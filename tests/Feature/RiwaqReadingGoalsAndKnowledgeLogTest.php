<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReview;
use App\Models\Community;
use App\Models\Discussion;
use App\Models\ReadingSession;
use App\Models\Reflection;
use App\Models\User;
use App\Models\UserCurrentBook;
use App\Models\YearlyReadingGoal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReadingGoalsAndKnowledgeLogTest extends TestCase
{
    use DatabaseTransactions;

    protected function getDemoUser(): User
    {
        return User::where('email', 'demo@riwaq.test')->first()
            ?? User::factory()->create(['email' => 'demo@riwaq.test', 'name' => 'قارئ تجريبي']);
    }

    protected function getSecondUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'reader2@riwaq.test'],
            ['name' => 'قارئ ثانٍ', 'password' => bcrypt('password')]
        );
    }

    /**
     * 1. حالات الكتاب الخمسة للمستخدم وتسجيل الصفحة ونسبة التقدم وتواريخ البداية والإنهاء
     */
    public function test_user_can_update_book_reading_status_across_all_five_states(): void
    {
        $user = $this->getDemoUser();
        $book = Book::first() ?? Book::factory()->create(['total_pages' => 300]);

        $statuses = [
            'want_to_read' => ['page' => 0, 'label' => 'أرغب بقراءته'],
            'currently_reading' => ['page' => 45, 'label' => 'أقرأ الآن'],
            'on_hold' => ['page' => 45, 'label' => 'متوقف / معلّق'],
            'completed' => ['page' => $book->total_pages, 'label' => 'مكتمل'],
            'did_not_finish' => ['page' => 120, 'label' => 'لم أكمله'],
        ];

        foreach ($statuses as $status => $data) {
            $response = $this->actingAs($user)->post('/reading/books/status', [
                'book_id' => $book->id,
                'status' => $status,
                'current_page' => $data['page'],
                'start_date' => '2026-01-10',
                'finish_date' => $status === 'completed' ? '2026-02-15' : null,
                'rating' => 5,
                'notes' => "ملاحظة اختبار لحالة {$status}",
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect();

            $record = UserCurrentBook::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->first();

            $this->assertNotNull($record);
            $this->assertEquals($status, $record->status);
            $this->assertStringContainsString($data['label'], $record->status_label);
            $this->assertEquals('2026-01-10', $record->start_date->format('Y-m-d'));
            $this->assertEquals(5, $record->rating);
            $this->assertStringContainsString($status, $record->notes);

            if ($status === 'completed') {
                $this->assertEquals($book->total_pages, $record->current_page);
                $this->assertEquals(100, $record->progress_percentage);
                $this->assertEquals('2026-02-15', $record->finish_date->format('Y-m-d'));
            }
        }
    }

    /**
     * 2. جلسة القراءة: البداية والنهاية، المدة، الصفحات، الملاحظات، والجهاز
     */
    public function test_user_can_record_reading_session_with_device_pages_and_timings(): void
    {
        $user = $this->getDemoUser();
        $book = Book::first();

        // Ensure active book
        $currentBook = UserCurrentBook::updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $book->id],
            [
                'status' => 'currently_reading',
                'current_page' => 50,
                'is_active' => true,
            ]
        );

        $startedAt = Carbon::now()->subMinutes(40)->format('Y-m-d\TH:i');
        $endedAt = Carbon::now()->format('Y-m-d\TH:i');

        $response = $this->actingAs($user)->post('/reading/sessions', [
            'book_id' => $book->id,
            'start_page' => 50,
            'page' => 85,
            'minutes' => 55,
            'device' => 'قارئ إلكتروني Kindle',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'note' => 'جلسة تركيز عميق مع القارئ الإلكتروني.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/reading');

        $session = ReadingSession::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals(50, $session->start_page);
        $this->assertEquals(85, $session->end_page);
        $this->assertEquals(85, $session->current_page);
        $this->assertEquals(35, $session->pages_read);
        $this->assertEquals(55, $session->minutes);
        $this->assertEquals('قارئ إلكتروني Kindle', $session->device);
        $this->assertEquals('جلسة تركيز عميق مع القارئ الإلكتروني.', $session->note);
        $this->assertTrue($session->isEligibleForStreak());

        // Verify book progress updated
        $currentBook->refresh();
        $this->assertEquals(85, $currentBook->current_page);
    }

    /**
     * 3. قاعدة التحقق المانعة للتلاعب في سلسلة القراءة (Anti-tamper Reading Streak)
     */
    public function test_anti_tamper_streak_enforces_eligibility_criteria_and_prevents_tampering(): void
    {
        $user = $this->getDemoUser();
        $book = Book::first();

        // Session with < 5 minutes and < 2 pages is NOT eligible
        $ineligibleSession = new ReadingSession([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 3,
            'pages_read' => 1,
            'current_page' => 10,
        ]);
        $this->assertFalse($ineligibleSession->isEligibleForStreak());

        // Session with >= 5 minutes IS eligible
        $eligibleSession1 = new ReadingSession([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 15,
            'pages_read' => 1,
            'current_page' => 11,
        ]);
        $this->assertTrue($eligibleSession1->isEligibleForStreak());

        // Session with >= 2 pages IS eligible even if minutes are short
        $eligibleSession2 = new ReadingSession([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 4,
            'pages_read' => 6,
            'current_page' => 17,
        ]);
        $this->assertTrue($eligibleSession2->isEligibleForStreak());

        // Test unbroken calendar day streak calculation
        // Clear past sessions for this test user
        ReadingSession::where('user_id', $user->id)->delete();

        // Day 1: Today
        ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 20,
            'pages_read' => 15,
            'current_page' => 20,
            'device' => 'كتاب ورقي',
            'started_at' => Carbon::today()->addHours(10),
        ]);

        // Day 2: Yesterday
        ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 30,
            'pages_read' => 25,
            'current_page' => 35,
            'device' => 'كتاب ورقي',
            'started_at' => Carbon::yesterday()->addHours(14),
        ]);

        // Second session on yesterday should NOT duplicate streak count
        ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 25,
            'pages_read' => 18,
            'current_page' => 45,
            'device' => 'كتاب ورقي',
            'started_at' => Carbon::yesterday()->addHours(20),
        ]);

        $calculated = $user->calculateReadingStreak();
        $this->assertEquals(2, $calculated);
    }

    /**
     * 4. الهدف السنوي بالكتب والصفحات والساعات، وحفظ السجل المعرفي التاريخي حتى بعد تعديل الهدف
     */
    public function test_annual_reading_goal_preserves_immutable_historical_achievements(): void
    {
        $user = $this->getDemoUser();
        $currentYear = (int) date('Y');

        // Setup historical goal for previous year
        $pastGoal = YearlyReadingGoal::updateOrCreate(
            ['user_id' => $user->id, 'year' => $currentYear - 1],
            [
                'target_books' => 30,
                'target_pages' => 7500,
                'target_hours' => 150,
                'achieved_books' => 28,
                'achieved_pages' => 7200,
                'achieved_minutes' => 8400, // 140 hours
                'knowledge_diversity_score' => 82,
            ]
        );

        // Update current year goal
        $response = $this->actingAs($user)->post('/reading/goal', [
            'goal' => 40,
            'target_pages' => 10000,
            'target_hours' => 200,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Verify current goal updated
        $currentYearGoal = $user->getOrCreateCurrentYearlyGoal($currentYear);
        $this->assertEquals(40, $currentYearGoal->target_books);
        $this->assertEquals(10000, $currentYearGoal->target_pages);
        $this->assertEquals(200, $currentYearGoal->target_hours);

        // Crucial test: historical achievements were completely untouched!
        $pastGoal->refresh();
        $this->assertEquals(30, $pastGoal->target_books);
        $this->assertEquals(28, $pastGoal->achieved_books);
        $this->assertEquals(7200, $pastGoal->achieved_pages);
        $this->assertEquals(8400, $pastGoal->achieved_minutes);
        $this->assertEquals(82, $pastGoal->knowledge_diversity_score);
    }

    /**
     * 5. الاقتباسات الخاصة والمشتركة وفق إعداد الخصوصية
     */
    public function test_quotes_support_privacy_and_private_quotes_are_strictly_hidden_from_others(): void
    {
        $user1 = $this->getDemoUser();
        $user2 = $this->getSecondUser();
        $book = Book::first();

        // User 1 creates a private reflection
        $resPrivate = $this->actingAs($user1)->post('/reading/reflections', [
            'book_id' => $book->id,
            'note' => 'هذا تأمل خاص وسري للغاية لا يراه أحد.',
            'privacy' => 'private',
        ]);
        $resPrivate->assertSessionHasNoErrors();

        // User 1 creates a public reflection
        $resPublic = $this->actingAs($user1)->post('/reading/reflections', [
            'book_id' => $book->id,
            'note' => 'اقتباس عام ملهم ومشارك لجميع قراء الرواق.',
            'privacy' => 'public',
        ]);
        $resPublic->assertSessionHasNoErrors();

        // Verify database records
        $privateQuote = Reflection::where('user_id', $user1->id)->where('privacy', 'private')->first();
        $publicQuote = Reflection::where('user_id', $user1->id)->where('privacy', 'public')->first();

        $this->assertNotNull($privateQuote);
        $this->assertTrue($privateQuote->isPrivate());
        $this->assertNotNull($publicQuote);
        $this->assertFalse($publicQuote->isPrivate());

        // User 2 visits reading page: can see public quote, but NOT private quote
        $responseUser2 = $this->actingAs($user2)->get('/reading?tab=quotes');
        $responseUser2->assertStatus(200);
        $responseUser2->assertSee('اقتباس عام ملهم ومشارك لجميع قراء الرواق.');
        $responseUser2->assertDontSee('هذا تأمل خاص وسري للغاية لا يراه أحد.');

        // User 1 visits reading page: can see both public and their own private quote
        $responseUser1 = $this->actingAs($user1)->get('/reading?tab=quotes');
        $responseUser1->assertStatus(200);
        $responseUser1->assertSee('اقتباس عام ملهم ومشارك لجميع قراء الرواق.');
        $responseUser1->assertSee('هذا تأمل خاص وسري للغاية لا يراه أحد.');
        $responseUser1->assertSee('خاص بك فقط 🔒');
    }

    /**
     * 6. فصل المراجعات العامة للكتب عن نقاشات المجتمعات القرائية
     */
    public function test_book_reviews_are_general_while_discussions_are_community_scoped(): void
    {
        $user = $this->getDemoUser();
        $book = Book::first();
        $community = Community::first() ?? Community::factory()->create();

        // 1. Post a general book review
        $reviewRes = $this->actingAs($user)->post('/reading/reviews', [
            'book_id' => $book->id,
            'rating' => 5,
            'title' => 'مراجعة نقدية عامة للكتاب',
            'content' => 'مراجعة تحليلية شاملة للكتاب موحدة على مستوى المنصة.',
        ]);
        $reviewRes->assertSessionHasNoErrors();

        $review = BookReview::where('user_id', $user->id)->where('book_id', $book->id)->latest('id')->first();
        $this->assertNotNull($review);
        $this->assertEquals('مراجعة نقدية عامة للكتاب', $review->title);

        // 2. Discussions are strictly community scoped
        $discussion = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'نقاش مخصص لأعضاء المجلس القرائي',
            'content' => 'حوار تفاعلي خاص بأعضاء هذا المجلس فقط.',
        ]);

        $this->assertNotNull($discussion->community_id);
        $this->assertEquals($community->id, $discussion->community_id);
    }

    /**
     * 7. مؤشر التنوع المعرفي (KDI) يحسب من توزيع التصنيفات واللغات والمؤلفين وفق المعادلة الموثقة
     */
    public function test_knowledge_diversity_index_calculates_and_documents_mathematical_formula(): void
    {
        $user = $this->getDemoUser();

        // Seed diverse books for the user
        $books = [
            ['title' => 'تاريخ الفلسفة', 'author' => 'يوسف كرم', 'category' => 'فلسفة', 'language' => 'العربية'],
            ['title' => 'مقدمة ابن خلدون', 'author' => 'ابن خلدون', 'category' => 'تاريخ', 'language' => 'العربية'],
            ['title' => 'قواعد العشق الأربعون', 'author' => 'إليف شفق', 'category' => 'رواية', 'language' => 'الإنجليزية'],
            ['title' => 'الفيزياء والوجود', 'author' => 'كارل ساغان', 'category' => 'علوم', 'language' => 'الإنجليزية'],
        ];

        foreach ($books as $b) {
            $book = Book::updateOrCreate(
                ['title' => $b['title']],
                ['author' => $b['author'], 'category' => $b['category'], 'language' => $b['language'], 'total_pages' => 300]
            );

            UserCurrentBook::updateOrCreate(
                ['user_id' => $user->id, 'book_id' => $book->id],
                ['status' => 'completed', 'current_page' => 300]
            );
        }

        $kdi = $user->calculateKnowledgeDiversityIndex();

        $this->assertIsArray($kdi);
        $this->assertArrayHasKey('score', $kdi);
        $this->assertArrayHasKey('categories_count', $kdi);
        $this->assertArrayHasKey('authors_count', $kdi);
        $this->assertArrayHasKey('languages_count', $kdi);
        $this->assertArrayHasKey('formula', $kdi);

        // Verify categories, authors, languages counts
        $this->assertGreaterThanOrEqual(3, $kdi['categories_count']);
        $this->assertGreaterThanOrEqual(3, $kdi['authors_count']);
        $this->assertGreaterThanOrEqual(2, $kdi['languages_count']);

        // Score should be between 1 and 100
        $this->assertGreaterThanOrEqual(50, $kdi['score']);
        $this->assertLessThanOrEqual(100, $kdi['score']);
    }

    /**
     * 8. التحقق من عرض الواجهات والتبويبات الجديدة (الرفوف، التنوع المعرفي، الأرشيف)
     */
    public function test_reading_views_render_shelves_diversity_kdi_and_annual_archive(): void
    {
        $user = $this->getDemoUser();

        // 1. Shelves tab
        $shelvesRes = $this->actingAs($user)->get('/reading?tab=shelves');
        $shelvesRes->assertStatus(200);
        $shelvesRes->assertSee('الرفوف وحالات الكتب الخمسة');
        $shelvesRes->assertSee('أقرأ الآن (قراءة نشطة)');
        $shelvesRes->assertSee('أرغب بقراءته (قائمة الأمنيات)');
        $shelvesRes->assertSee('مكتمل (أتممت قراءته)');
        $shelvesRes->assertSee('متوقف / معلّق مؤقتاً');
        $shelvesRes->assertSee('لم أكمله (DNF)');

        // 2. Diversity tab
        $diversityRes = $this->actingAs($user)->get('/reading?tab=diversity');
        $diversityRes->assertStatus(200);
        $diversityRes->assertSee('مؤشر التنوع المعرفي (KDI)');
        $diversityRes->assertSee('تنوع التصنيفات (45%)');
        $diversityRes->assertSee('تعدد المؤلفين (35%)');
        $diversityRes->assertSee('تنوع اللغات (20%)');
        $diversityRes->assertSee('بصمة تنوعك المعرفي');

        // 3. Achievements tab
        $achievementsRes = $this->actingAs($user)->get('/reading?tab=achievements');
        $achievementsRes->assertStatus(200);
        $achievementsRes->assertSee('قاعدة التحقق ومنع التلاعب');
        $achievementsRes->assertSee('السجل السنوي والأرشيف المعرفي المحفوظ');
    }
}
