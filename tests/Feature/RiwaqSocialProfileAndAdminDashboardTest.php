<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Community;
use App\Models\ContentReport;
use App\Models\Discussion;
use App\Models\SocialActivity;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqSocialProfileAndAdminDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $readerUser;

    protected User $companionUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::where('role', 'super_admin')->first() ?? User::factory()->create(['role' => 'super_admin']);
        $this->readerUser = User::where('role', 'user')->first() ?? User::factory()->create(['role' => 'user']);
        $this->companionUser = User::where('id', '!=', $this->readerUser->id)->where('id', '!=', $this->superAdmin->id)->first()
            ?? User::factory()->create(['role' => 'user']);

        $this->readerUser->following()->detach($this->companionUser->id);
        $this->companionUser->following()->detach($this->readerUser->id);
    }

    public function test_global_search_returns_unified_results_across_catalog_and_readers(): void
    {
        // 1. Visit empty search
        $response = $this->actingAs($this->readerUser)->get(route('search.index'));
        $response->assertStatus(200);
        $response->assertSee('الفهرس المعرفي الشامل');
        $response->assertSee('فهرس الكتب والأعمال الأدبية');

        // 2. Query search
        $book = Book::first();
        if ($book) {
            $response = $this->get(route('search.index', ['q' => mb_substr($book->title, 0, 4)]));
            $response->assertStatus(200);
            $response->assertSee($book->title);
        }

        // 3. Reader search
        $response = $this->get(route('search.index', ['q' => $this->companionUser->name, 'type' => 'readers']));
        $response->assertStatus(200);
        $response->assertSee($this->companionUser->name);
    }

    public function test_reader_profile_displays_stats_level_and_activities(): void
    {
        // Own profile
        $response = $this->actingAs($this->readerUser)->get(route('profile.show'));
        $response->assertStatus(200);
        $response->assertSee($this->readerUser->name);
        $response->assertSee('نقطة أثر');
        $response->assertSee('النشاط القرائي والاجتماعي');
        $response->assertSee('تعديل الملف');

        // Companion profile
        $response = $this->actingAs($this->readerUser)->get(route('profile.show', $this->companionUser->id));
        $response->assertStatus(200);
        $response->assertSee($this->companionUser->name);
        $response->assertSee('متابعة');
    }

    public function test_user_can_update_bio_and_annual_reading_goal(): void
    {
        $response = $this->actingAs($this->readerUser)->post(route('profile.updateBio'), [
            'bio' => 'باحث شغوف بالأدب الأندلسي والفلسفة الإسلامية.',
            'annual_goal' => 36,
        ]);

        $response->assertRedirect();
        $this->readerUser->refresh();
        $this->assertEquals('باحث شغوف بالأدب الأندلسي والفلسفة الإسلامية.', $this->readerUser->bio);
        $this->assertEquals(36, $this->readerUser->annual_goal);
    }

    public function test_user_can_follow_unfollow_and_triggers_notification(): void
    {
        // Initial state: not following
        $this->readerUser->following()->detach($this->companionUser->id);

        // Follow
        $response = $this->actingAs($this->readerUser)->post(route('profile.toggleFollow', $this->companionUser->id));
        $response->assertRedirect();
        $this->assertTrue($this->readerUser->fresh()->isFollowing($this->companionUser));

        // Verify Notification was dispatched to companion
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->companionUser->id,
            'type' => 'social',
        ]);

        // Verify Social Activity was logged
        $this->assertDatabaseHas('social_activities', [
            'user_id' => $this->readerUser->id,
            'activity_type' => 'friend_followed',
        ]);

        // Unfollow
        $response = $this->actingAs($this->readerUser)->post(route('profile.toggleFollow', $this->companionUser->id));
        $response->assertRedirect();
        $this->assertFalse($this->readerUser->fresh()->isFollowing($this->companionUser));
    }

    public function test_user_can_cheer_social_activities(): void
    {
        $activity = SocialActivity::create([
            'user_id' => $this->companionUser->id,
            'activity_type' => 'reading_session',
            'title' => 'أتمّ 40 دقيقة قراءة في كليلة ودمنة',
            'cheers_count' => 2,
        ]);

        $response = $this->actingAs($this->readerUser)->post(route('activities.cheer', $activity->id));
        $response->assertRedirect();

        $this->assertEquals(3, $activity->fresh()->cheers_count);
    }

    public function test_super_admin_authorization_and_dashboard_tabs(): void
    {
        // Regular user receives 403 Forbidden
        $response = $this->actingAs($this->readerUser)->get(route('admin.dashboard'));
        $response->assertStatus(403);

        // Super Admin receives 200 OK
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('لوحة إدارة المنصة (Super Admin)');
        $response->assertSee('نظرة عامة ومؤشرات');

        // Test tabs: users, content, reports, settings
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard', ['tab' => 'users']));
        $response->assertStatus(200);
        $response->assertSee('إدارة المستخدمين وتعيين الأدوار والتعليق');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard', ['tab' => 'content']));
        $response->assertStatus(200);
        $response->assertSee('إدارة المحتوى والمناقشات والردود');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard', ['tab' => 'reports']));
        $response->assertStatus(200);
        $response->assertSee('طابور البلاغات والشكاوى المركزية');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard', ['tab' => 'settings']));
        $response->assertStatus(200);
        $response->assertSee('الإعدادات العامة وسياسات المنصة');
    }

    public function test_super_admin_can_update_user_role_and_toggle_suspension(): void
    {
        // 1. Update role
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateRole', $this->companionUser->id), [
            'role' => 'admin',
            'confirmed' => 1,
        ]);
        $response->assertRedirect();
        $this->assertEquals('admin', $this->companionUser->fresh()->role);

        // 2. Suspend user
        $response = $this->actingAs($this->superAdmin)->post(route('admin.toggleSuspension', $this->companionUser->id), [
            'suspension_reason' => 'مخالفة معايير النقاش الهادف.',
            'confirmed' => 1,
        ]);
        $response->assertRedirect();
        $this->companionUser->refresh();
        $this->assertTrue($this->companionUser->is_suspended);
        $this->assertEquals('مخالفة معايير النقاش الهادف.', $this->companionUser->suspension_reason);

        // 3. Unsuspend user
        $response = $this->actingAs($this->superAdmin)->post(route('admin.toggleSuspension', $this->companionUser->id), [
            'confirmed' => 1,
        ]);
        $response->assertRedirect();
        $this->companionUser->refresh();
        $this->assertFalse($this->companionUser->is_suspended);
        $this->assertNull($this->companionUser->suspension_reason);
    }

    public function test_super_admin_can_delete_content_and_handle_reports(): void
    {
        $community = Community::first();
        if (! $community) {
            $community = Community::create([
                'name' => 'نادي الفلسفة',
                'category' => 'فلسفة',
                'type' => 'public',
                'creator_id' => $this->superAdmin->id,
            ]);
        }

        // 1. Create and delete discussion
        $discussion = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $this->readerUser->id,
            'title' => 'نقاش مخالف للتجربة',
            'content' => 'محتوى مخالف يستوجب الحذف.',
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('admin.deleteContent', [
            'type' => 'discussion',
            'id' => $discussion->id,
        ]));
        $response->assertRedirect();
        $this->assertSoftDeleted('discussions', ['id' => $discussion->id]);

        // 2. Create and handle content report
        $report = ContentReport::create([
            'user_id' => $this->readerUser->id,
            'reportable_type' => 'discussion',
            'reportable_id' => 99999,
            'reason' => 'spam',
            'details' => 'إعلانات متكررة غير مرغوبة.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.handleReport', $report->id), [
            'action' => 'dismiss',
            'moderator_notes' => 'بلاغ كيدي وتم حفظه.',
        ]);
        $response->assertRedirect();
        $this->assertEquals('dismissed', $report->fresh()->status);
        $this->assertEquals('بلاغ كيدي وتم حفظه.', $report->fresh()->moderator_notes);
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateSettings'), [
            'settings' => [
                'site_name' => 'رِواق المعرفة والبيان',
                'max_active_loans' => 5,
                'loan_duration_days' => 21,
                'allow_registrations' => 1,
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals('رِواق المعرفة والبيان', SystemSetting::get('site_name'));
        $this->assertEquals(5, SystemSetting::get('max_active_loans'));
        $this->assertEquals(21, SystemSetting::get('loan_duration_days'));
    }
}
