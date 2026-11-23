<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookNomination;
use App\Models\BookPoll;
use App\Models\BookPollOption;
use App\Models\BookReview;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\CommunityBookHistory;
use App\Models\ContentReport;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\Meeting;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqDiscussionsAndBookOfMonthEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    protected function createTestUser(string $name = 'قارئ رواق', string $role = 'reader'): User
    {
        return User::create([
            'name' => $name,
            'email' => 'test_'.uniqid().'@riwaq.test',
            'password' => bcrypt('password'),
            'role' => $role,
            'points' => 100,
        ]);
    }

    protected function createTestCommunity(User $creator): Community
    {
        $community = Community::create([
            'name' => 'مجلس الأدب والنقد '.uniqid(),
            'slug' => 'community-'.uniqid(),
            'description' => 'مجلس متخصص في قراءة روائع الأدب العالمي والعربي',
            'type' => 'public',
            'creator_id' => $creator->id,
            'max_members' => 50,
            'rules' => 'الالتزام بآداب الحوار وعدم حرق الأحداث بدون تنبيه',
            'welcome_message' => 'مرحباً بكم في مجلسنا القرائي',
            'featured_question' => 'ما هو المشهد الذي أعاد تشكيل نظرتك للحياة؟',
        ]);

        $community->members()->attach($creator->id, ['role' => 'owner']);

        return $community;
    }

    public function test_isolated_discussion_space_per_book_inside_community(): void
    {
        $creator = $this->createTestUser('مدير المجلس', 'admin');
        $member = $this->createTestUser('عضو نقاش');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($member->id, ['role' => 'member']);

        $bookA = Book::first() ?? Book::create([
            'title' => 'البؤساء',
            'author' => 'فيكتور هوجو',
            'category' => 'روايات',
            'pages_count' => 500,
            'language' => 'العربية',
        ]);

        $bookB = Book::skip(1)->first() ?? Book::create([
            'title' => 'مئة عام من العزلة',
            'author' => 'غابرييل غارسيا ماركيز',
            'category' => 'روايات',
            'pages_count' => 450,
            'language' => 'العربية',
        ]);

        $community->update(['current_book_id' => $bookA->id]);

        // Create Discussion for Book A in Community
        $discA = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'book_id' => $bookA->id,
            'title' => 'رمزية شخصية جان فالجان',
            'content' => 'كيف يعكس التحول الأخلاقي في شخصية جان فالجان فكرة الخلاص في الأدب الكلاسيكي؟',
        ]);

        // Create Discussion for Book B in Community
        $discB = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'book_id' => $bookB->id,
            'title' => 'عزلة عائلة بوينديا وتكرار التاريخ',
            'content' => 'مفهوم الزمن الدائري في ماكوندو وعلاقته بالعزلة الإنسانية.',
        ]);

        // Create global Review on Book A (platform-wide, completely separate model)
        BookReview::create([
            'user_id' => $member->id,
            'book_id' => $bookA->id,
            'rating' => 5,
            'content' => 'مراجعة عامة للمنصة حول كتاب البؤساء.',
        ]);

        // 1. Visit community filtered by Book A: discussions for Book A visible, Book B hidden
        $responseA = $this->actingAs($member)->get("/communities?community_id={$community->id}&book_id={$bookA->id}");
        $responseA->assertStatus(200);
        $responseA->assertSee('رمزية شخصية جان فالجان');
        $responseA->assertDontSee('عزلة عائلة بوينديا وتكرار التاريخ');

        // 2. Visit community filtered by Book B: discussions for Book B visible, Book A hidden
        $responseB = $this->actingAs($member)->get("/communities?community_id={$community->id}&book_id={$bookB->id}");
        $responseB->assertStatus(200);
        $responseB->assertSee('عزلة عائلة بوينديا وتكرار التاريخ');
        $responseB->assertDontSee('رمزية شخصية جان فالجان');

        // 3. Test bookDiscussions route redirect helper
        $redirectResponse = $this->actingAs($member)->get("/communities/{$community->id}/books/{$bookA->id}/discussions");
        $redirectResponse->assertRedirect(route('communities.index', [
            'community_id' => $community->id,
            'book_id' => $bookA->id,
            'tab' => 'discussions',
        ]));
    }

    public function test_coordinator_question_and_pinned_discussion_permissions(): void
    {
        $creator = $this->createTestUser('منسق القراءة', 'admin');
        $member = $this->createTestUser('قارئ عادي');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($member->id, ['role' => 'member']);
        $book = Book::first();

        // 1. Coordinator creates a Discussion marked as Coordinator Question and Pinned
        $responseCoord = $this->actingAs($creator)->post("/communities/{$community->id}/discussions", [
            'book_id' => $book->id,
            'title' => 'سؤال المنسق: البعد الفلسفي للرواية',
            'content' => 'ما رأيكم في رمزية المرآة في الفصل الخامس؟ شاركونا آراءكم.',
            'is_coordinator_question' => 1,
            'is_pinned' => 1,
            'is_spoiler' => 0,
        ]);
        $responseCoord->assertSessionHas('success');

        $coordDisc = Discussion::where('title', 'سؤال المنسق: البعد الفلسفي للرواية')->first();
        $this->assertNotNull($coordDisc);
        $this->assertTrue((bool) $coordDisc->is_coordinator_question);
        $this->assertTrue((bool) $coordDisc->is_pinned);

        // 2. Regular member attempts to post with coordinator/pinned flags (should be ignored)
        $this->actingAs($member)->post("/communities/{$community->id}/discussions", [
            'book_id' => $book->id,
            'title' => 'منشور عضو عادي يحاول التثبيت',
            'content' => 'مشاركة شخصية عادية.',
            'is_coordinator_question' => 1,
            'is_pinned' => 1,
        ]);

        $memberDisc = Discussion::where('title', 'منشور عضو عادي يحاول التثبيت')->first();
        $this->assertNotNull($memberDisc);
        $this->assertFalse((bool) $memberDisc->is_coordinator_question);
        $this->assertFalse((bool) $memberDisc->is_pinned);

        // 3. Coordinator toggles pin on member discussion
        $pinResponse = $this->actingAs($creator)->post("/discussions/{$memberDisc->id}/pin");
        $pinResponse->assertSessionHas('success');
        $memberDisc->refresh();
        $this->assertTrue((bool) $memberDisc->is_pinned);

        // 4. Regular member tries to toggle pin -> 403 Forbidden
        $unauthPin = $this->actingAs($member)->post("/discussions/{$memberDisc->id}/pin");
        $unauthPin->assertStatus(403);
    }

    public function test_comments_nested_replies_and_spoiler_flags(): void
    {
        $creator = $this->createTestUser('مدير المجلس');
        $userA = $this->createTestUser('القارئ ماجد');
        $userB = $this->createTestUser('القارئة سارة');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($userA->id, ['role' => 'member']);
        $community->members()->attach($userB->id, ['role' => 'member']);

        // 1. Post discussion with spoiler flag
        $this->actingAs($userA)->post("/communities/{$community->id}/discussions", [
            'title' => 'مصير البطل في النهاية',
            'content' => 'النهاية صادمة عندما يكتشف البطل الحقيقة ويفترق عن الجميع.',
            'is_spoiler' => 1,
        ]);

        $disc = Discussion::where('title', 'مصير البطل في النهاية')->first();
        $this->assertNotNull($disc);
        $this->assertTrue((bool) $disc->is_spoiler);

        // 2. User B posts comment on discussion
        $this->actingAs($userB)->post("/discussions/{$disc->id}/comments", [
            'content' => 'أتفق معك تماماً، لم أكن أتوقع هذا التحول.',
            'is_spoiler' => 0,
        ]);

        $comment = DiscussionComment::where('discussion_id', $disc->id)->first();
        $this->assertNotNull($comment);
        $this->assertNull($comment->parent_id);
        $this->assertEquals($userB->id, $comment->user_id);

        // Assert notification sent to discussion author (User A)
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $userA->id,
            'type' => 'discussion_comment',
        ]);

        // 3. User A posts nested reply to User B's comment with spoiler flag
        $this->actingAs($userA)->post("/discussions/{$disc->id}/comments", [
            'parent_id' => $comment->id,
            'content' => 'والأكثر دهشة هو اعتراف الخصم في الصفحة الأخيرة.',
            'is_spoiler' => 1,
        ]);

        $reply = DiscussionComment::where('parent_id', $comment->id)->first();
        $this->assertNotNull($reply);
        $this->assertTrue((bool) $reply->is_spoiler);
        $this->assertEquals($comment->id, $reply->parent_id);

        // Assert notification sent to comment author (User B)
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $userB->id,
            'type' => 'discussion_comment',
        ]);
    }

    public function test_discussion_and_comment_likes_ecosystem(): void
    {
        $creator = $this->createTestUser('مدير المجلس');
        $userA = $this->createTestUser('كاتب النقاش');
        $userB = $this->createTestUser('المعجب بالنقاش');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($userA->id, ['role' => 'member']);
        $community->members()->attach($userB->id, ['role' => 'member']);

        $disc = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $userA->id,
            'title' => 'تأمل في بلاغة النص',
            'content' => 'جمال السرد يكمن في بساطة اللغة وعمق الدلالة.',
            'likes_count' => 0,
        ]);

        $comment = DiscussionComment::create([
            'discussion_id' => $disc->id,
            'user_id' => $userA->id,
            'content' => 'أشكر كل من تفاعل مع هذه النقطة.',
            'likes_count' => 0,
        ]);

        // 1. User B likes the discussion
        $this->actingAs($userB)->post("/discussions/{$disc->id}/like");
        $disc->refresh();
        $this->assertEquals(1, $disc->likes_count);
        $this->assertTrue($disc->isLikedBy($userB));
        $this->assertDatabaseHas('discussion_likes', [
            'discussion_id' => $disc->id,
            'user_id' => $userB->id,
        ]);

        // Author receives notification
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $userA->id,
            'type' => 'discussion_like',
        ]);

        // 2. User B un-likes the discussion
        $this->actingAs($userB)->post("/discussions/{$disc->id}/like");
        $disc->refresh();
        $this->assertEquals(0, $disc->likes_count);
        $this->assertFalse($disc->isLikedBy($userB));
        $this->assertDatabaseMissing('discussion_likes', [
            'discussion_id' => $disc->id,
            'user_id' => $userB->id,
        ]);

        // 3. User B likes the comment
        $this->actingAs($userB)->post("/comments/{$comment->id}/like");
        $comment->refresh();
        $this->assertEquals(1, $comment->likes_count);
        $this->assertTrue($comment->isLikedBy($userB));
        $this->assertDatabaseHas('discussion_comment_likes', [
            'discussion_comment_id' => $comment->id,
            'user_id' => $userB->id,
        ]);

        // 4. User B un-likes the comment
        $this->actingAs($userB)->post("/comments/{$comment->id}/like");
        $comment->refresh();
        $this->assertEquals(0, $comment->likes_count);
        $this->assertFalse($comment->isLikedBy($userB));
    }

    public function test_mention_parsing_and_notifications(): void
    {
        $creator = $this->createTestUser('مدير المجلس');
        $author = $this->createTestUser('طارق الكاتب');
        $mentionedUser = $this->createTestUser('زياد_القارئ');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($author->id, ['role' => 'member']);
        $community->members()->attach($mentionedUser->id, ['role' => 'member']);

        // 1. Author mentions @زياد_القارئ in discussion
        $this->actingAs($author)->post("/communities/{$community->id}/discussions", [
            'title' => 'استفسار قرائي',
            'content' => "أود استشارة الزميل @{$mentionedUser->name} حول ترجمة هذا المقطع.",
        ]);

        // Assert notification dispatched for mentioned user
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $mentionedUser->id,
            'type' => 'mention',
        ]);

        // 2. Mention in comment
        $disc = Discussion::where('title', 'استفسار قرائي')->first();
        $this->actingAs($author)->post("/discussions/{$disc->id}/comments", [
            'content' => "شكراً لك @{$mentionedUser->name} على التوضيح الرائع.",
        ]);

        // Assert 2 mention notifications exist for mentioned user
        $mentionCount = UserNotification::where('user_id', $mentionedUser->id)
            ->where('type', 'mention')
            ->count();
        $this->assertGreaterThanOrEqual(2, $mentionCount);
    }

    public function test_content_reporting_and_moderation(): void
    {
        $creator = $this->createTestUser('مشرف المجلس', 'admin');
        $reporter = $this->createTestUser('المبلّغ');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($reporter->id, ['role' => 'member']);

        $disc = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $creator->id,
            'title' => 'محتوى مخالف للشروط',
            'content' => 'نص يحتوي على حرق بدون تنبيه أو كلام غير لائق.',
        ]);

        // 1. Reporter files a report
        $reportRes = $this->actingAs($reporter)->post('/content/report', [
            'reportable_type' => 'discussion',
            'reportable_id' => $disc->id,
            'reason' => 'spoiler',
            'details' => 'المنشور يحرق الخاتمة بدون وضع علامة حرق الأحداث.',
        ]);
        $reportRes->assertSessionHas('success');

        $report = ContentReport::where('reportable_type', 'discussion')
            ->where('reportable_id', $disc->id)
            ->first();
        $this->assertNotNull($report);
        $this->assertEquals('pending', $report->status);

        // 2. Moderator takes action: delete_content
        $resolveRes = $this->actingAs($creator)->post("/moderation/reports/{$report->id}/resolve", [
            'action' => 'delete_content',
            'moderator_notes' => 'تم الحذف لمخالفة قواعد المجلس.',
        ]);
        $resolveRes->assertSessionHas('success');

        $report->refresh();
        $this->assertEquals('action_taken', $report->status);
        $this->assertEquals($creator->id, $report->moderated_by);

        // Target discussion was soft deleted
        $this->assertSoftDeleted('discussions', ['id' => $disc->id]);
        $this->assertNull(Discussion::find($disc->id));
    }

    public function test_book_nominations_and_endorsements(): void
    {
        $creator = $this->createTestUser('مدير المجلس', 'admin');
        $nominator = $this->createTestUser('المقترح');
        $endorser = $this->createTestUser('المؤيد');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($nominator->id, ['role' => 'member']);
        $community->members()->attach($endorser->id, ['role' => 'member']);

        // 1. Submit Book Nomination
        $nomResponse = $this->actingAs($nominator)->post("/communities/{$community->id}/nominations", [
            'title' => 'ثلاثية غرناطة',
            'author' => 'رضوى عاشور',
            'reason' => 'رواية تاريخية ملحمية تستحق قراءة جماعية متأنية.',
        ]);
        $nomResponse->assertSessionHas('success');

        $nomination = BookNomination::where('community_id', $community->id)->first();
        $this->assertNotNull($nomination);
        $this->assertEquals('ثلاثية غرناطة', $nomination->title);
        $this->assertEquals(1, $nomination->endorsements_count); // Auto-endorsed by submitter

        // 2. Endorse Nomination
        $endorseRes = $this->actingAs($endorser)->post("/nominations/{$nomination->id}/endorse");
        $endorseRes->assertSessionHas('success');
        $nomination->refresh();
        $this->assertEquals(2, $nomination->endorsements_count);
        $this->assertTrue($nomination->isEndorsedBy($endorser));

        // 3. Add to Poll
        $poll = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'استطلاع كتاب الشهر',
            'is_active' => true,
            'closes_at' => Carbon::now()->addDays(14),
        ]);

        $addToPollRes = $this->actingAs($creator)->post("/nominations/{$nomination->id}/add-to-poll");
        $addToPollRes->assertSessionHas('success');
        $nomination->refresh();
        $this->assertEquals('added_to_poll', $nomination->status);

        $pollOption = BookPollOption::where('book_poll_id', $poll->id)
            ->where('custom_title', 'like', '%ثلاثية غرناطة%')
            ->first();
        $this->assertNotNull($pollOption);
    }

    public function test_book_poll_result_visibility_settings(): void
    {
        $creator = $this->createTestUser('مدير المجلس', 'admin');
        $voter = $this->createTestUser('المصوت');
        $nonVoter = $this->createTestUser('غير المصوت');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($voter->id, ['role' => 'member']);
        $community->members()->attach($nonVoter->id, ['role' => 'member']);

        $bookA = Book::first();
        $bookB = Book::skip(1)->first();

        // 1. Setting: after_vote
        $pollAfterVote = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'استطلاع مع نتائج بعد التصويت',
            'is_active' => true,
            'show_results' => BookPoll::SHOW_RESULTS_AFTER_VOTE,
            'closes_at' => Carbon::now()->addDays(7),
        ]);

        $opt1 = $pollAfterVote->options()->create(['book_id' => $bookA->id, 'custom_title' => $bookA->title, 'votes_count' => 0]);
        $opt2 = $pollAfterVote->options()->create(['book_id' => $bookB->id, 'custom_title' => $bookB->title, 'votes_count' => 0]);

        // Voter has not voted yet -> cannot see results
        $this->assertFalse($pollAfterVote->canUserSeeResults($voter));

        // Non-voter cannot see results
        $this->assertFalse($pollAfterVote->canUserSeeResults($nonVoter));

        // Manager can always see results
        $this->assertTrue($pollAfterVote->canUserSeeResults($creator));

        // Voter votes -> now can see results
        $this->actingAs($voter)->post("/polls/{$pollAfterVote->id}/vote", ['option_id' => $opt1->id]);
        $this->assertTrue($pollAfterVote->canUserSeeResults($voter));
        $this->assertFalse($pollAfterVote->canUserSeeResults($nonVoter));

        // 2. Setting: after_close
        $pollAfterClose = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'استطلاع مع نتائج بعد الإغلاق',
            'is_active' => true,
            'show_results' => BookPoll::SHOW_RESULTS_AFTER_CLOSE,
            'closes_at' => Carbon::now()->addDays(7),
        ]);
        $optClose1 = $pollAfterClose->options()->create(['book_id' => $bookA->id, 'custom_title' => $bookA->title, 'votes_count' => 0]);

        // Voter votes, but cannot see results until poll closes
        $this->actingAs($voter)->post("/polls/{$pollAfterClose->id}/vote", ['option_id' => $optClose1->id]);
        $this->assertFalse($pollAfterClose->canUserSeeResults($voter));
        $this->assertTrue($pollAfterClose->canUserSeeResults($creator)); // Manager still sees it

        // Close poll -> all users can now see results
        $pollAfterClose->update(['is_active' => false]);
        $this->assertTrue($pollAfterClose->canUserSeeResults($voter));
        $this->assertTrue($pollAfterClose->canUserSeeResults($nonVoter));
    }

    public function test_one_click_close_poll_workflow_creates_challenge_and_meeting(): void
    {
        $creator = $this->createTestUser('منسق القراءة والمدير', 'admin');
        $member1 = $this->createTestUser('مصوت أول');
        $member2 = $this->createTestUser('مصوت ثان');
        $community = $this->createTestCommunity($creator);
        $community->members()->attach($member1->id, ['role' => 'member']);
        $community->members()->attach($member2->id, ['role' => 'member']);

        $bookOld = Book::first() ?? Book::create(['title' => 'الكتاب السابق', 'author' => 'كاتب قديم', 'category' => 'تراث']);
        $winningBook = Book::skip(1)->first() ?? Book::create(['title' => 'الكتاب الفائز بالتصويت', 'author' => 'كاتب فائز', 'category' => 'فكر']);
        $runnerUpBook = Book::skip(2)->first() ?? Book::create(['title' => 'الكتاب الوصيف', 'author' => 'كاتب وصيف', 'category' => 'أدب']);

        $community->update(['current_book_id' => $bookOld->id]);

        $poll = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'أيّ كتاب نقرأ لشهر نوفمبر؟',
            'is_active' => true,
            'month_name' => 'نوفمبر',
            'show_results' => BookPoll::SHOW_RESULTS_ALWAYS,
            'closes_at' => Carbon::now()->addDays(5),
        ]);

        $optWinning = $poll->options()->create([
            'book_id' => $winningBook->id,
            'custom_title' => $winningBook->title,
            'votes_count' => 0,
        ]);

        $optRunnerUp = $poll->options()->create([
            'book_id' => $runnerUpBook->id,
            'custom_title' => $runnerUpBook->title,
            'votes_count' => 0,
        ]);

        // 2 votes for winning book, 1 vote for runner up
        $this->actingAs($member1)->post("/polls/{$poll->id}/vote", ['option_id' => $optWinning->id]);
        $this->actingAs($member2)->post("/polls/{$poll->id}/vote", ['option_id' => $optWinning->id]);
        $this->actingAs($creator)->post("/polls/{$poll->id}/vote", ['option_id' => $optRunnerUp->id]);

        $optWinning->refresh();
        $optRunnerUp->refresh();
        $this->assertEquals(2, $optWinning->votes_count);
        $this->assertEquals(1, $optRunnerUp->votes_count);

        // Execute One-Click Close & Integrated Workflow
        $closeRes = $this->actingAs($creator)->post("/polls/{$poll->id}/close-and-set-winner", [
            'create_challenge' => 1,
            'create_meeting' => 1,
            'meeting_type' => Meeting::TYPE_HYBRID,
        ]);
        $closeRes->assertSessionHas('success');

        // 1. Assert Poll is closed and winner recorded
        $poll->refresh();
        $this->assertFalse((bool) $poll->is_active);
        $this->assertEquals($winningBook->id, $poll->winner_book_id);
        $this->assertTrue($poll->isClosed());

        // 2. Assert Community's current_book_id updated to winning book
        $community->refresh();
        $this->assertEquals($winningBook->id, $community->current_book_id);

        // 3. Assert previous book archived in CommunityBookHistory
        $this->assertDatabaseHas('community_books_history', [
            'community_id' => $community->id,
            'book_id' => $bookOld->id,
        ]);

        // 4. Assert linked Challenge created for the winning book
        $challenge = Challenge::where('community_id', $community->id)
            ->where('specific_book_id', $winningBook->id)
            ->first();
        $this->assertNotNull($challenge);
        $this->assertEquals($winningBook->id, $challenge->specific_book_id);
        $this->assertEquals('book_specific', $challenge->challenge_type);

        // 5. Assert linked Meeting created for the winning book
        $meeting = Meeting::where('community_id', $community->id)
            ->where('book_id', $winningBook->id)
            ->first();
        $this->assertNotNull($meeting);
        $this->assertEquals($winningBook->id, $meeting->book_id);
        $this->assertEquals(Meeting::TYPE_HYBRID, $meeting->meeting_type);

        // 6. Assert Community Announcement published
        $this->assertDatabaseHas('community_announcements', [
            'community_id' => $community->id,
            'title' => "كتاب الشهر المعتمد: «{$winningBook->title}» 🏆",
        ]);

        // 7. Assert Notification sent to members
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member1->id,
            'type' => 'community',
        ]);

        // 8. Assert AuditLog recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $creator->id,
            'action' => 'close_poll_workflow',
        ]);
    }
}
