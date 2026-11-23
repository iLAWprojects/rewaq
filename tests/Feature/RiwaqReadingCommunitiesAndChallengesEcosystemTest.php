<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReview;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReadingCommunitiesAndChallengesEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    protected function getDemoUser(): User
    {
        return User::where('email', 'demo@riwaq.test')->first()
            ?? User::factory()->create(['email' => 'demo@riwaq.test', 'name' => 'قارئ تجريبي']);
    }

    public function test_reading_page_renders_all_tabs_and_series(): void
    {
        $user = $this->getDemoUser();

        // Journey / Sessions Tab
        $res = $this->actingAs($user)->get('/reading?tab=journey');
        $res->assertStatus(200);
        $res->assertSee('سجلّ الأثر');
        $res->assertSee('الهدف السنوي للقراءة');

        // Reviews Tab
        $resRev = $this->actingAs($user)->get('/reading?tab=reviews');
        $resRev->assertStatus(200);
        $resRev->assertSee('المراجعات والتقييمات');

        // Quotes Tab
        $resQuotes = $this->actingAs($user)->get('/reading?tab=quotes');
        $resQuotes->assertStatus(200);
        $resQuotes->assertSee('الاقتباسات والتأملات');

        // Series Tab
        $resSeries = $this->actingAs($user)->get('/reading?tab=series');
        $resSeries->assertStatus(200);
        $resSeries->assertSee('السلاسل والمسارات القرائية');

        // Achievements Tab
        $resAch = $this->actingAs($user)->get('/reading?tab=achievements');
        $resAch->assertStatus(200);
        $resAch->assertSee('سلسلة الاستمرار والانضباط');
        $resAch->assertSee('الأوسمة والإنجازات الشرفية');
    }

    public function test_user_can_update_annual_reading_goal(): void
    {
        $user = $this->getDemoUser();

        $response = $this->actingAs($user)->post('/reading/goal', [
            'goal' => 36,
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals(36, $user->annual_goal);
    }

    public function test_user_can_post_and_like_book_review_with_spoiler_protection(): void
    {
        $user = $this->getDemoUser();
        $book = Book::first();

        $response = $this->actingAs($user)->post('/reading/reviews', [
            'book_id' => $book->id,
            'rating' => 5,
            'title' => 'تحفة فكرية أدبية خالدة',
            'content' => 'كتاب عميق يتناول قضايا النهضة والفكر الإنساني بأسلوب بديع.',
            'is_spoiler' => 1,
        ]);

        $response->assertRedirect('/reading?tab=reviews');
        $this->assertDatabaseHas('book_reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'is_spoiler' => true,
        ]);

        $review = BookReview::where('user_id', $user->id)->where('book_id', $book->id)->latest()->first();
        $initialLikes = $review->likes_count;

        // Like the review
        $likeResponse = $this->actingAs($user)->post("/reading/reviews/{$review->id}/like");
        $likeResponse->assertSessionHas('success');
        $review->refresh();
        $this->assertEquals($initialLikes + 1, $review->likes_count);
    }

    public function test_user_can_switch_current_active_book(): void
    {
        $user = User::factory()->create();
        $books = Book::take(2)->get();
        if ($books->count() < 2) {
            $books->push(Book::create([
                'title' => 'كتاب ثان للتجربة',
                'author' => 'مؤلف تجريبي',
                'category' => 'أدب',
                'total_pages' => 200,
                'cover_color' => '#173D36',
            ]));
        }

        $targetBook = $books->last();

        $response = $this->actingAs($user)->post('/reading/switch-book', [
            'book_id' => $targetBook->id,
        ]);

        $response->assertRedirect('/reading');
        $this->assertDatabaseHas('user_current_books', [
            'user_id' => $user->id,
            'book_id' => $targetBook->id,
            'is_active' => true,
        ]);
    }

    public function test_communities_type_filtering_and_roles(): void
    {
        $user = $this->getDemoUser();

        // Filter by private
        $response = $this->actingAs($user)->get('/communities?type=private');
        $response->assertStatus(200);

        // Filter by specialized
        $responseSpec = $this->actingAs($user)->get('/communities?type=specialized');
        $responseSpec->assertStatus(200);
    }

    public function test_private_community_join_request_flow(): void
    {
        $user = $this->getDemoUser();
        $otherUser = User::factory()->create(['name' => 'طالب الانضمام']);

        $privateCommunity = Community::firstOrCreate(
            ['type' => 'private'],
            [
                'name' => 'مجلس الرواية الفلسفية الخاص',
                'category' => 'فلسفة ورواية',
                'description' => 'حلقة نقاش مغلقة لكبار قارئي الروايات الفلسفية.',
                'creator_id' => $user->id,
                'code' => 'PHIL-9999',
            ]
        );
        $privateCommunity->update(['creator_id' => $user->id]);

        // Request join
        $reqResponse = $this->actingAs($otherUser)->post("/communities/{$privateCommunity->id}/join-request", [
            'notes' => 'أهوى الروايات الوجودية وأرغب بالمشاركة الجادة.',
        ]);

        $reqResponse->assertSessionHas('success');
        $this->assertDatabaseHas('community_join_requests', [
            'community_id' => $privateCommunity->id,
            'user_id' => $otherUser->id,
            'status' => 'pending',
        ]);

        $joinRequest = CommunityJoinRequest::where('community_id', $privateCommunity->id)->where('user_id', $otherUser->id)->first();

        // Creator approves request
        $approveResponse = $this->actingAs($user)->post("/communities/join-requests/{$joinRequest->id}/approve");
        $approveResponse->assertSessionHas('success');

        $joinRequest->refresh();
        $this->assertEquals('approved', $joinRequest->status);
        $this->assertTrue($privateCommunity->members()->where('user_id', $otherUser->id)->exists());
    }

    public function test_community_manager_can_post_pinned_announcement(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();
        $community->update(['creator_id' => $user->id]);

        $response = $this->actingAs($user)->post("/communities/{$community->id}/announcements", [
            'title' => 'لقاء خاص مع ضيف شرف',
            'content' => 'يسرنا استضافة ناقد أدبي لمناقشة فصول كتاب الشهر يوم الخميس.',
            'is_pinned' => 1,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('community_announcements', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'title' => 'لقاء خاص مع ضيف شرف',
            'is_pinned' => true,
        ]);
    }

    public function test_challenges_team_join_and_progress_logging_with_certificate(): void
    {
        $user = $this->getDemoUser();

        $teamChallenge = Challenge::firstOrCreate(
            ['is_team' => true],
            [
                'title' => 'سباق فرسان الأدب ضد رواد الفكر',
                'description' => 'تحدي الفرق الجماعي لاجتياز 1000 صفحة مشتركة.',
                'category' => 'تنافس جماعي',
                'target_count' => 100,
                'team_a_name' => 'فرسان الأدب',
                'team_b_name' => 'رواد الفكر',
                'team_a_pages' => 0,
                'team_b_pages' => 0,
                'is_active' => true,
                'reward_points' => 200,
                'reward_badge' => 'وسام الفوز الجماعي',
            ]
        );
        $teamChallenge->update(['target_count' => 100]);

        // Join Team A
        $joinTeamResponse = $this->actingAs($user)->post("/challenges/{$teamChallenge->id}/team-join", [
            'team' => 'team_a',
        ]);
        $joinTeamResponse->assertSessionHas('success');

        // Log Progress
        $progressResponse = $this->actingAs($user)->post("/challenges/{$teamChallenge->id}/progress", [
            'pages' => 120,
        ]);
        $progressResponse->assertSessionHas('success');

        $teamChallenge->refresh();
        $this->assertGreaterThanOrEqual(120, $teamChallenge->team_a_pages);

        $participant = $teamChallenge->participants()->where('user_id', $user->id)->first();
        $this->assertNotNull($participant);
        $this->assertTrue((bool) $participant->pivot->completed);
        $this->assertNotNull($participant->pivot->certificate_code);
        $this->assertStringStartsWith('CERT-RWQ-', $participant->pivot->certificate_code);
    }

    public function test_challenges_page_renders_leaderboard_and_archive_tabs(): void
    {
        $user = $this->getDemoUser();

        // Active Tab
        $resActive = $this->actingAs($user)->get('/challenges?tab=active');
        $resActive->assertStatus(200);
        $resActive->assertSee('تحديات الفرق');

        // Leaderboards Tab
        $resLead = $this->actingAs($user)->get('/challenges?tab=leaderboards');
        $resLead->assertStatus(200);
        $resLead->assertSee('لوحة صدارة القرّاء المتصدرين');

        // Archive Tab
        $resArc = $this->actingAs($user)->get('/challenges?tab=archive');
        $resArc->assertStatus(200);
        $resArc->assertSee('أرشيف التحديات السابقة');
    }
}
