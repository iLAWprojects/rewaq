<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Book;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\Discussion;
use App\Models\Friendship;
use App\Models\PointTransaction;
use App\Models\SocialActivity;
use App\Models\User;
use App\Services\AntiGamingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqFriendshipSocialAndGamificationEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1. Friend Request Lifecycle: Pending -> Accepted / Rejected
     */
    public function test_friend_request_lifecycle_pending_and_acceptance()
    {
        $alice = User::factory()->create(['name' => 'أليس القارئة']);
        $bob = User::factory()->create(['name' => 'باسم الباحث']);

        // Prevent self-request
        $this->actingAs($alice)->post(route('friends.request', $alice->id))
            ->assertSessionHas('error');

        // Alice sends friend request to Bob
        $response = $this->actingAs($alice)->post(route('friends.request', $bob->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('friendships', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        $this->assertTrue($alice->friendshipWith($bob)->isPending());
        $this->assertEquals(0, $alice->friendsCount());
        $this->assertEquals(0, $bob->friendsCount());

        // Bob accepts friend request
        $acceptResponse = $this->actingAs($bob)->post(route('friends.accept', $alice->id));
        $acceptResponse->assertSessionHas('success');

        $this->assertDatabaseHas('friendships', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);

        // Mutual friendship and mutual follow verified
        $this->assertEquals(1, $alice->friendsCount());
        $this->assertEquals(1, $bob->friendsCount());
        $this->assertTrue($alice->isFollowing($bob));
        $this->assertTrue($bob->isFollowing($alice));

        // Curated social activity logged
        $this->assertDatabaseHas('social_activities', [
            'user_id' => $bob->id,
            'activity_type' => SocialActivity::TYPE_FRIEND_CONNECTED,
        ]);
    }

    public function test_friend_request_rejection()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->post(route('friends.request', $bob->id));

        $rejectResponse = $this->actingAs($bob)->post(route('friends.reject', $alice->id));
        $rejectResponse->assertSessionHas('info');

        $this->assertDatabaseHas('friendships', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => Friendship::STATUS_REJECTED,
        ]);

        $this->assertEquals(0, $alice->friendsCount());
    }

    public function test_unfriend_action()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->post(route('friends.request', $bob->id));
        $this->actingAs($bob)->post(route('friends.accept', $alice->id));

        $this->assertEquals(1, $alice->friendsCount());

        // Alice unfriends Bob
        $response = $this->actingAs($alice)->post(route('friends.unfriend', $bob->id));
        $response->assertSessionHas('info');

        $this->assertDatabaseMissing('friendships', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
        ]);

        $this->assertEquals(0, $alice->friendsCount());
        $this->assertEquals(0, $bob->friendsCount());
    }

    /**
     * 2. User Block Mechanics
     */
    public function test_user_block_and_unblock_lifecycle()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Establish friendship and follows first
        $alice->sendFriendRequest($bob);
        $bob->acceptFriendRequest($alice);

        $this->assertTrue($alice->isFollowing($bob));
        $this->assertTrue($bob->isFollowing($alice));
        $this->assertEquals(1, $alice->friendsCount());

        // Alice blocks Bob
        $blockResponse = $this->actingAs($alice)->post(route('friends.block', $bob->id), [
            'reason' => 'محتوى غير لائق',
        ]);
        $blockResponse->assertSessionHas('warning');

        $this->assertTrue($alice->hasBlocked($bob));
        $this->assertTrue($bob->isBlockedBy($alice));
        $this->assertTrue($alice->isBlockedWith($bob));

        // Friendship and follow connections severed
        $this->assertFalse($alice->isFollowing($bob));
        $this->assertFalse($bob->isFollowing($alice));
        $this->assertEquals(0, $alice->friendsCount());

        // Blocked users cannot send friend requests
        $reqResponse = $this->actingAs($bob)->post(route('friends.request', $alice->id));
        $reqResponse->assertSessionHas('error');

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $alice->id,
            'action' => 'user_blocked',
        ]);

        // Alice unblocks Bob
        $unblockResponse = $this->actingAs($alice)->post(route('friends.unblock', $bob->id));
        $unblockResponse->assertSessionHas('success');

        $this->assertFalse($alice->hasBlocked($bob));
        $this->assertFalse($alice->isBlockedWith($bob));
    }

    /**
     * 3. Curated Social Activities & Privacy Preferences
     */
    public function test_curated_social_activities_and_privacy_filtering()
    {
        $alice = User::factory()->create();
        $viewer = User::factory()->create();

        // Log curated activities
        $curatedActivity = SocialActivity::log(
            $alice,
            SocialActivity::TYPE_BOOK_COMPLETED,
            'أتمّ قراءة كتاب مقدمة ابن خلدون',
            'إنجاز معرفي رصين'
        );
        $this->assertNotNull($curatedActivity);

        // Spam or uncurated event is rejected
        $spamActivity = SocialActivity::log(
            $alice,
            'random_spam_action',
            'عنوان عشوائي',
            'تفاصيل'
        );
        $this->assertNull($spamActivity);

        // User hides reading session events
        $this->actingAs($alice)->post(route('profile.updateActivityPrivacy'), [
            'hidden_types' => [SocialActivity::TYPE_READING_SESSION],
        ])->assertSessionHas('success');

        $alice->refresh();
        $this->assertTrue($alice->hasHiddenActivityType(SocialActivity::TYPE_READING_SESSION));

        // Create a reading session activity for Alice
        $sessionActivity = SocialActivity::log(
            $alice,
            SocialActivity::TYPE_READING_SESSION,
            'جلسة قراءة مسائية',
            'قرأ 30 صفحة'
        );
        $this->assertNotNull($sessionActivity);

        // For outside viewer, the hidden activity should NOT be visible
        $visibleActivities = SocialActivity::visibleForViewer($viewer)->where('user_id', $alice->id)->get();
        $this->assertFalse($visibleActivities->contains($sessionActivity));
        $this->assertTrue($visibleActivities->contains($curatedActivity));

        // For Alice herself, all her activities remain accessible
        $ownActivities = SocialActivity::visibleForViewer($alice)->where('user_id', $alice->id)->get();
        $this->assertTrue($ownActivities->contains($sessionActivity));
    }

    public function test_blocked_user_activities_are_completely_hidden_from_viewer()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $activity = SocialActivity::log(
            $bob,
            SocialActivity::TYPE_BOOK_COMPLETED,
            'أتمّ قراءة كتاب البخلاء',
            'أدب عربي'
        );

        // Alice blocks Bob
        $alice->blockUser($bob);

        $visibleActivities = SocialActivity::visibleForViewer($alice)->where('user_id', $bob->id)->get();
        $this->assertEmpty($visibleActivities);
    }

    /**
     * 4. Non-tamperable Ledger Transactions & Dynamic Level Calculation
     */
    public function test_ledger_transactions_and_level_derivation()
    {
        $user = User::factory()->create(['points' => 0, 'level' => 1]);

        $this->assertEquals(0, $user->validPointsTotal());
        $this->assertEquals(1, $user->calculateLevel());

        // Add verified points transaction
        $user->addPoints(150, 'bonus', 'مكافأة معرفية');
        $user->refresh();

        $this->assertEquals(150, $user->validPointsTotal());
        $this->assertEquals(2, $user->calculateLevel()); // Level 2: >= 100
        $this->assertEquals(2, $user->level);

        // Add invalid / flagged transaction (e.g. anti-gaming flag)
        PointTransaction::create([
            'user_id' => $user->id,
            'points' => 500,
            'is_valid' => false,
            'status' => PointTransaction::STATUS_FLAGGED,
            'activity_type' => 'suspicious_activity',
            'description' => 'حركة غير مؤكدة',
        ]);

        // Valid points and level MUST ignore invalid transactions
        $this->assertEquals(150, $user->validPointsTotal());
        $this->assertEquals(2, $user->calculateLevel());

        // Level 3 at 300, Level 4 at 600, Level 5 at 1000
        // Move to next day to avoid daily cap for level test
        Carbon::setTestNow(now()->addDays(2));

        $user->addPoints(200, 'bonus', 'مكافأة موسمية');
        $user->refresh();
        $this->assertEquals(350, $user->validPointsTotal());
        $this->assertEquals(3, $user->calculateLevel());

        Carbon::setTestNow(now()->addDays(3));
        $user->addPoints(250, 'bonus', 'مكافأة');
        $user->refresh();
        $this->assertEquals(600, $user->validPointsTotal());
        $this->assertEquals(4, $user->calculateLevel());

        Carbon::setTestNow(now()->addDays(4));
        $user->addPoints(250, 'bonus', 'مكافأة كبرى');
        Carbon::setTestNow(now()->addDays(5));
        $user->addPoints(200, 'bonus', 'مكافأة نهائية');
        $user->refresh();
        $this->assertEquals(1050, $user->validPointsTotal());
        $this->assertEquals(5, $user->calculateLevel());
    }

    /**
     * 5. Anti-Gaming Rules: Daily Caps, Reading Speed Anomalies & Duplicate Prevention
     */
    public function test_anti_gaming_daily_activity_caps()
    {
        $user = User::factory()->create(['points' => 0]);
        $antiGaming = app(AntiGamingService::class);

        // Activity cap for reading session is 200 points/day
        $cap1 = $antiGaming->checkDailyCap($user, 'reading_session', 150);
        $this->assertEquals(150, $cap1['allowed_points']);
        $this->assertFalse($cap1['capped']);

        $user->addPoints(150, 'reading_session', 'جلسة أولى');

        // Next request for 80 points exceeds 200 cap (only 50 allowed)
        $cap2 = $antiGaming->checkDailyCap($user, 'reading_session', 80);
        $this->assertEquals(50, $cap2['allowed_points']);
        $this->assertTrue($cap2['capped']);

        $user->addPoints(50, 'reading_session', 'جلسة ثانية');

        // Third request is completely blocked for reading_session
        $cap3 = $antiGaming->checkDailyCap($user, 'reading_session', 15);
        $this->assertEquals(0, $cap3['allowed_points']);
        $this->assertTrue($cap3['capped']);
    }

    public function test_anti_gaming_reading_speed_anomaly_detection()
    {
        $user = User::factory()->create();
        $antiGaming = app(AntiGamingService::class);

        // Realistic reading: 20 pages in 30 minutes (0.67 pages/min)
        $validCheck = $antiGaming->verifyReadingSession($user, 30, 20);
        $this->assertTrue($validCheck['is_valid']);
        $this->assertFalse($validCheck['anomalous']);

        // Anomalous reading: 200 pages in 10 minutes (20 pages/min > 3.0 limit)
        $anomalyCheck = $antiGaming->verifyReadingSession($user, 10, 200);
        $this->assertFalse($anomalyCheck['is_valid']);
        $this->assertTrue($anomalyCheck['anomalous']);

        // Audit log created for anomaly
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'anomalous_activity_detected',
        ]);
    }

    public function test_anti_gaming_duplicate_book_finished_in_same_day()
    {
        $user = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();
        $antiGaming = app(AntiGamingService::class);

        $firstCheck = $antiGaming->verifyBookFinished($user, $book);
        $this->assertTrue($firstCheck['is_valid']);

        $user->addPoints(100, 'book_finished', "إتمام كتاب «{$book->title}»", Book::class, $book->id);

        $secondCheck = $antiGaming->verifyBookFinished($user, $book);
        $this->assertFalse($secondCheck['is_valid']);
        $this->assertTrue($secondCheck['duplicate']);
    }

    /**
     * 6. Rule-Based Platform Badges and Automatic/Manual Community Badges
     */
    public function test_rule_based_platform_badge_auto_awarding()
    {
        $user = User::factory()->create(['points' => 0, 'books_finished' => 5]);

        $badge = Badge::create([
            'name' => 'خبير الخماسية القرائية',
            'description' => 'إنهاء 5 كتب كاملة',
            'icon' => 'book-check',
            'category' => 'books',
            'points_required' => 50,
            'rule_type' => Badge::RULE_BOOKS_COUNT,
            'rule_threshold' => 5,
        ]);

        $this->assertFalse($user->badges->contains($badge));

        // Evaluate and award rule-based badges
        $user->checkAndAwardBadges();

        $this->assertTrue($user->fresh()->badges->contains($badge));

        // Curated activity created
        $this->assertDatabaseHas('social_activities', [
            'user_id' => $user->id,
            'activity_type' => SocialActivity::TYPE_BADGE_EARNED,
        ]);
    }

    public function test_community_badges_manual_and_automatic_awarding()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $community = Community::first() ?? Community::create([
            'name' => 'مجلس حكمة الرواق',
            'type' => 'open',
            'created_by' => $admin->id,
        ]);

        // 1. Manual Community Badge
        $manualBadge = CommunityBadge::create([
            'community_id' => $community->id,
            'name' => 'فارس الحوار',
            'description' => 'تميز ملحوظ في إثراء نقاشات المجلس',
            'award_type' => CommunityBadge::AWARD_MANUAL,
        ]);

        $this->assertTrue($manualBadge->isManual());
        $manualBadge->awardToUser($member, $admin, 'تقديراً لمشاركاته النيرة');

        $this->assertTrue($member->fresh()->communityBadges->contains($manualBadge));

        // 2. Automatic Community Badge
        $autoBadge = CommunityBadge::create([
            'community_id' => $community->id,
            'name' => 'مبادر المجلس',
            'description' => 'طرح نقاشين أو أكثر في المجتمع',
            'award_type' => CommunityBadge::AWARD_AUTOMATIC,
            'auto_rule_type' => CommunityBadge::RULE_DISCUSSIONS_COUNT,
            'auto_rule_threshold' => 2,
        ]);

        $this->assertTrue($autoBadge->isAutomatic());
        $this->assertFalse($autoBadge->evaluateRule($member));

        // Member creates 2 discussions
        Discussion::create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'title' => 'نقاش فلسفي 1',
            'content' => 'محتوى أول',
        ]);
        Discussion::create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'title' => 'نقاش فلسفي 2',
            'content' => 'محتوى ثاني',
        ]);

        $this->assertTrue($autoBadge->evaluateRule($member));
        $autoBadge->awardToUser($member, null, 'استحقاق تلقائي لتجاوز عتبة النقاشات');

        $this->assertTrue($member->fresh()->communityBadges->contains($autoBadge));
    }

    /**
     * 7. Profile Blade View Tests: Friendship Actions & Modals
     */
    public function test_profile_page_displays_friendship_actions_and_modals()
    {
        $owner = User::factory()->create(['name' => 'صاحب الملف']);
        $requester = User::factory()->create(['name' => 'مرسل الطلب']);

        // Requester sends friend request to Owner
        $requester->sendFriendRequest($owner);

        // Owner visits own profile
        $response = $this->actingAs($owner)->get(route('profile.show'));
        $response->assertStatus(200);
        $response->assertSee('إدارة الصداقات والطلبات');
        $response->assertSee('خصوصية الأنشطة');
        $response->assertSee('مرسل الطلب');
        $response->assertSee('قبول 🤝');

        // Requester visits Owner's profile -> sees pending outgoing status
        $visitorResponse = $this->actingAs($requester)->get(route('profile.show', $owner->id));
        $visitorResponse->assertStatus(200);
        $visitorResponse->assertSee('طلب صداقة مُرسل ⏳');
    }

    public function test_profile_page_handles_blocked_user_gracefully()
    {
        $alice = User::factory()->create(['name' => 'المستخدم المحظور']);
        $bob = User::factory()->create(['name' => 'المستخدم الحاجب']);

        // Bob blocks Alice
        $bob->blockUser($alice);

        // Alice visits Bob's profile -> sees blocked restriction notice
        $response = $this->actingAs($alice)->get(route('profile.show', $bob->id));
        $response->assertStatus(200);
        $response->assertSee('الوصول إلى الأنشطة والمعالم المعرفية مقيّد');

        // Bob visits Alice's profile -> sees unblock action
        $bobResponse = $this->actingAs($bob)->get(route('profile.show', $alice->id));
        $bobResponse->assertStatus(200);
        $bobResponse->assertSee('إلغاء حظر المستخدم');
    }
}
