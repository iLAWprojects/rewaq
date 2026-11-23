<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookPoll;
use App\Models\BookPollOption;
use App\Models\Community;
use App\Models\Discussion;
use App\Models\Library;
use App\Models\LibraryMember;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiwaqRolesPermissionsAndAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * قاعدة 1: لا يجوز أن تمنح صلاحية منصة (Super Admin) تلقائياً لمجرد كون المستخدم مالك مكتبة أو مجتمع
     */
    public function test_library_and_community_owners_do_not_automatically_receive_super_admin_role(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $library = Library::create([
            'user_id' => $user->id,
            'name' => 'خزانة الأدب',
            'type' => 'personal',
            'is_personal' => true,
        ]);

        $community = Community::create([
            'creator_id' => $user->id,
            'name' => 'مجلس الفكر المعاصر',
            'code' => 'TESTCOMM_'.Str::random(8),
            'type' => 'public',
        ]);

        // User is owner of library and community
        $this->assertTrue($library->isOwner($user));
        $this->assertTrue($community->isOwner($user));

        // BUT user must NOT be Super Admin on platform level
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->canManagePlatform());

        // Attempting to access super admin dashboard must be forbidden (403)
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    /**
     * قاعدة 2: أدوار المكتبة - المشاهد يقرأ الفهرس فقط ويُمنع من الاستعارة
     */
    public function test_library_viewer_role_can_view_catalog_but_is_forbidden_from_borrowing(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $viewer = User::factory()->create(['role' => 'user']);

        $library = Library::create([
            'user_id' => $owner->id,
            'name' => 'مكتبة بيت الحكمة',
            'type' => 'club',
            'is_personal' => false,
        ]);

        // Assign viewer role
        LibraryMember::create([
            'library_id' => $library->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'joined_at' => now(),
        ]);

        $book = Book::create([
            'title' => 'مقدمة ابن خلدون',
            'author' => 'عبد الرحمن بن خلدون',
            'category' => 'فكر',
        ]);

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => '1',
            'barcode' => 'RWQ-TEST-VIEWER',
            'status' => 'available',
            'condition' => 'good',
        ]);

        $this->assertTrue($library->canViewCatalog($viewer));
        $this->assertFalse($library->canBorrow($viewer));

        // Attempt to request loan as viewer should be rejected (403 forbidden)
        $response = $this->actingAs($viewer)->post(route('libraries.requestLoan', $copy->id));
        $response->assertStatus(403);
    }

    /**
     * قاعدة 3: أدوار المجتمع - العضو الموقوف يُمنع من كتابة مناقشة أو التصويت في الاستطلاع
     */
    public function test_suspended_community_member_cannot_post_discussion_or_vote_in_polls(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $suspendedUser = User::factory()->create(['role' => 'user']);

        $community = Community::create([
            'creator_id' => $owner->id,
            'name' => 'مجلس الرواية العربية',
            'code' => 'NOVELS_'.Str::random(8),
            'type' => 'public',
        ]);

        // Attach suspended membership
        $community->members()->attach($suspendedUser->id, ['role' => 'suspended']);

        $this->assertTrue($community->isSuspended($suspendedUser));
        $this->assertFalse($community->canPostDiscussion($suspendedUser));
        $this->assertFalse($community->canVotePoll($suspendedUser));

        // Attempting to post discussion should fail (403)
        $discResponse = $this->actingAs($suspendedUser)->post(route('communities.storeDiscussion', $community->id), [
            'note' => 'مشاركة مخالفة من حساب موقوف',
        ]);
        $discResponse->assertStatus(403);

        // Attempting to vote in a poll should fail (403)
        $poll = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'كتاب الشهر القادم',
            'is_active' => true,
        ]);
        $opt = BookPollOption::create([
            'book_poll_id' => $poll->id,
            'custom_title' => 'طوق الحمامة · ابن حزم',
            'votes_count' => 0,
        ]);

        $voteResponse = $this->actingAs($suspendedUser)->post(route('polls.vote', $poll->id), [
            'option_id' => $opt->id,
        ]);
        $voteResponse->assertStatus(403);
    }

    /**
     * قاعدة 4: منع آخر مالك من مغادرة المكتبة أو المجلس قبل تعيين مالك آخر
     */
    public function test_last_owner_cannot_leave_library_or_community(): void
    {
        $owner = User::factory()->create(['role' => 'user']);

        $library = Library::create([
            'user_id' => $owner->id,
            'name' => 'خزانة المعارف',
            'type' => 'personal',
            'is_personal' => true,
        ]);
        $libMember = LibraryMember::create([
            'library_id' => $library->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $community = Community::create([
            'creator_id' => $owner->id,
            'name' => 'مجلس رواد القراءة',
            'code' => 'PIONEERS_'.Str::random(8),
            'type' => 'public',
        ]);
        $community->members()->attach($owner->id, ['role' => 'owner']);

        // 1. Library: Trying to remove or leave as last owner is forbidden by Policy (403)
        $libLeaveResponse = $this->actingAs($owner)->delete(route('libraries.removeMember', [$library->id, $libMember->id]));
        $libLeaveResponse->assertStatus(403);
        $this->assertDatabaseHas('library_members', ['id' => $libMember->id]);

        // 2. Community: Trying to toggle membership (leave) as only owner is forbidden by Policy (403)
        $commLeaveResponse = $this->actingAs($owner)->post(route('communities.toggle', $community->id));
        $commLeaveResponse->assertStatus(403);
        $this->assertTrue($community->members()->where('user_id', $owner->id)->exists());
    }

    /**
     * قاعدة 5: نقل الملكية عملية حساسة تتطلب تأكيداً وتسجل في سجل التدقيق (Audit Log)
     */
    public function test_transfer_ownership_requires_confirmation_and_creates_audit_log(): void
    {
        $oldOwner = User::factory()->create(['name' => 'المالك القديم', 'role' => 'user']);
        $newOwner = User::factory()->create(['name' => 'المالك الجديد', 'role' => 'user']);

        $community = Community::create([
            'creator_id' => $oldOwner->id,
            'name' => 'مجلس الفلسفة والأدب',
            'code' => 'PHILOS_'.Str::random(8),
            'type' => 'public',
        ]);
        $community->members()->attach($oldOwner->id, ['role' => 'owner']);
        $community->members()->attach($newOwner->id, ['role' => 'member']);

        // Without confirmation checkbox, validation fails
        $failResponse = $this->actingAs($oldOwner)->post(route('communities.transferOwnership', $community->id), [
            'new_owner_id' => $newOwner->id,
        ]);
        $failResponse->assertSessionHasErrors(['confirm']);

        // With explicit confirmation
        $successResponse = $this->actingAs($oldOwner)->post(route('communities.transferOwnership', $community->id), [
            'new_owner_id' => $newOwner->id,
            'confirm' => '1',
        ]);
        $successResponse->assertSessionHas('success');

        // Verify community ownership transferred
        $community->refresh();
        $this->assertEquals($newOwner->id, $community->creator_id);
        $this->assertTrue($community->isOwner($newOwner));

        // Verify Audit Log entry created
        $audit = AuditLog::where('action', 'transfer_community_ownership')
            ->where('auditable_id', $community->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($oldOwner->id, $audit->user_id);
        $this->assertStringContainsString('تم نقل ملكية المجلس', $audit->description);
        $this->assertEquals($oldOwner->id, $audit->old_values['creator_id']);
        $this->assertEquals($newOwner->id, $audit->new_values['creator_id']);
    }

    /**
     * قاعدة 6: الحذف يكون Soft Delete للمستخدم والمجتمع والمكتبة والاجتماع
     */
    public function test_soft_deletes_applied_on_user_community_library_and_meeting(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $targetUser = User::factory()->create(['role' => 'user']);

        $library = Library::create([
            'user_id' => $targetUser->id,
            'name' => 'مكتبة الحفظ',
            'type' => 'personal',
        ]);

        $community = Community::create([
            'creator_id' => $targetUser->id,
            'name' => 'مجلس الأمانة',
            'code' => 'AMANAH_'.Str::random(8),
            'type' => 'public',
        ]);

        $meeting = Meeting::create([
            'community_id' => $community->id,
            'title' => 'لقاء مناقشة كتاب الشهر',
            'meeting_date' => now()->addDays(2),
            'meeting_type' => 'remote',
        ]);

        // Soft delete Meeting
        $this->actingAs($superAdmin)->delete(route('calendar.destroy', $meeting->id));
        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);

        // Soft delete Community
        $this->actingAs($superAdmin)->delete(route('communities.destroy', $community->id));
        $this->assertSoftDeleted('communities', ['id' => $community->id]);

        // Soft delete Library
        $this->actingAs($superAdmin)->delete(route('libraries.destroy', $library->id));
        $this->assertSoftDeleted('libraries', ['id' => $library->id]);

        // Soft delete User
        $this->actingAs($superAdmin)->delete(route('admin.destroyUser', $targetUser->id));
        $this->assertSoftDeleted('users', ['id' => $targetUser->id]);

        // Check AuditLog entries for each deletion
        $this->assertTrue(AuditLog::where('action', 'delete_meeting')->exists());
        $this->assertTrue(AuditLog::where('action', 'delete_community')->exists());
        $this->assertTrue(AuditLog::where('action', 'delete_library')->exists());
        $this->assertTrue(AuditLog::where('action', 'delete_user')->exists());
    }

    /**
     * اختبار لوحة Super Admin وعرض سجل التدقيق (Audit Log)
     */
    public function test_super_admin_can_view_audit_logs_tab_in_dashboard(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        // Record a mock audit log
        AuditLog::record(
            $superAdmin,
            'test_action',
            null,
            'عملية تدقيق تجريبية للنظام'
        );

        $response = $this->actingAs($superAdmin)->get(route('admin.dashboard', ['tab' => 'audit_logs']));
        $response->assertStatus(200);
        $response->assertSee('سجل التدقيق والرقابة الشامل (Audit Log)');
        $response->assertSee('عملية تدقيق تجريبية للنظام');
    }
}
