<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\ReadingSession;
use App\Models\User;
use App\Models\UserCurrentBook;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqChallengeAudienceNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_challenge_notifies_only_creator_followers(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $follower->follow($creator);

        $this->actingAs($creator)->post(route('challenges.store'), [
            'title' => 'تحدي الصيف العام',
            'description' => 'اقرأ خمسة كتب',
            'challenge_type' => Challenge::TYPE_BOOKS_COUNT,
            'target_count' => 5,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, UserNotification::where('user_id', $follower->id)->where('type', 'challenge')->count());
        $this->assertSame(0, UserNotification::where('user_id', $stranger->id)->where('type', 'challenge')->count());
        $this->assertSame(0, UserNotification::where('user_id', $creator->id)->where('type', 'challenge')->count());
    }

    public function test_new_challenge_appears_in_all_challenges_list_and_club_challenges_stay_private(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);
        $outsider = User::factory()->create();

        $this->actingAs($creator)->post(route('challenges.store'), [
            'title' => 'تحدي ظاهر للجميع',
            'description' => 'وصف',
            'challenge_type' => Challenge::TYPE_BOOKS_COUNT,
            'target_count' => 3,
        ])->assertRedirect(route('challenges.index').'#all-challenges')->assertSessionHas('new_challenge_id');

        $club = Community::create([
            'name' => 'نادي سري', 'category' => 'فكر', 'description' => 'د', 'cover_color' => '#173D36',
            'creator_id' => $creator->id, 'type' => 'private', 'language' => 'العربية', 'city' => 'الرياض', 'max_members' => 10,
        ]);
        Challenge::create([
            'title' => 'تحدي النادي السري', 'description' => 'د', 'community_id' => $club->id, 'creator_id' => $creator->id,
            'challenge_type' => Challenge::TYPE_BOOKS_COUNT, 'target_count' => 2, 'status' => 'active', 'is_active' => true,
        ]);

        $this->actingAs($outsider)->get(route('challenges.index'))
            ->assertOk()->assertSee('كل التحديات الجارية والقادمة')->assertSee('تحدي ظاهر للجميع')->assertDontSee('تحدي النادي السري');
    }

    private function pageChallengeAndBook(User $user): array
    {
        $book = Book::create(['title' => 'كتاب التحدي', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 400]);
        UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'currently_reading', 'current_page' => 0, 'progress_percentage' => 0, 'is_active' => true]);
        $challenge = Challenge::create([
            'title' => 'تحدي صفحات', 'description' => 'د', 'creator_id' => $user->id, 'challenge_type' => Challenge::TYPE_PAGES_COUNT,
            'target_count' => 500, 'target_unit' => 'صفحات', 'status' => 'active', 'is_active' => true, 'start_date' => now()->subDay(),
        ]);
        $challenge->participants()->attach($user->id, ['progress_count' => 0, 'points_earned' => 0, 'completed' => false, 'joined_at' => now()]);

        return [$book, $challenge];
    }

    public function test_reading_session_with_realistic_pace_counts_toward_challenge(): void
    {
        $user = User::factory()->create();
        [$book, $challenge] = $this->pageChallengeAndBook($user);

        // 20 صفحة تحتاج 30 دقيقة على الأقل (دقيقتان للصفحة مع تسامح 25%)
        $this->actingAs($user)->post(route('reading.storeSession'), ['book_id' => $book->id, 'start_page' => 0, 'page' => 20, 'minutes' => 30])
            ->assertSessionHas('success');

        $this->assertSame(20, (int) $challenge->participants()->where('user_id', $user->id)->first()->pivot->progress_count);
        $this->assertTrue(ReadingSession::where('user_id', $user->id)->first()->is_valid);
    }

    public function test_completing_challenge_via_session_shows_points_message_and_progress_and_header_points(): void
    {
        $user = User::factory()->create();
        [$book, $challenge] = $this->pageChallengeAndBook($user);
        $challenge->update(['target_count' => 20, 'reward_points' => 200]);

        $this->actingAs($user)->post(route('reading.storeSession'), ['book_id' => $book->id, 'start_page' => 0, 'page' => 20, 'minutes' => 30])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'لقد أكملت التحدي «تحدي صفحات» وحصلت على 200 نقطة'));

        $page = $this->actingAs($user)->get(route('challenges.index'));
        $page->assertOk()->assertSee('أكملت التحدي ✅')->assertSee('20 / 20')->assertSee('id="rq-points"', false);
        $this->assertTrue(UserNotification::where('user_id', $user->id)->where('body', 'like', '%وحصلت على 200 نقطة%')->exists());
    }

    public function test_unrealistic_pace_is_saved_as_unverified_and_counts_nowhere(): void
    {
        $user = User::factory()->create();
        [$book, $challenge] = $this->pageChallengeAndBook($user);
        $pointsBefore = (int) $user->points;

        $this->actingAs($user)->post(route('reading.storeSession'), ['book_id' => $book->id, 'start_page' => 0, 'page' => 100, 'minutes' => 5])
            ->assertSessionHas('warning');

        $session = ReadingSession::where('user_id', $user->id)->first();
        $this->assertFalse($session->is_valid);
        $this->assertNotNull($session->invalid_reason);

        $this->assertSame(0, (int) $challenge->participants()->where('user_id', $user->id)->first()->pivot->progress_count);
        $this->assertSame($pointsBefore, (int) $user->fresh()->points);
        $this->assertSame(0, (int) $user->fresh()->reading_streak);
        $this->assertSame(0, (int) UserCurrentBook::where('user_id', $user->id)->first()->current_page);
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->where('title', 'جلسة قراءة غير موثّقة')->count());
    }

    public function test_specific_book_challenge_shows_book_and_start_reading_button_after_join(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'الكتاب المطلوب', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 200]);
        $challenge = Challenge::create([
            'title' => 'تحدي كتاب محدد', 'description' => 'د', 'creator_id' => $user->id, 'challenge_type' => Challenge::TYPE_SPECIFIC_BOOK,
            'specific_book_id' => $book->id, 'target_count' => 1, 'target_unit' => 'كتب', 'status' => 'active', 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('challenges.index'))->assertSee('الكتاب المطلوب')->assertSee('لا تُحتسب')->assertDontSee('ابدأ قراءة هذا الكتاب');

        $challenge->participants()->attach($user->id, ['progress_count' => 0, 'points_earned' => 0, 'completed' => false, 'joined_at' => now()]);
        $this->actingAs($user)->get(route('challenges.index'))->assertSee('ابدأ قراءة هذا الكتاب');
    }

    public function test_create_form_offers_audience_and_book_scope(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($creator)->get(route('challenges.index'))
            ->assertOk()->assertSee('من يشارك؟')->assertSee('تحدٍّ عام')->assertSee('أي كتب تُحتسب؟')->assertSee('كتاب محدد');
    }

    public function test_creator_auto_joins_and_book_is_added_to_library_and_reading_log(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);
        $book = Book::create(['title' => 'كتاب لا أملكه', 'author' => 'م', 'category' => 'أدب', 'total_pages' => 120]);
        $this->assertFalse($creator->hasBookInPersonalLibrary($book));

        $this->actingAs($creator)->post(route('challenges.store'), [
            'title' => 'تحدي كتابي', 'description' => 'د', 'challenge_type' => Challenge::TYPE_SPECIFIC_BOOK,
            'specific_book_id' => $book->id, 'target_count' => 1,
        ])->assertSessionHasNoErrors();

        $challenge = Challenge::where('title', 'تحدي كتابي')->firstOrFail();
        $this->assertTrue($challenge->participants()->where('user_id', $creator->id)->exists());
        $this->assertTrue($creator->fresh()->hasBookInPersonalLibrary($book));
        $this->assertTrue(UserCurrentBook::where('user_id', $creator->id)->where('book_id', $book->id)->exists());
    }

    public function test_joiner_gets_book_added_and_creator_is_notified(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);
        $joiner = User::factory()->create();
        $book = Book::create(['title' => 'كتاب المنضم', 'author' => 'م', 'category' => 'أدب', 'total_pages' => 120]);
        $challenge = Challenge::create([
            'title' => 'تحدي للانضمام', 'description' => 'د', 'creator_id' => $creator->id, 'challenge_type' => Challenge::TYPE_SPECIFIC_BOOK,
            'specific_book_id' => $book->id, 'target_count' => 1, 'status' => 'active', 'is_active' => true,
        ]);

        $this->actingAs($joiner)->post(route('challenges.join', $challenge->id))->assertSessionHas('success');
        $this->actingAs($joiner)->post(route('challenges.join', $challenge->id));

        $this->assertTrue($joiner->fresh()->hasBookInPersonalLibrary($book));
        $this->assertTrue(UserCurrentBook::where('user_id', $joiner->id)->where('book_id', $book->id)->exists());
        $this->assertSame(1, UserNotification::where('user_id', $creator->id)->where('title', 'like', 'قارئ جديد انضم%')->count());
    }

    public function test_only_creator_can_finish_challenge_and_participants_are_notified(): void
    {
        $creator = User::factory()->create();
        $reader = User::factory()->create();
        $challenge = Challenge::create([
            'title' => 'تحدي للإنهاء', 'description' => 'د', 'creator_id' => $creator->id, 'challenge_type' => Challenge::TYPE_BOOKS_COUNT,
            'target_count' => 3, 'target_unit' => 'كتب', 'status' => 'active', 'is_active' => true,
        ]);
        $challenge->participants()->attach($creator->id, ['progress_count' => 2, 'points_earned' => 0, 'completed' => false, 'joined_at' => now()]);
        $challenge->participants()->attach($reader->id, ['progress_count' => 1, 'points_earned' => 0, 'completed' => false, 'joined_at' => now()]);

        $this->actingAs($reader)->post(route('challenges.updateStatus', $challenge->id), ['status' => 'completed'])->assertForbidden();
        $this->assertSame('active', $challenge->fresh()->status);

        $this->actingAs($creator)->get(route('challenges.index'))->assertSee('إنهاء التحدي');
        $this->actingAs($creator)->post(route('challenges.updateStatus', $challenge->id), ['status' => 'completed'])->assertSessionHas('success');

        $this->assertSame('completed', $challenge->fresh()->status);
        $this->assertSame(1, UserNotification::where('user_id', $reader->id)->where('title', 'like', 'انتهى التحدي%')->count());
    }

    public function test_book_search_endpoint_filters_by_query_and_prefers_my_books(): void
    {
        $user = User::factory()->create();
        $mine = Book::create(['title' => 'زاي كتابي الخاص', 'author' => 'مؤلف أ', 'category' => 'أدب', 'total_pages' => 90]);
        $other = Book::create(['title' => 'ألف كتاب غيري', 'author' => 'مؤلف ب', 'category' => 'أدب', 'total_pages' => 90]);
        UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $mine->id, 'status' => 'want_to_read', 'current_page' => 0, 'progress_percentage' => 0, 'is_active' => false]);

        $filtered = $this->actingAs($user)->getJson(route('books.search', ['q' => 'غيري']))->assertOk()->json('books');
        $this->assertSame([$other->id], array_column($filtered, 'id'));

        $empty = $this->actingAs($user)->getJson(route('books.search'))->assertOk()->json('books');
        $this->assertSame($mine->id, $empty[0]['id']);
        $this->assertTrue($empty[0]['mine']);

        $this->getJson(route('books.search', ['q' => 'كتاب']))->assertOk();
    }

    public function test_specific_book_challenge_requires_book(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);
        $book = Book::create(['title' => 'كتاب التحدي المحدد', 'author' => 'م', 'category' => 'أدب', 'total_pages' => 100]);
        $base = ['title' => 'تحدي كتاب', 'description' => 'د', 'target_count' => 1];

        $this->actingAs($creator)->post(route('challenges.store'), $base + ['challenge_type' => Challenge::TYPE_SPECIFIC_BOOK])
            ->assertSessionHasErrors('specific_book_id');

        $this->actingAs($creator)->post(route('challenges.store'), $base + ['challenge_type' => Challenge::TYPE_BOOKS_COUNT, 'specific_book_id' => $book->id])
            ->assertSessionHasNoErrors();
        $created = Challenge::where('title', 'تحدي كتاب')->firstOrFail();
        $this->assertSame($book->id, $created->specific_book_id);
    }

    public function test_cannot_create_challenge_in_a_club_you_cannot_manage(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Community::create([
            'name' => 'نادي مغلق', 'category' => 'فكر', 'description' => 'د', 'cover_color' => '#173D36',
            'creator_id' => $owner->id, 'type' => 'public', 'language' => 'العربية', 'city' => 'الرياض', 'max_members' => 10,
        ]);

        $this->actingAs($outsider)->post(route('challenges.store'), [
            'title' => 'تحدي دخيل', 'description' => 'د', 'challenge_type' => Challenge::TYPE_BOOKS_COUNT,
            'target_count' => 1, 'community_id' => $club->id,
        ])->assertForbidden();
        $this->assertSame(0, Challenge::where('title', 'تحدي دخيل')->count());
    }

    public function test_club_challenge_notifies_members_only_not_followers(): void
    {
        $creator = User::factory()->create(['role' => 'super_admin']);
        $member = User::factory()->create();
        $follower = User::factory()->create();
        $follower->follow($creator);

        $club = Community::create([
            'name' => 'نادي الفلسفة', 'category' => 'فكر', 'description' => 'نادٍ تجريبي',
            'cover_color' => '#173D36', 'creator_id' => $creator->id, 'type' => 'public',
            'language' => 'العربية', 'city' => 'الرياض', 'max_members' => 50,
        ]);
        $club->members()->attach($creator->id, ['role' => 'owner', 'joined_at' => now()]);
        $club->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $challenge = Challenge::create([
            'title' => 'تحدي النادي', 'description' => 'وصف', 'community_id' => $club->id,
            'creator_id' => $creator->id, 'challenge_type' => Challenge::TYPE_BOOKS_COUNT,
            'target_count' => 2, 'status' => 'active', 'is_active' => true,
        ]);

        $count = $challenge->notifyAudience($creator);

        $this->assertSame(1, $count);
        $this->assertSame(1, UserNotification::where('user_id', $member->id)->where('type', 'challenge')->count());
        $this->assertSame(0, UserNotification::where('user_id', $follower->id)->where('type', 'challenge')->count());
        $this->assertSame(0, UserNotification::where('user_id', $creator->id)->where('type', 'challenge')->count());
    }
}
