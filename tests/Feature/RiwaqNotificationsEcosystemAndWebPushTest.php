<?php

namespace Tests\Feature;

use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserPushSubscription;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqNotificationsEcosystemAndWebPushTest extends TestCase
{
    use DatabaseTransactions;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * 1. In-app notifications: unread/read + deep link + category + priority + read_at.
     */
    public function test_in_app_notifications_lifecycle_category_priority_and_deep_link(): void
    {
        $user = User::factory()->create();

        $notification = $this->notificationService->send($user, [
            'title' => 'موعد مناقشة كتاب البداية والنهاية',
            'body' => 'سيبدأ اللقاء القرائي خلال ساعة في مجلس التاريخ.',
            'category' => UserNotification::CATEGORY_MEETINGS,
            'priority' => UserNotification::PRIORITY_HIGH,
            'link' => '/calendar?meeting=42',
            'type' => 'meeting',
        ]);

        $this->assertNotNull($notification);
        $this->assertEquals($user->id, $notification->user_id);
        $this->assertEquals('meetings', $notification->category);
        $this->assertEquals('high', $notification->priority);
        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);
        $this->assertEquals(url('/calendar?meeting=42'), $notification->url);
        $this->assertEquals('الاجتماعات والتقويم', $notification->category_label);
        $this->assertEquals('هام', $notification->priority_label);

        // Mark as read via controller
        $response = $this->actingAs($user)->postJson(route('notifications.markAsRead', $notification));
        $response->assertOk()
            ->assertJson(['success' => true, 'is_read' => true]);

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);

        // Test mark all as read
        $notif2 = $this->notificationService->send($user, [
            'title' => 'تحدي جديد بانتظارك',
            'body' => 'انضم إلى تحدي الأدب الأندلسي الآن.',
            'category' => UserNotification::CATEGORY_CHALLENGES,
        ]);
        $this->assertFalse($notif2->fresh()->is_read);

        $responseAll = $this->actingAs($user)->postJson(route('notifications.markAllAsRead'));
        $responseAll->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($notif2->fresh()->is_read);
        $this->assertNotNull($notif2->fresh()->read_at);
    }

    /**
     * 2. Web Push / FCM with explicit Opt-in.
     */
    public function test_web_push_subscription_management_and_explicit_opt_in(): void
    {
        $user = User::factory()->create([
            'push_opt_in' => false,
        ]);

        // Push subscription registration
        $response = $this->actingAs($user)->postJson(route('notifications.push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-abc-123',
            'p256dh' => 'BL6Y89...test-p256dh',
            'auth_token' => 'auth-token-xyz',
            'device_name' => 'Safari على MacBook',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'opt_in' => true]);

        $user->refresh();
        $this->assertTrue($user->push_opt_in);

        $subscription = UserPushSubscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('https://fcm.googleapis.com/fcm/send/test-endpoint-abc-123', $subscription->endpoint);
        $this->assertTrue($subscription->is_active);

        // Unsubscribe
        $unsubResponse = $this->actingAs($user)->postJson(route('notifications.push.unsubscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-abc-123',
        ]);
        $unsubResponse->assertOk()->assertJson(['success' => true]);

        $subscription->refresh();
        $this->assertFalse($subscription->is_active);

        // Toggle opt-in off
        $toggleResponse = $this->actingAs($user)->postJson(route('notifications.push.toggleOptIn'), [
            'push_opt_in' => false,
        ]);
        $toggleResponse->assertOk()->assertJson(['push_opt_in' => false]);
        $this->assertFalse($user->fresh()->push_opt_in);
    }

    /**
     * 3. Preference per category: in-app and push channels.
     */
    public function test_preferences_per_category_suppression(): void
    {
        $user = User::factory()->create([
            'push_opt_in' => true,
        ]);

        // Create active subscription
        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.com/test-endpoint',
            'is_active' => true,
        ]);

        // Set preferences: Disable in-app for comments, disable push for loans
        $user->updateNotificationPreferences([
            'comments' => ['in_app' => false, 'push' => false],
            'loans' => ['in_app' => true, 'push' => false],
        ]);

        $this->assertFalse($user->wantsNotification('comments', 'in_app'));
        $this->assertFalse($user->wantsNotification('loans', 'push'));
        $this->assertTrue($user->wantsNotification('loans', 'in_app'));

        // Send a comment notification -> In-app must be suppressed
        $notifComment = $this->notificationService->send($user, [
            'title' => 'تعليق جديد على مراجعتك',
            'body' => 'أضاف أحمد تعليقاً في مجلس الرواية.',
            'category' => UserNotification::CATEGORY_COMMENTS,
            'priority' => UserNotification::PRIORITY_NORMAL,
        ]);

        $this->assertNull($notifComment); // In-app was suppressed

        $logComment = NotificationDeliveryLog::where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->where('category', 'comments')
            ->first();

        $this->assertNotNull($logComment);
        $this->assertEquals('suppressed_preference', $logComment->status);

        // Send a loan notification -> In-app delivered, push suppressed by preference
        $notifLoan = $this->notificationService->send($user, [
            'title' => 'اقترب موعد إرجاع كتاب مقدمة ابن خلدون',
            'body' => 'يرجى إعادة الكتاب أو تمديد الإعارة خلال يومين.',
            'category' => UserNotification::CATEGORY_LOANS,
            'priority' => UserNotification::PRIORITY_NORMAL,
        ]);

        $this->assertNotNull($notifLoan); // In-app delivered

        $loanPushLog = NotificationDeliveryLog::where('user_id', $user->id)
            ->where('channel', 'web_push')
            ->where('category', 'loans')
            ->first();

        $this->assertNotNull($loanPushLog);
        $this->assertEquals('suppressed_preference', $loanPushLog->status);
    }

    /**
     * 4. Quiet Hours evaluated accurately according to User's specific Timezone.
     */
    public function test_quiet_hours_based_on_user_timezone(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Asia/Riyadh',
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
            'push_opt_in' => true,
        ]);

        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.com/test-endpoint',
            'is_active' => true,
        ]);

        // Case A: Current time is 23:30 in Riyadh (Within quiet hours)
        $nightTime = Carbon::parse('2026-10-18 23:30:00', 'Asia/Riyadh');
        $this->assertTrue($user->isInQuietHours($nightTime));

        // Normal priority notification should suppress Web Push
        $this->notificationService->send($user, [
            'title' => 'تحديث مجتمعي هادئ',
            'body' => 'تم إضافة كتاب جديد في المكتبة العامة.',
            'category' => UserNotification::CATEGORY_COMMUNITIES,
            'priority' => UserNotification::PRIORITY_NORMAL,
            'reference_time' => $nightTime,
        ]);

        $nightLog = NotificationDeliveryLog::where('user_id', $user->id)
            ->where('channel', 'web_push')
            ->where('category', 'communities')
            ->where('status', 'suppressed_quiet_hours')
            ->first();

        $this->assertNotNull($nightLog);
        $this->assertEquals('suppressed_quiet_hours', $nightLog->status);

        // Case B: Urgent priority bypasses quiet hours
        $this->notificationService->send($user, [
            'title' => 'تنبيه عاجل: تأكيد حجز مقعدك في اللقاء',
            'body' => 'توفر مقعد لك في المجلس وعليك التأكيد خلال 15 دقيقة.',
            'category' => UserNotification::CATEGORY_MEETINGS,
            'priority' => UserNotification::PRIORITY_URGENT,
            'reference_time' => $nightTime,
        ]);

        $urgentLog = NotificationDeliveryLog::where('user_id', $user->id)
            ->where('channel', 'web_push')
            ->where('category', 'meetings')
            ->where('priority', 'urgent')
            ->where('status', 'delivered')
            ->first();

        $this->assertNotNull($urgentLog);
        $this->assertEquals('delivered', $urgentLog->status);

        // Case C: Outside quiet hours (14:00 in Riyadh)
        $dayTime = Carbon::parse('2026-10-18 14:00:00', 'Asia/Riyadh');
        $this->assertFalse($user->isInQuietHours($dayTime));

        $this->notificationService->send($user, [
            'title' => 'شروق معرفي',
            'body' => 'جلسة قراءة نهارية.',
            'category' => UserNotification::CATEGORY_CHALLENGES,
            'priority' => UserNotification::PRIORITY_NORMAL,
            'reference_time' => $dayTime,
        ]);

        $dayLog = NotificationDeliveryLog::where('user_id', $user->id)
            ->where('channel', 'web_push')
            ->where('category', 'challenges')
            ->where('status', 'delivered')
            ->first();

        $this->assertNotNull($dayLog);
        $this->assertEquals('delivered', $dayLog->status);
    }

    /**
     * 5. Suppress Web Push for low-priority social pings by default.
     * "لا تُرسل Push للنشاط الاجتماعي منخفض الأهمية افتراضياً"
     */
    public function test_push_is_suppressed_for_low_priority_social_activity_by_default(): void
    {
        $user = User::factory()->create([
            'push_opt_in' => true,
            'quiet_hours_enabled' => false,
        ]);

        $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.com/test-endpoint',
            'is_active' => true,
        ]);

        // Send low priority social notification (e.g. cheer, routine like)
        $this->notificationService->send($user, [
            'title' => 'إعجاب بنشاطك القرائي',
            'body' => 'أبدى زيد إعجابه بإتمامك الفصل الرابع.',
            'category' => UserNotification::CATEGORY_FRIENDSHIPS,
            'priority' => UserNotification::PRIORITY_LOW,
        ]);

        $lowPushLog = NotificationDeliveryLog::where('user_id', $user->id)
            ->where('channel', 'web_push')
            ->where('priority', 'low')
            ->first();

        $this->assertNotNull($lowPushLog);
        $this->assertEquals('skipped_low_priority', $lowPushLog->status);
        $this->assertEquals('200_SUPPRESSED_LOW_PRIORITY', $lowPushLog->response_code);
    }

    /**
     * 6. Delivery log preserves privacy without persisting sensitive message payload.
     * "Delivery log يحفظ القناة والنتيجة دون تخزين محتوى حساس أكثر من اللازم"
     */
    public function test_delivery_logs_do_not_store_sensitive_content_payload(): void
    {
        $user = User::factory()->create();

        $secretTitle = 'عنوان سري للغاية لا يجب تخزينه في سجل التوصيل';
        $secretBody = 'تفاصيل شخصية حساسة عن القارئ';

        $notif = $this->notificationService->send($user, [
            'title' => $secretTitle,
            'body' => $secretBody,
            'category' => UserNotification::CATEGORY_SYSTEM,
            'priority' => UserNotification::PRIORITY_NORMAL,
        ]);

        $log = NotificationDeliveryLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);

        // Verify delivery log table schema contains only non-sensitive columns
        $logArray = $log->toArray();
        $this->assertArrayNotHasKey('title', $logArray);
        $this->assertArrayNotHasKey('body', $logArray);
        $this->assertArrayNotHasKey('message', $logArray);
        $this->assertArrayNotHasKey('content', $logArray);

        // Verify JSON metadata does not contain sensitive text
        $metadataJson = json_encode($log->metadata);
        $this->assertStringNotContainsString($secretTitle, $metadataJson);
        $this->assertStringNotContainsString($secretBody, $metadataJson);
    }

    /**
     * 7. Web UI & Settings Controller endpoints.
     */
    public function test_notifications_page_and_settings_filtering_and_preferences(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Asia/Riyadh',
            'quiet_hours_enabled' => true,
            'push_opt_in' => true,
        ]);

        // Seed varied notifications
        $this->notificationService->send($user, [
            'title' => 'إعارة كتاب البداية والنهاية',
            'body' => 'تفاصيل الإعارة',
            'category' => UserNotification::CATEGORY_LOANS,
            'priority' => UserNotification::PRIORITY_NORMAL,
        ]);
        $this->notificationService->send($user, [
            'title' => 'تحدي المعرفة الرمضاني',
            'body' => 'تفاصيل التحدي',
            'category' => UserNotification::CATEGORY_CHALLENGES,
            'priority' => UserNotification::PRIORITY_HIGH,
        ]);

        // Visit notifications page
        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertOk();
        $response->assertSee('الإشعارات');
        $response->assertSee('إعارة كتاب البداية والنهاية');
        $response->assertSee('تحدي المعرفة الرمضاني');
        $response->assertSee('إشعارات الويب (Push): مفعّلة');

        // Filter by category=loans
        $filterLoans = $this->actingAs($user)->get(route('notifications.index', ['category' => 'loans']));
        $filterLoans->assertOk();
        $filterLoans->assertSee('إعارة كتاب البداية والنهاية');
        $filterLoans->assertDontSee('تحدي المعرفة الرمضاني');

        // Visit settings page
        $settingsPage = $this->actingAs($user)->get(route('settings.index'));
        $settingsPage->assertOk();
        $settingsPage->assertSee('ساعات الهدوء والسكينة الليلية');
        $settingsPage->assertSee('المنطقة الزمنية المحلية');
        $settingsPage->assertSee('إشعارات المتصفح الفورية (Web Push Opt-in)');
        $settingsPage->assertSee('تفضيلات القنوات بحسب الفئة');

        // Update settings with timezone and quiet hours
        $postSettings = $this->actingAs($user)->post(route('settings.update'), [
            'name' => 'قارئ جديد',
            'annual_goal' => 30,
            'timezone' => 'Asia/Dubai',
            'quiet_hours_enabled' => 1,
            'quiet_hours_start' => '23:00',
            'quiet_hours_end' => '07:00',
            'push_opt_in' => 1,
            'preferences' => [
                'communities' => ['in_app' => 1, 'push' => 0],
                'challenges' => ['in_app' => 1, 'push' => 1],
            ],
        ]);

        $postSettings->assertRedirect();
        $user->refresh();
        $this->assertEquals('Asia/Dubai', $user->timezone);
        $this->assertEquals('23:00', $user->quiet_hours_start);
        $this->assertEquals('07:00', $user->quiet_hours_end);
        $this->assertFalse($user->wantsNotification('communities', 'push'));
        $this->assertTrue($user->wantsNotification('challenges', 'push'));
    }
}
