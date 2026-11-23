<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReview;
use App\Models\ContentReport;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqContentReportFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function makeBook(): Book
    {
        return Book::create(['title' => 'كتاب مخالف', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 100]);
    }

    public function test_reader_can_report_a_book_and_admins_are_notified(): void
    {
        $reader = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $book = $this->makeBook();

        $this->actingAs($reader)->post(route('content.report'), [
            'reportable_type' => 'book',
            'reportable_id' => $book->id,
            'reason' => 'inappropriate',
            'details' => 'محتوى مخالف',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('content_reports', [
            'user_id' => $reader->id,
            'reportable_type' => 'book',
            'reportable_id' => $book->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'type' => 'moderation']);
    }

    public function test_duplicate_pending_report_is_rejected(): void
    {
        $reader = User::factory()->create();
        $book = $this->makeBook();
        $payload = ['reportable_type' => 'book', 'reportable_id' => $book->id, 'reason' => 'spam'];

        $this->actingAs($reader)->post(route('content.report'), $payload)->assertSessionHas('success');
        $this->actingAs($reader)->post(route('content.report'), $payload)->assertSessionHas('error');

        $this->assertSame(1, ContentReport::where('user_id', $reader->id)->count());
    }

    public function test_reader_cannot_report_own_review_or_missing_content(): void
    {
        $reader = User::factory()->create();
        $book = $this->makeBook();
        $review = BookReview::create(['book_id' => $book->id, 'user_id' => $reader->id, 'rating' => 4, 'content' => 'رأيي']);

        $this->actingAs($reader)->post(route('content.report'), [
            'reportable_type' => 'review', 'reportable_id' => $review->id, 'reason' => 'spam',
        ])->assertSessionHas('error');

        $this->actingAs($reader)->post(route('content.report'), [
            'reportable_type' => 'book', 'reportable_id' => 99999999, 'reason' => 'spam',
        ])->assertSessionHas('error');

        $this->assertSame(0, ContentReport::count() - ContentReport::where('user_id', '!=', $reader->id)->count());
    }

    public function test_guest_must_login_to_report(): void
    {
        $book = $this->makeBook();

        $this->post(route('content.report'), [
            'reportable_type' => 'book', 'reportable_id' => $book->id, 'reason' => 'spam',
        ])->assertRedirect(route('login'));
    }

    public function test_admin_resolution_marks_report_and_notifies_reporter(): void
    {
        $reader = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $book = $this->makeBook();
        $report = ContentReport::create([
            'user_id' => $reader->id, 'reportable_type' => 'book', 'reportable_id' => $book->id,
            'reason' => 'inappropriate', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.handleReport', $report->id), ['action' => 'resolve_and_delete'])
            ->assertSessionHas('success');

        $this->assertSame('action_taken', $report->fresh()->status);
        $this->assertNull(Book::find($book->id));
        $this->assertTrue(UserNotification::where('user_id', $reader->id)->where('type', 'moderation')->exists());
    }

    public function test_admin_can_dismiss_report(): void
    {
        $reader = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $book = $this->makeBook();
        $report = ContentReport::create([
            'user_id' => $reader->id, 'reportable_type' => 'book', 'reportable_id' => $book->id,
            'reason' => 'other', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.handleReport', $report->id), ['action' => 'dismiss']);

        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertNotNull(Book::find($book->id));
    }

    public function test_report_ui_is_available_on_book_page_and_modal(): void
    {
        $reader = User::factory()->create();
        $book = $this->makeBook();

        $this->actingAs($reader)->get(route('books.show', $book->id))
            ->assertOk()
            ->assertSee('data-report-type="book"', false)
            ->assertSee('rq-report-modal', false);
    }

    public function test_friend_request_accept_and_unfriend_flow_over_http(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('friends.request', $b->id))->assertSessionHas('success');
        $this->assertTrue(UserNotification::where('user_id', $b->id)->where('type', 'friend_request')->exists());

        $this->actingAs($b)->post(route('friends.accept', $a->id))->assertSessionHas('success');
        $this->assertTrue($a->fresh()->friends()->where('users.id', $b->id)->exists());

        $this->actingAs($a)->post(route('friends.unfriend', $b->id))->assertSessionHas('info');
        $this->assertFalse($a->fresh()->friends()->where('users.id', $b->id)->exists());
    }
}
