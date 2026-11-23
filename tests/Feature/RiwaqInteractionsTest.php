<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookPoll;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Meeting;
use App\Models\User;
use App\Models\UserCurrentBook;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqInteractionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_record_reading_session_updates_progress_and_points(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $currentBook = UserCurrentBook::where('user_id', $user->id)->first();
        $currentBook->update(['current_page' => 50]);
        $initialPage = 50;
        $initialPoints = $user->points;

        $response = $this->actingAs($user)->post('/reading/sessions', [
            'page' => $initialPage + 10,
            'minutes' => 20,
            'note' => 'ملاحظة اختبار الجلسة التفاعلية',
        ]);

        $response->assertRedirect('/reading');

        $currentBook->refresh();
        $user->refresh();

        $this->assertEquals($initialPage + 10, $currentBook->current_page);
        $this->assertGreaterThan($initialPoints, $user->points);
    }

    public function test_request_and_approve_book_loan(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $book = Book::create([
            'title' => 'كتاب الإعارة الفريد '.uniqid(),
            'author' => 'مؤلف معتمد',
            'category' => 'أدب',
            'total_pages' => 200,
        ]);
        $library = Library::first();

        // Dedicated copy for this test run
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => (string) rand(1000, 9999),
            'status' => 'available',
            'barcode' => 'TEST-'.uniqid(),
        ]);

        // Request loan (by another reader: owners cannot borrow from their own library)
        $borrower = User::where('role', 'super_admin')->where('id', '!=', $library->user_id)->first();
        $response = $this->actingAs($borrower)->post("/libraries/copies/{$copy->id}/loan");
        $response->assertSessionHas('success');

        $copy->refresh();
        $this->assertEquals('reserved', $copy->status);

        $loan = Loan::where('book_copy_id', $copy->id)->latest()->first();
        $this->assertEquals('pending', $loan->status);

        // Approve loan
        $response = $this->actingAs($user)->post("/libraries/loans/{$loan->id}/status", [
            'status' => 'active',
        ]);
        $response->assertSessionHas('success');

        $copy->refresh();
        $this->assertEquals('borrowed', $copy->status);
    }

    public function test_cannot_borrow_the_same_book_more_than_once(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $book = Book::create([
            'title' => 'كتاب منع تكرار الإعارة '.uniqid(),
            'author' => 'مؤلف تجريبي',
            'category' => 'فكر',
            'total_pages' => 150,
        ]);
        $library = Library::first();

        $copy1 = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => '1',
            'status' => 'available',
            'barcode' => 'TEST-CP1-'.uniqid(),
        ]);

        $copy2 = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => '2',
            'status' => 'available',
            'barcode' => 'TEST-CP2-'.uniqid(),
        ]);

        $borrower = User::where('role', 'super_admin')->where('id', '!=', $library->user_id)->first();

        // First loan request for this book should succeed
        $response1 = $this->actingAs($borrower)->post("/libraries/copies/{$copy1->id}/loan");
        $response1->assertSessionHas('success');

        // Second loan request for the SAME book (different copy) MUST FAIL
        $response2 = $this->actingAs($borrower)->post("/libraries/copies/{$copy2->id}/loan");
        $response2->assertSessionHas('error');
    }

    public function test_search_existing_books_ajax_and_duplicate_prevention(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        // كتاب موجود في الفهرس وليس في مكتبة المستخدم الشخصية (المكتبة الشخصية ترفض تكرار الكتاب نفسه)
        $personalLibraryId = $user->getPersonalLibrary()->id;
        $existingBook = Book::whereDoesntHave('copies', fn ($q) => $q->where('library_id', $personalLibraryId))->first()
            ?? Book::create(['title' => 'كتاب فهرس تجريبي', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 100]);

        // 1. Test Ajax search finds existing book
        $response = $this->actingAs($user)->getJson('/libraries/books/search-existing?q='.urlencode(mb_substr($existingBook->title, 0, 3)));
        $response->assertStatus(200);
        $response->assertJsonStructure(['books', 'count']);
        $this->assertGreaterThan(0, $response->json('count'));

        // 2. Test storeBook with existing book ID adds copy without duplicate Book model
        $initialBookCount = Book::count();
        $response = $this->actingAs($user)->post('/libraries/books', [
            'existing_book_id' => $existingBook->id,
            'title' => $existingBook->title,
            'author' => $existingBook->author,
            'category' => $existingBook->category,
            'shelf_location' => 'رف التجربة',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals($initialBookCount, Book::count());
    }

    public function test_poll_voting_enforces_single_vote_per_user(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $poll = BookPoll::first();
        $option1 = $poll->options->first();
        $option2 = $poll->options->last();

        // Vote for option 1
        $this->actingAs($user)->post("/polls/{$poll->id}/vote", [
            'option_id' => $option1->id,
        ]);

        $this->assertEquals(1, $poll->votes()->where('user_id', $user->id)->count());

        // Change vote to option 2
        $this->actingAs($user)->post("/polls/{$poll->id}/vote", [
            'option_id' => $option2->id,
        ]);

        // Still only 1 vote record exists, pointing to option 2
        $this->assertEquals(1, $poll->votes()->where('user_id', $user->id)->count());
        $this->assertEquals($option2->id, $poll->votes()->where('user_id', $user->id)->first()->book_poll_option_id);
    }

    public function test_meeting_rsvp_toggle(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $meeting = Meeting::first();

        $response = $this->actingAs($user)->post("/calendar/meetings/{$meeting->id}/rsvp");
        $response->assertSessionHas('success');

        $this->assertTrue($meeting->rsvps()->where('user_id', $user->id)->exists());
    }

    public function test_join_reading_challenge(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $challenge = Challenge::first();

        $response = $this->actingAs($user)->post("/challenges/{$challenge->id}/join");
        $response->assertSessionHas('success');

        $this->assertTrue($challenge->participants()->where('user_id', $user->id)->exists());
    }

    public function test_community_join_via_invite_link_and_code(): void
    {
        $community = Community::first();
        $this->assertNotEmpty($community->code);

        // Guest redirected to login
        $response = $this->get("/communities/join/{$community->code}");
        $response->assertRedirect('/login');

        // Create a new user to test joining
        $newUser = User::factory()->create([
            'email' => 'newreader_'.uniqid().'@riwaq.test',
        ]);

        $this->assertFalse($community->members()->where('user_id', $newUser->id)->exists());

        // Join via link
        $response = $this->actingAs($newUser)->get("/communities/join/{$community->code}");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($community->members()->where('user_id', $newUser->id)->exists());
    }

    public function test_community_join_by_code_form(): void
    {
        $community = Community::first();
        $newUser = User::factory()->create([
            'email' => 'code_reader_'.uniqid().'@riwaq.test',
        ]);

        // Valid code
        $response = $this->actingAs($newUser)->post('/communities/join-by-code', [
            'code' => $community->code,
        ]);
        $response->assertSessionHas('success');
        $this->assertTrue($community->members()->where('user_id', $newUser->id)->exists());

        // Invalid code
        $response = $this->actingAs($newUser)->post('/communities/join-by-code', [
            'code' => 'INVALID-CODE-999',
        ]);
        $response->assertSessionHas('error');
    }

    public function test_send_community_invitation_creates_notification(): void
    {
        $sender = User::where('email', 'demo@riwaq.test')->first();
        $recipient = User::where('email', 'admin@riwaq.test')->first();
        $community = Community::first();

        $initialNotificationCount = UserNotification::where('user_id', $recipient->id)->count();

        $response = $this->actingAs($sender)->post("/communities/{$community->id}/invite", [
            'recipient' => $recipient->email,
            'custom_message' => 'تعال وانضم إلينا!',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals(
            $initialNotificationCount + 1,
            UserNotification::where('user_id', $recipient->id)->count()
        );

        $latestNotif = UserNotification::where('user_id', $recipient->id)->latest()->first();
        $this->assertEquals('community_invite', $latestNotif->type);
        $this->assertStringContainsString($community->code, $latestNotif->body);
    }

    public function test_user_can_delete_available_copy_from_library(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $lib = Library::where('user_id', $user->id)->first();
        $copy = $lib->copies()->where('status', 'available')->first();
        $this->assertNotNull($copy);

        $response = $this->actingAs($user)->delete("/libraries/copies/{$copy->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('book_copies', [
            'id' => $copy->id,
        ]);
    }

    public function test_user_can_store_book_with_camscanner_enhanced_cover(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        // 1x1 transparent PNG as base64
        $fakeBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $title = 'كتاب مصور بماسح رِواق '.uniqid();
        $response = $this->actingAs($user)->post('/libraries/books', [
            'title' => $title,
            'author' => 'مؤلف تجريبي',
            'category' => 'فكر',
            'total_pages' => 310,
            'shelf_location' => 'الرف الذكي',
            'cover_image_enhanced' => $fakeBase64,
        ]);

        $response->assertRedirect('/libraries?tab=books');
        $response->assertSessionHas('success');

        $book = Book::where('title', $title)->first();
        $this->assertNotNull($book);
        $this->assertNotNull($book->cover_image);
        $this->assertStringStartsWith('uploads/covers/', $book->cover_image);
        $this->assertFileExists(public_path($book->cover_image));

        // Clean up created file
        if (file_exists(public_path($book->cover_image))) {
            @unlink(public_path($book->cover_image));
        }
    }

    public function test_libraries_view_renders_camscanner_studio_and_inputs(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/libraries');
        $response->assertStatus(200);
        $response->assertSee('camscanner-dropzone', false);
        $response->assertSee('camscanner-studio', false);
        $response->assertSee('book-cover-enhanced-data', false);
        $response->assertSee('camscanner-preset-pill', false);
    }

    public function test_notifications_page_renders_sanctuary_hero_filter_tabs_and_cards(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/notifications');
        $response->assertStatus(200);
        $response->assertSee('nt-head-actions');
        $response->assertSee('pf-tabs');
        $response->assertSee('notif-search-box');
        $response->assertSee('notif-cards-list');
        $response->assertSee('الإشعارات');
        $response->assertSee(url('/libraries?tab=loans'), false);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        UserNotification::create([
            'user_id' => $user->id,
            'title' => 'تنبيه تجريبي للاختبار',
            'body' => 'محتوى التنبيه التجريبي',
            'type' => 'event',
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)->post('/notifications/read-all');
        $response->assertSessionHas('success');

        $unreadCount = UserNotification::where('user_id', $user->id)->where('is_read', false)->count();
        $this->assertEquals(0, $unreadCount);
    }
}
