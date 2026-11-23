<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Challenge;
use App\Models\ChallengeTeam;
use App\Models\ReadingSession;
use App\Models\User;
use App\Models\UserCurrentBook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReadingChallengesAndReadingSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Book $book;

    protected Challenge $teamChallenge;

    protected ChallengeTeam $teamA;

    protected ChallengeTeam $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'فارس القراءة',
            'points' => 100,
        ]);

        $this->book = Book::create([
            'title' => 'مقدمة ابن خلدون',
            'author' => 'ابن خلدون',
            'category' => 'فكر',
            'total_pages' => 600,
            'cover_color' => '#245E50',
        ]);

        // Create active Team Challenge
        $this->teamChallenge = Challenge::create([
            'title' => 'تحدي المجالس الكبرى: مجلس الأدب ضد رواد الفكر',
            'description' => 'سباق جماعي لقراءة 5000 صفحة مشتركة',
            'status' => Challenge::STATUS_ACTIVE,
            'is_active' => true,
            'is_team' => true,
            'challenge_type' => Challenge::TYPE_COMMUNITY_VS_COMMUNITY,
            'team_a_name' => 'مجلس الأدب',
            'team_b_name' => 'رواد الفكر',
            'team_a_pages' => 0,
            'team_b_pages' => 0,
            'target_count' => 5000,
            'target_unit' => 'صفحة',
            'reward_points' => 300,
        ]);

        $this->teamA = ChallengeTeam::create([
            'challenge_id' => $this->teamChallenge->id,
            'name' => 'مجلس الأدب',
            'color' => '#173D36',
            'total_progress' => 0,
            'total_points' => 0,
        ]);

        $this->teamB = ChallengeTeam::create([
            'challenge_id' => $this->teamChallenge->id,
            'name' => 'رواد الفكر',
            'color' => '#C3A15D',
            'total_progress' => 0,
            'total_points' => 0,
        ]);

        // Enroll user in Team A
        $this->teamChallenge->assignUserToTeam($this->user, $this->teamA);

        // Put book in user's reading log
        UserCurrentBook::create([
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'current_page' => 10,
            'status' => 'currently_reading',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Reading session stored in /reading automatically updates the team's challenge progress.
     */
    public function test_reading_session_in_reading_log_automatically_credits_team_challenge(): void
    {
        $this->actingAs($this->user);

        // Record a reading session of 45 pages in /reading
        $response = $this->post(route('reading.storeSession'), [
            'book_id' => $this->book->id,
            'page' => 55, // 55 - 10 = 45 pages read
            'minutes' => 70,
            'device' => 'كتاب ورقي',
            'note' => 'قراءة فصل العمران البشري',
        ]);

        $response->assertSessionHas('success');

        // Check reading session exists in reading log
        $this->assertDatabaseHas('reading_sessions', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'pages_read' => 45,
        ]);

        // Check that team challenge and Team A progress were updated automatically!
        $this->teamChallenge->refresh();
        $this->teamA->refresh();

        $this->assertEquals(45, $this->teamChallenge->team_a_pages);
        $this->assertEquals(45, $this->teamA->total_progress);

        // Check that challenge progress log references the reading session
        $this->assertDatabaseHas('challenge_progress_logs', [
            'challenge_id' => $this->teamChallenge->id,
            'user_id' => $this->user->id,
            'team_id' => $this->teamA->id,
            'increment_value' => 45,
            'event_type' => 'pages_read',
        ]);
    }

    /**
     * Test 2: Uncredited reading sessions from reading log can be synchronized via syncReading endpoint.
     */
    public function test_uncredited_reading_sessions_can_be_synced_to_challenge(): void
    {
        $this->actingAs($this->user);

        // Manually create an uncredited reading session in reading log
        $session = ReadingSession::create([
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(1),
            'start_page' => 55,
            'end_page' => 105,
            'current_page' => 105,
            'pages_read' => 50,
            'minutes' => 60,
            'device' => 'كتاب ورقي',
            'note' => 'جلسة غير متزامنة مسبقاً',
        ]);

        // Challenge should have 50 pending pages
        $pending = $this->teamChallenge->getPendingReadingPagesForUser($this->user);
        $this->assertEquals(50, $pending);

        // Sync via endpoint
        $syncResponse = $this->post(route('challenges.syncReading', $this->teamChallenge->id));
        $syncResponse->assertSessionHas('success');

        $this->teamChallenge->refresh();
        $this->teamA->refresh();

        $this->assertEquals(50, $this->teamChallenge->team_a_pages);
        $this->assertEquals(50, $this->teamA->total_progress);

        // Pending pages should now be 0
        $this->assertEquals(0, $this->teamChallenge->getPendingReadingPagesForUser($this->user));
    }

    /**
     * Test 3: Anti-gaming blocks anomalous speed (e.g. 1000 pages in 5 minutes).
     */
    public function test_anti_gaming_blocks_anomalous_reading_speed_in_challenge(): void
    {
        $this->actingAs($this->user);

        // Try to log 1000 pages in 5 minutes (speed = 200 pages/min > 3 pages/min)
        $response = $this->post(route('challenges.logProgress', $this->teamChallenge->id), [
            'book_id' => $this->book->id,
            'pages' => 1000,
            'minutes' => 5,
        ]);

        $response->assertSessionHasErrors('pages');

        // Verify team pages were NOT incremented
        $this->teamChallenge->refresh();
        $this->assertEquals(0, $this->teamChallenge->team_a_pages);
    }

    /**
     * Test 4: Logging a valid session from challenges modal creates a real ReadingSession and updates team score.
     */
    public function test_logging_valid_session_from_challenge_modal_creates_reading_session(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('challenges.logProgress', $this->teamChallenge->id), [
            'book_id' => $this->book->id,
            'pages' => 30,
            'minutes' => 45,
            'device' => 'كتاب ورقي',
            'notes' => 'جلسة موثقة لصالح مجلس الأدب',
        ]);

        $response->assertSessionHas('success');

        // Check real ReadingSession was created in reading log
        $this->assertDatabaseHas('reading_sessions', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'pages_read' => 30,
            'minutes' => 45,
        ]);

        // Check team score updated
        $this->teamChallenge->refresh();
        $this->teamA->refresh();

        $this->assertEquals(30, $this->teamChallenge->team_a_pages);
        $this->assertEquals(30, $this->teamA->total_progress);
    }

    /**
     * Test 5: Book completion in reading log automatically increments books-count challenges.
     */
    public function test_book_completion_in_reading_log_syncs_to_book_count_challenges(): void
    {
        $bookChallenge = Challenge::create([
            'title' => 'تحدي قراءة 5 كتب معتمدة',
            'description' => 'تحدي قراءة وإتمام 5 كتب فكرية معتمدة',
            'status' => Challenge::STATUS_ACTIVE,
            'is_active' => true,
            'challenge_type' => Challenge::TYPE_BOOKS_COUNT,
            'target_count' => 5,
            'target_unit' => 'كتاب',
        ]);

        $this->actingAs($this->user);
        $this->post(route('challenges.join', $bookChallenge->id));

        // Complete the book in /reading
        $this->post(route('reading.updateBookStatus'), [
            'book_id' => $this->book->id,
            'status' => 'completed',
        ]);

        // Check that book challenge received the completion
        $participant = $bookChallenge->participants()->where('user_id', $this->user->id)->first();
        $this->assertNotNull($participant);
        $this->assertEquals(1, $participant->pivot->progress_count);
    }
}
