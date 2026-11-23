<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Challenge;
use App\Models\ChallengeProgressLog;
use App\Models\ChallengeTeam;
use App\Models\Community;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReadingChallengesAndTeamsEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $creator;

    protected User $readerA;

    protected User $readerB;

    protected User $readerC;

    protected Community $community;

    protected Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create([
            'name' => 'قائد التحديات',
            'email' => 'challenge_leader@riwaq.test',
            'points' => 500,
        ]);

        $this->readerA = User::factory()->create([
            'name' => 'فهد القارئ',
            'email' => 'fahad@riwaq.test',
            'points' => 120,
        ]);

        $this->readerB = User::factory()->create([
            'name' => 'سارة الباحثة',
            'email' => 'sara@riwaq.test',
            'points' => 350,
        ]);

        $this->readerC = User::factory()->create([
            'name' => 'عمر الناقد',
            'email' => 'omar@riwaq.test',
            'points' => 80,
        ]);

        $this->community = Community::create([
            'name' => 'مجلس رواد الفكر والبيان',
            'category' => 'فكر وفلسفة',
            'creator_id' => $this->creator->id,
            'type' => 'public',
        ]);

        $this->book = Book::first() ?? Book::create([
            'title' => 'مقدمة ابن خلدون',
            'author' => 'ابن خلدون',
            'category' => 'تاريخ وفكر',
            'total_pages' => 600,
        ]);
    }

    /**
     * 1. التحقق من دعم أنواع التحديات الـ 13 ومسمياتها وتخزينها
     */
    public function test_all_thirteen_challenge_types_are_supported(): void
    {
        $types = [
            Challenge::TYPE_BOOKS_COUNT,
            Challenge::TYPE_PAGES_COUNT,
            Challenge::TYPE_HOURS_COUNT,
            Challenge::TYPE_DAILY_STREAK,
            Challenge::TYPE_SPECIFIC_BOOK,
            Challenge::TYPE_DIVERSE_CATEGORIES,
            Challenge::TYPE_DIVERSE_AUTHORS,
            Challenge::TYPE_ARABIC_LITERATURE,
            Challenge::TYPE_SEASONAL,
            Challenge::TYPE_COMMUNITY_VS_COMMUNITY,
            Challenge::TYPE_INTERNAL_TEAMS,
            Challenge::TYPE_BOOK_SERIES,
            Challenge::TYPE_KNOWLEDGE_DIVERSITY,
        ];

        $this->assertCount(13, $types);

        foreach ($types as $type) {
            $ch = Challenge::create([
                'title' => "تحدي تجريبي {$type}",
                'description' => "وصف تجريبي لنوع التحدي {$type}",
                'challenge_type' => $type,
                'target_count' => 10,
                'target_unit' => 'وحدات',
                'status' => Challenge::STATUS_ACTIVE,
                'creator_id' => $this->creator->id,
            ]);

            $this->assertEquals($type, $ch->challenge_type);
            $this->assertNotEmpty($ch->challenge_type_label);
        }
    }

    /**
     * 2. التحقق من دعم الحالات الست لدورة حياة التحدي
     */
    public function test_all_six_challenge_statuses_are_supported_with_scopes(): void
    {
        $statuses = [
            Challenge::STATUS_DRAFT,
            Challenge::STATUS_UPCOMING,
            Challenge::STATUS_ACTIVE,
            Challenge::STATUS_COMPLETED,
            Challenge::STATUS_CANCELLED,
            Challenge::STATUS_ARCHIVED,
        ];

        $this->assertCount(6, $statuses);

        foreach ($statuses as $st) {
            $challenge = Challenge::create([
                'title' => "تحدي بحالة {$st}",
                'description' => 'وصف تجريبي للحالة',
                'status' => $st,
                'target_count' => 100,
                'creator_id' => $this->creator->id,
            ]);

            $this->assertEquals($st, $challenge->status);
            $this->assertNotEmpty($challenge->status_label);
        }

        // Test Scopes
        $activeCh = Challenge::create([
            'title' => 'نشط Scope',
            'description' => 'وصف',
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 50,
        ]);

        $upcomingCh = Challenge::create([
            'title' => 'قادم Scope',
            'description' => 'وصف',
            'status' => Challenge::STATUS_UPCOMING,
            'target_count' => 50,
        ]);

        $this->assertTrue(Challenge::active()->where('id', $activeCh->id)->exists());
        $this->assertTrue(Challenge::upcoming()->where('id', $upcomingCh->id)->exists());
    }

    /**
     * 3. تأسيس التحدي وتخزين بياناته (الوحدة، طريقة التوزيع، المكافأة، الكتاب، إلخ)
     */
    public function test_challenge_creation_endpoint_stores_all_parameters(): void
    {
        $response = $this->actingAs($this->creator)->post(route('challenges.store'), [
            'title' => 'ماراثون الأدب الأندلسي',
            'description' => 'قراءة 800 صفحة في عيون الأدب الأندلسي والتاريخ المغربي.',
            'challenge_type' => Challenge::TYPE_ARABIC_LITERATURE,
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 800,
            'target_unit' => 'صفحات',
            'team_assignment_method' => Challenge::ASSIGNMENT_USER_CHOICE,
            'is_team' => 1,
            'team_a_name' => 'فرسان قرطبة',
            'team_b_name' => 'رواد إشبيلية',
            'reward_badge' => 'وسام وشاح الأندلس',
            'reward_points' => 250,
            'community_id' => $this->community->id,
            'specific_book_id' => $this->book->id,
        ]);

        $response->assertSessionHas('success');

        $challenge = Challenge::where('title', 'ماراثون الأدب الأندلسي')->first();
        $this->assertNotNull($challenge);
        $this->assertEquals(Challenge::TYPE_ARABIC_LITERATURE, $challenge->challenge_type);
        $this->assertEquals(800, $challenge->target_count);
        $this->assertEquals('صفحات', $challenge->target_unit);
        $this->assertEquals(250, $challenge->reward_points);
        $this->assertEquals($this->creator->id, $challenge->creator_id);
        $this->assertTrue($challenge->is_team);

        // Verify that teams were automatically initialized
        $this->assertCount(2, $challenge->teams);
        $this->assertTrue($challenge->teams->pluck('name')->contains('فرسان قرطبة'));
        $this->assertTrue($challenge->teams->pluck('name')->contains('رواد إشبيلية'));
    }

    /**
     * 4. تتبع المشاركين: المستخدم، تاريخ الانضمام، التقدم، النقاط، الترتيب
     */
    public function test_participant_tracking_includes_joined_at_points_and_rank(): void
    {
        $challenge = Challenge::create([
            'title' => 'تحدي المتسابقين',
            'description' => 'تحدي قياس المشاركين والترتيب',
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 300,
            'target_unit' => 'صفحة',
        ]);

        // Reader A joins
        $this->actingAs($this->readerA)->post(route('challenges.join', $challenge->id));

        $participant = $challenge->participants()->where('user_id', $this->readerA->id)->first();
        $this->assertNotNull($participant);
        $this->assertNotNull($participant->pivot->joined_at);
        $this->assertEquals(0, $participant->pivot->progress_count);
        $this->assertEquals(0, $participant->pivot->points_earned);
        $this->assertNotNull($participant->pivot->rank);
    }

    /**
     * 5. تسجيل التقدم يكون Event/Transaction-based وليس مجرد رقم نهائي متغير، لحفظ أثر التدقيق
     */
    public function test_event_transaction_based_progress_logging_creates_immutable_audit_trail(): void
    {
        $challenge = Challenge::create([
            'title' => 'تحدي التدقيق المعرفي',
            'description' => 'تحدي توثيق المعاملات وأثر التدقيق',
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 500,
            'target_unit' => 'صفحة',
            'reward_points' => 150,
        ]);

        // Transaction 1: Read 50 pages
        $log1 = $challenge->logUserProgress(
            $this->readerA,
            50,
            'pages_read',
            'قراءة الفصل الأول من مقدمة ابن خلدون',
            $this->book->id
        );

        $this->assertDatabaseHas('challenge_progress_logs', [
            'id' => $log1->id,
            'challenge_id' => $challenge->id,
            'user_id' => $this->readerA->id,
            'increment_value' => 50,
            'progress_before' => 0,
            'progress_after' => 50,
            'event_type' => 'pages_read',
            'notes' => 'قراءة الفصل الأول من مقدمة ابن خلدون',
        ]);

        // Transaction 2: Read another 70 pages
        $log2 = $challenge->logUserProgress(
            $this->readerA,
            70,
            'pages_read',
            'إتمام الباب الثاني'
        );

        $this->assertEquals(50, $log2->progress_before);
        $this->assertEquals(120, $log2->progress_after);
        $this->assertEquals(70, $log2->increment_value);

        // Verify total logs for user in challenge
        $logsCount = ChallengeProgressLog::where('challenge_id', $challenge->id)
            ->where('user_id', $this->readerA->id)
            ->count();
        $this->assertEquals(2, $logsCount);

        // Verify cumulative participant progress in pivot
        $p = $challenge->participants()->where('user_id', $this->readerA->id)->first();
        $this->assertEquals(120, $p->pivot->progress_count);
        $this->assertGreaterThan(0, $p->pivot->points_earned);
    }

    /**
     * 6. الفرق: اسم، لون/شعار، قائد، أعضاء، إجماليات، ترتيب
     */
    public function test_challenge_teams_structure_totals_and_ranking(): void
    {
        $challenge = Challenge::create([
            'title' => 'معركة الفرق الكبرى',
            'description' => 'تنافس بين فريقين',
            'status' => Challenge::STATUS_ACTIVE,
            'is_team' => true,
            'target_count' => 1000,
        ]);

        $teamA = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => 'صقور اليمامة',
            'color' => '#173D36',
            'badge_icon' => 'shield',
            'leader_id' => $this->readerA->id,
        ]);

        $teamB = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => 'نسور طويق',
            'color' => '#C3A15D',
            'badge_icon' => 'flame',
            'leader_id' => $this->readerB->id,
        ]);

        // Assign Reader A to Team A and log 200 pages
        $challenge->assignUserToTeam($this->readerA, $teamA);
        $challenge->logUserProgress($this->readerA, 200);

        // Assign Reader B to Team B and log 350 pages
        $challenge->assignUserToTeam($this->readerB, $teamB);
        $challenge->logUserProgress($this->readerB, 350);

        $teamA->refresh();
        $teamB->refresh();

        $this->assertEquals(1, $teamA->members_count);
        $this->assertEquals(200, $teamA->total_progress);

        $this->assertEquals(1, $teamB->members_count);
        $this->assertEquals(350, $teamB->total_progress);

        // Verify ranks: Team B has 350 pages so rank 1, Team A rank 2
        $this->assertEquals(1, $teamB->rank);
        $this->assertEquals(2, $teamA->rank);
    }

    /**
     * 7. طرق توزيع الفرق: يدوي، عشوائي، حسب المستوى، اختيار المستخدم
     */
    public function test_all_four_team_assignment_strategies(): void
    {
        $challenge = Challenge::create([
            'title' => 'تحدي توزيع الفرق المتعدد',
            'description' => 'اختبار استراتيجيات التوزيع',
            'status' => Challenge::STATUS_ACTIVE,
            'is_team' => true,
            'target_count' => 1000,
        ]);

        $team1 = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => 'فريق الألفا',
            'color' => '#173D36',
        ]);

        $team2 = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => 'فريق البيتا',
            'color' => '#C3A15D',
        ]);

        // Strategy A: User Choice
        $challenge->team_assignment_method = Challenge::ASSIGNMENT_USER_CHOICE;
        $assigned1 = $challenge->assignUserToTeam($this->readerA, $team2);
        $this->assertEquals($team2->id, $assigned1->id);

        // Strategy B: Random (balanced assignment)
        $challenge->team_assignment_method = Challenge::ASSIGNMENT_RANDOM;
        $assigned2 = $challenge->assignUserToTeam($this->readerB);
        // Team 1 had 0 members, Team 2 had 1 member -> Random must pick Team 1
        $this->assertEquals($team1->id, $assigned2->id);

        // Strategy C: By Level (balances total points/strength)
        // Set team1 total_points high, team2 low
        $team1->update(['total_points' => 900]);
        $team2->update(['total_points' => 100]);

        $challenge->team_assignment_method = Challenge::ASSIGNMENT_BY_LEVEL;
        $assigned3 = $challenge->assignUserToTeam($this->readerC);
        // Team 2 has fewer points -> assigned to Team 2
        $this->assertEquals($team2->id, $assigned3->id);

        // Strategy D: Manual
        $challenge->team_assignment_method = Challenge::ASSIGNMENT_MANUAL;
        $assignedManual = $challenge->assignUserToTeam($this->creator, $team1);
        $this->assertEquals($team1->id, $assignedManual->id);
    }

    /**
     * 8. إشعار تحقيق مرحلة (Milestone Reached: 25%, 50%, 75%, 100%)
     */
    public function test_milestone_reached_notifications(): void
    {
        $challenge = Challenge::create([
            'title' => 'تحدي المراحل الخمسين',
            'description' => 'تحدي قياس الإشعارات المرحلية',
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 100, // 100 pages
            'target_unit' => 'صفحة',
        ]);

        // Log 30 pages -> reaches 30% (crosses 25% milestone)
        $challenge->logUserProgress($this->readerA, 30);

        $notif25 = UserNotification::where('user_id', $this->readerA->id)
            ->where('type', 'challenge_milestone')
            ->first();

        $this->assertNotNull($notif25);
        $this->assertStringContainsString('25%', $notif25->title);

        // Log 25 more pages -> total 55% (crosses 50% milestone)
        $challenge->logUserProgress($this->readerA, 25);

        $notif50 = UserNotification::where('user_id', $this->readerA->id)
            ->where('type', 'challenge_milestone')
            ->where('title', 'like', '%50%%')
            ->first();

        $this->assertNotNull($notif50);
    }

    /**
     * 9. إشعار تغيير المركز والترتيب (Rank Changed)
     */
    public function test_rank_changed_notification_on_leaderboard_promotion(): void
    {
        $challenge = Challenge::create([
            'title' => 'سباق القمة',
            'description' => 'تحدي سرعة الترتيب',
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 500,
        ]);

        // Reader B starts in 1st place with 100 pages
        $challenge->logUserProgress($this->readerB, 100);

        // Reader A starts in 2nd place with 20 pages
        $challenge->logUserProgress($this->readerA, 20);

        $pA = $challenge->participants()->where('user_id', $this->readerA->id)->first();
        $this->assertEquals(2, $pA->pivot->rank);

        // Reader A logs 150 pages -> now total 170 pages -> takes 1st place!
        $challenge->logUserProgress($this->readerA, 150);

        $rankNotif = UserNotification::where('user_id', $this->readerA->id)
            ->where('type', 'challenge_rank_changed')
            ->first();

        $this->assertNotNull($rankNotif);
        $this->assertStringContainsString('ارتقيت في ترتيب التحدي', $rankNotif->title);
        $this->assertStringContainsString('(1)', $rankNotif->body);
    }

    /**
     * 10. إشعار قرب انتهاء التحدي (Ending Soon Notification)
     */
    public function test_ending_soon_notification_for_active_challenges(): void
    {
        $challenge = Challenge::create([
            'title' => 'تحدي الأيام الأخيرة',
            'description' => 'تحدي يختتم غداً',
            'status' => Challenge::STATUS_ACTIVE,
            'target_count' => 300,
            'end_date' => now()->addHours(24)->toDateString(),
        ]);

        // Attach participant without completion
        $challenge->participants()->attach($this->readerA->id, [
            'progress_count' => 50,
            'completed' => false,
        ]);

        $notified = $challenge->checkAndNotifyEndingSoon();
        $this->assertGreaterThanOrEqual(1, $notified);

        $endingNotif = UserNotification::where('user_id', $this->readerA->id)
            ->where('type', 'challenge_ending_soon')
            ->first();

        $this->assertNotNull($endingNotif);
        $this->assertStringContainsString('أوشك التحدي على الانتهاء', $endingNotif->title);
    }

    /**
     * 11. إشعار الفوز بالتحدي للمستخدم والفريق الفائز (Challenge Won Notification)
     */
    public function test_challenge_completion_and_victory_notifications(): void
    {
        $challenge = Challenge::create([
            'title' => 'نهائي بطولة القراءة الكبرى',
            'description' => 'المباراة النهائية',
            'status' => Challenge::STATUS_ACTIVE,
            'is_team' => true,
            'target_count' => 100,
        ]);

        $teamWinner = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => 'فريق الأبطال',
            'color' => '#173D36',
        ]);

        $teamRunnerUp = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => 'فريق المتحدين',
            'color' => '#C3A15D',
        ]);

        $challenge->assignUserToTeam($this->readerA, $teamWinner);
        $challenge->logUserProgress($this->readerA, 100);

        $challenge->assignUserToTeam($this->readerB, $teamRunnerUp);
        $challenge->logUserProgress($this->readerB, 40);

        // Complete and finalize winners
        $results = $challenge->completeAndFinalizeWinners();

        $this->assertEquals(Challenge::STATUS_COMPLETED, $challenge->status);
        $this->assertEquals($this->readerA->id, $challenge->winner_user_id);
        $this->assertEquals($teamWinner->id, $challenge->winner_team_id);

        // Winner individual notification
        $winUserNotif = UserNotification::where('user_id', $this->readerA->id)
            ->where('type', 'challenge_won')
            ->where('title', 'like', '%المركز الأول%')
            ->first();
        $this->assertNotNull($winUserNotif);

        // Winning team notification
        $winTeamNotif = UserNotification::where('user_id', $this->readerA->id)
            ->where('type', 'challenge_won')
            ->where('title', 'like', '%ظفر بالبطولة%')
            ->first();
        $this->assertNotNull($winTeamNotif);
    }

    /**
     * 12. التأكد من معالجة الفلاتر التي لا تطابق أي تحدي نشط دون حدوث خطأ 500 أو قراءة id على null
     */
    public function test_challenges_page_handles_missing_or_unmatched_type_filter_without_null_pointer_error(): void
    {
        // 1. زيارة الصفحة بنوع تحدي غير موجود (type=pages_count) لمستخدم مسجل
        $response = $this->actingAs($this->readerA)->get('/challenges?tab=active&type=pages_count');
        $response->assertStatus(200);
        $response->assertSee('لا توجد تحديات نشطة تحت تصنيف');
        $response->assertSee('عدد صفحات');

        // 2. زيارة الصفحة كزائر غير مسجل (Guest)
        $guestResponse = $this->get('/challenges?tab=active&type=pages_count');
        $guestResponse->assertStatus(200);
        $guestResponse->assertSee('لا توجد تحديات نشطة تحت تصنيف');
    }
}
