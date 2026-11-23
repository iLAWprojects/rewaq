<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Community;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPushSubscription;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqAdminSecurityAndMfaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $secondarySuperAdmin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have a clean set of users for testing
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_suspended' => false,
            'mfa_enabled' => false,
        ]);

        $this->secondarySuperAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_suspended' => false,
            'mfa_enabled' => false,
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
            'is_suspended' => false,
            'mfa_enabled' => false,
        ]);

        SystemSetting::set('admin_mfa_enforced', '0', 'boolean', 'security');
        SystemSetting::set('admin_session_timeout_minutes', '15', 'integer', 'security');
    }

    /**
     * 1. حماية المدير الأخير: لا يمكن للمدير إلغاء صلاحية نفسه أو غيره إذا كان آخر Super Admin
     */
    public function test_last_super_admin_cannot_be_demoted(): void
    {
        // Demote secondary admin first so only one active super_admin remains
        $this->secondarySuperAdmin->update(['role' => 'user']);

        // Ensure there is only 1 super_admin left
        User::where('role', 'super_admin')->where('id', '!=', $this->superAdmin->id)->update(['role' => 'user']);
        $this->assertEquals(1, User::where('role', 'super_admin')->where('is_suspended', false)->count());

        // Attempting to demote the sole super_admin must fail
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateRole', $this->superAdmin->id), [
            'role' => 'admin',
            'confirmed' => 1,
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('super_admin', $this->superAdmin->fresh()->role);
    }

    public function test_super_admin_can_be_demoted_if_another_super_admin_exists(): void
    {
        $this->assertTrue(User::where('role', 'super_admin')->where('is_suspended', false)->count() >= 2);

        // Demoting secondary admin should succeed because primary super admin exists
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateRole', $this->secondarySuperAdmin->id), [
            'role' => 'admin',
            'confirmed' => 1,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('admin', $this->secondarySuperAdmin->fresh()->role);
    }

    /**
     * حماية المدير الأخير: منع تعليق الحساب للنفس أو لآخر Super Admin
     */
    public function test_cannot_suspend_oneself_or_last_super_admin(): void
    {
        // 1. Self-suspension is blocked
        $response = $this->actingAs($this->superAdmin)->post(route('admin.toggleSuspension', $this->superAdmin->id), [
            'confirmed' => 1,
        ]);
        $response->assertSessionHas('error');
        $this->assertFalse($this->superAdmin->fresh()->is_suspended);

        // 2. Suspending the last active super_admin is blocked
        $this->secondarySuperAdmin->update(['role' => 'user']);
        User::where('role', 'super_admin')->where('id', '!=', $this->superAdmin->id)->update(['role' => 'user']);

        $tempAdmin = User::factory()->create(['role' => 'super_admin', 'is_suspended' => false]);
        $this->superAdmin->update(['role' => 'user']); // now tempAdmin is the only super admin

        $response = $this->actingAs($tempAdmin)->post(route('admin.toggleSuspension', $tempAdmin->id), [
            'confirmed' => 1,
        ]);
        $response->assertSessionHas('error');
        $this->assertFalse($tempAdmin->fresh()->is_suspended);
    }

    /**
     * حماية المدير الأخير: منع حذف النفس أو حذف آخر Super Admin
     */
    public function test_cannot_delete_oneself_or_last_super_admin(): void
    {
        // 1. Self-deletion is blocked by policy (403 Forbidden)
        $response = $this->actingAs($this->superAdmin)->delete(route('admin.destroyUser', $this->superAdmin->id));
        $response->assertForbidden();
        $this->assertNull($this->superAdmin->fresh()->deleted_at);

        // 2. Deleting last super_admin is blocked
        $tempAdmin = User::factory()->create(['role' => 'admin', 'is_suspended' => false]);
        $this->secondarySuperAdmin->update(['role' => 'user']);
        User::where('role', 'super_admin')->where('id', '!=', $this->superAdmin->id)->update(['role' => 'user']);

        // Another admin attempting to delete the only super admin is forbidden
        $response = $this->actingAs($tempAdmin)->delete(route('admin.destroyUser', $this->superAdmin->id));
        $response->assertForbidden();
        $this->assertFalse($this->superAdmin->fresh()->trashed());
    }

    /**
     * 2. تأكيد صريح وتوثيق في سجل التدقيق: تعليق أو تغيير Role يتطلب تأكيداً واضحاً ويسجل Audit Event
     */
    public function test_role_change_requires_explicit_confirmation(): void
    {
        // Missing confirmed field
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateRole', $this->regularUser->id), [
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors(['confirmed']);
        $this->assertEquals('user', $this->regularUser->fresh()->role);

        // With confirmed field
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateRole', $this->regularUser->id), [
            'role' => 'admin',
            'confirmed' => 1,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('admin', $this->regularUser->fresh()->role);

        // Verify AuditLog recorded with confirmation flag
        $auditLog = AuditLog::where('action', 'change_platform_role')
            ->where('auditable_id', $this->regularUser->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->superAdmin->id, $auditLog->user_id);
        $this->assertTrue($auditLog->new_values['security_confirmation'] ?? false);
    }

    public function test_user_suspension_requires_explicit_confirmation(): void
    {
        // Missing confirmed parameter
        $response = $this->actingAs($this->superAdmin)->post(route('admin.toggleSuspension', $this->regularUser->id), [
            'suspension_reason' => 'مخالفة السياسات.',
        ]);

        $response->assertSessionHasErrors(['confirmed']);
        $this->assertFalse($this->regularUser->fresh()->is_suspended);

        // With confirmed parameter
        $response = $this->actingAs($this->superAdmin)->post(route('admin.toggleSuspension', $this->regularUser->id), [
            'suspension_reason' => 'مخالفة السياسات.',
            'confirmed' => 1,
        ]);

        $response->assertSessionHas('info');
        $this->assertTrue($this->regularUser->fresh()->is_suspended);

        // Verify AuditLog record
        $auditLog = AuditLog::where('action', 'suspend_user')
            ->where('auditable_id', $this->regularUser->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertTrue($auditLog->new_values['security_confirmation'] ?? false);
    }

    /**
     * 3. التحقق الثنائي (MFA) وإدارة الجلسات
     */
    public function test_admin_with_mfa_enabled_is_redirected_to_verification_screen(): void
    {
        $mfaService = app(MfaService::class);
        $secret = $mfaService->generateSecret();
        $recoveryCodes = $mfaService->generateRecoveryCodes();

        $this->superAdmin->update([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'recovery_codes' => $recoveryCodes,
        ]);

        // Attempting to access admin dashboard without admin_mfa_verified session
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.mfa.verify'));

        // Visit MFA verify page
        $response = $this->actingAs($this->superAdmin)->get(route('admin.mfa.verify'));
        $response->assertStatus(200);
        $response->assertSee('التحقق الثنائي للمشرفين');
    }

    public function test_admin_can_verify_mfa_using_valid_totp_code(): void
    {
        $mfaService = app(MfaService::class);
        $secret = $mfaService->generateSecret();

        $this->superAdmin->update([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'recovery_codes' => ['TEST-1234', 'TEST-5678'],
        ]);

        $validCode = $mfaService->getCurrentOtp($secret);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.mfa.verify.post'), [
            'code' => $validCode,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('admin_mfa_verified'));

        // Now admin can access dashboard freely
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    public function test_admin_can_verify_mfa_using_backup_recovery_code(): void
    {
        $mfaService = app(MfaService::class);
        $secret = $mfaService->generateSecret();

        $this->superAdmin->update([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'recovery_codes' => ['TEST-RECOVER-1', 'TEST-RECOVER-2'],
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.mfa.verify.post'), [
            'code' => 'TEST-RECOVER-1',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('admin_mfa_verified'));

        // Recovery code TEST-RECOVER-1 must be consumed/burned
        $this->superAdmin->refresh();
        $this->assertNotContains('TEST-RECOVER-1', $this->superAdmin->recovery_codes);
        $this->assertContains('TEST-RECOVER-2', $this->superAdmin->recovery_codes);
    }

    public function test_invalid_mfa_code_is_rejected(): void
    {
        $mfaService = app(MfaService::class);
        $secret = $mfaService->generateSecret();

        $this->superAdmin->update([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'recovery_codes' => ['TEST-RECOVER-1'],
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.mfa.verify.post'), [
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertFalse((bool) session('admin_mfa_verified'));
    }

    public function test_mandatory_mfa_setting_forces_unenrolled_admins_to_setup(): void
    {
        SystemSetting::set('admin_mfa_enforced', '1', 'boolean', 'security');

        $this->assertFalse($this->superAdmin->hasMfaEnabled());

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.mfa.setup'));

        // Admin visits setup screen
        $response = $this->actingAs($this->superAdmin)->get(route('admin.mfa.setup'));
        $response->assertStatus(200);
        $response->assertSee('إعداد التحقق الثنائي');
    }

    public function test_admin_session_timeout_after_inactivity_period(): void
    {
        // Set last activity to 20 minutes ago (timeout is 15 minutes)
        session(['admin_last_activity' => now()->subMinutes(20)->timestamp]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning');

        // Verify audit log for session timeout was recorded
        $this->assertTrue(AuditLog::where('action', 'admin_session_timeout')->exists());
    }

    /**
     * 4. حجب الرموز الحساسة وكلمات المرور في الواجهات وسجلات التدقيق
     */
    public function test_sensitive_attributes_are_hidden_from_serialization(): void
    {
        $mfaService = app(MfaService::class);
        $secret = $mfaService->generateSecret();
        $recoveryCodes = ['CODE1', 'CODE2'];

        $this->superAdmin->update([
            'mfa_secret' => $secret,
            'recovery_codes' => $recoveryCodes,
        ]);

        $userArray = $this->superAdmin->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('mfa_secret', $userArray);
        $this->assertArrayNotHasKey('recovery_codes', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);

        // Push subscription keys are hidden
        $pushSub = UserPushSubscription::create([
            'user_id' => $this->regularUser->id,
            'endpoint' => 'https://push.example.com/test',
            'p256dh' => 'SENSITIVE_P256DH_KEY',
            'auth_token' => 'SENSITIVE_AUTH_TOKEN',
        ]);

        $pushArray = $pushSub->toArray();
        $this->assertArrayNotHasKey('p256dh', $pushArray);
        $this->assertArrayNotHasKey('auth_token', $pushArray);
    }

    public function test_audit_log_scrubs_and_sanitizes_sensitive_keys(): void
    {
        $log = AuditLog::record(
            $this->superAdmin,
            'security_test',
            $this->regularUser,
            'اختبار حجب البيانات الحساسة',
            [
                'password' => 'secret12345',
                'api_token' => 'token_xyz_987',
                'secret_key' => 'shhhhh',
                'recovery_codes' => ['RECOVER1', 'RECOVER2'],
                'visible_field' => 'معلومة مقبولة',
            ],
            [
                'auth_token' => 'new_auth_token_456',
                'p256dh' => 'new_p256dh_key_789',
                'status' => 'active',
            ]
        );

        $this->assertEquals('******** (محمي أمنياً)', $log->old_values['password']);
        $this->assertEquals('******** (محمي أمنياً)', $log->old_values['api_token']);
        $this->assertEquals('******** (محمي أمنياً)', $log->old_values['secret_key']);
        $this->assertEquals('******** (محمي أمنياً)', $log->old_values['recovery_codes']);
        $this->assertEquals('معلومة مقبولة', $log->old_values['visible_field']);

        $this->assertEquals('******** (محمي أمنياً)', $log->new_values['auth_token']);
        $this->assertEquals('******** (محمي أمنياً)', $log->new_values['p256dh']);
        $this->assertEquals('active', $log->new_values['status']);
    }

    /**
     * 5. الحذف المرن والأرشفة (Soft Delete / Archive) لكافة الإجراءات المدمرة
     */
    public function test_destructive_actions_use_soft_deletes(): void
    {
        // 1. Soft delete User
        $userToDelete = User::factory()->create(['role' => 'user']);
        $response = $this->actingAs($this->superAdmin)->delete(route('admin.destroyUser', $userToDelete->id));
        $response->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $userToDelete->id]);
        $this->assertTrue(AuditLog::where('action', 'delete_user')->where('auditable_id', $userToDelete->id)->exists());

        // 2. Soft delete Book
        $book = Book::create([
            'title' => 'كتاب تجريبي للحذف المرن',
            'author' => 'كاتب تجريبي',
        ]);
        $response = $this->actingAs($this->superAdmin)->delete(route('admin.deleteContent', [
            'type' => 'book',
            'id' => $book->id,
        ]));
        $response->assertRedirect();
        $this->assertSoftDeleted('books', ['id' => $book->id]);
        $this->assertTrue(AuditLog::where('action', 'delete_book')->where('auditable_id', $book->id)->exists());

        // 3. Soft delete Discussion
        $community = Community::first() ?? Community::create([
            'name' => 'مجتمع تجريبي',
            'category' => 'فكر',
            'type' => 'public',
            'creator_id' => $this->superAdmin->id,
        ]);

        $discussion = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $this->regularUser->id,
            'title' => 'نقاش تجريبي للحذف المرن',
            'content' => 'محتوى نقاش تجريبي.',
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('admin.deleteContent', [
            'type' => 'discussion',
            'id' => $discussion->id,
        ]));
        $response->assertRedirect();
        $this->assertSoftDeleted('discussions', ['id' => $discussion->id]);
        $this->assertTrue(AuditLog::where('action', 'delete_discussion')->where('auditable_id', $discussion->id)->exists());

        // 4. Soft delete DiscussionComment
        $comment = DiscussionComment::create([
            'discussion_id' => $discussion->id,
            'user_id' => $this->regularUser->id,
            'content' => 'تعليق تجريبي للحذف المرن.',
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('admin.deleteContent', [
            'type' => 'comment',
            'id' => $comment->id,
        ]));
        $response->assertRedirect();
        $this->assertSoftDeleted('discussion_comments', ['id' => $comment->id]);
        $this->assertTrue(AuditLog::where('action', 'delete_comment')->where('auditable_id', $comment->id)->exists());
    }
}
