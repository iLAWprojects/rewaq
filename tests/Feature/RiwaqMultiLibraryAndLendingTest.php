<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookWaitlist;
use App\Models\Library;
use App\Models\LibraryMember;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqMultiLibraryAndLendingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;

    protected User $reader;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('enable_book_lending', '1', 'boolean', 'lending');
        $this->owner = User::where('email', 'admin@riwaq.test')->first() ?? User::factory()->create();
        $this->reader = User::where('email', 'demo@riwaq.test')->first() ?? User::factory()->create();
    }

    public function test_user_can_create_new_library_and_becomes_owner(): void
    {
        $response = $this->actingAs($this->owner)->post('/libraries', [
            'name' => 'خزانة النادي الأدبي '.uniqid(),
            'description' => 'مكتبة مخصصة لنوادي القراءة وتبادل المخطوطات',
            'type' => 'club',
            'cover_color' => '#245E50',
        ]);

        $response->assertSessionHas('success');

        $lib = Library::where('name', 'like', 'خزانة النادي الأدبي%')->latest()->first();
        $this->assertNotNull($lib);
        $this->assertEquals('club', $lib->type);
        $this->assertNotNull($lib->code);

        // Verify owner membership
        $member = LibraryMember::where('library_id', $lib->id)->where('user_id', $this->owner->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals('owner', $member->role);
    }

    public function test_user_can_join_library_using_invite_code(): void
    {
        $lib = Library::create([
            'user_id' => $this->owner->id,
            'name' => 'مكتبة الفكر '.uniqid(),
            'code' => 'LIB-TEST-'.rand(1000, 9999),
            'type' => 'club',
        ]);

        $response = $this->actingAs($this->reader)->post('/libraries/join-by-code', [
            'code' => $lib->code,
        ]);

        $response->assertRedirect('/libraries?library_id='.$lib->id);
        $response->assertSessionHas('success');

        $member = LibraryMember::where('library_id', $lib->id)->where('user_id', $this->reader->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals('member', $member->role);
    }

    public function test_library_manager_can_add_member_and_update_role(): void
    {
        $lib = Library::create([
            'user_id' => $this->owner->id,
            'name' => 'مكتبة التراجم '.uniqid(),
            'type' => 'club',
        ]);

        LibraryMember::create([
            'library_id' => $lib->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        // Add reader as librarian
        $response = $this->actingAs($this->owner)->post("/libraries/{$lib->id}/members", [
            'user_id' => $this->reader->id,
            'role' => 'librarian',
        ]);

        $response->assertSessionHas('success');

        $member = LibraryMember::where('library_id', $lib->id)->where('user_id', $this->reader->id)->first();
        $this->assertEquals('librarian', $member->role);

        // Update role to member
        $updateResp = $this->actingAs($this->owner)->put("/libraries/{$lib->id}/members/{$member->id}", [
            'role' => 'member',
        ]);
        $updateResp->assertSessionHas('success');

        $member->refresh();
        $this->assertEquals('member', $member->role);
    }

    public function test_physical_copy_creation_with_condition_and_shelf(): void
    {
        $lib = Library::create([
            'user_id' => $this->owner->id,
            'name' => 'مكتبة الآداب '.uniqid(),
            'type' => 'personal',
        ]);

        $book = Book::first();

        $response = $this->actingAs($this->owner)->post('/libraries/books', [
            'existing_book_id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'category' => $book->category,
            'library_id' => $lib->id,
            'condition' => 'mint',
            'shelf_location' => 'الرف الذهبي - ز1',
            'condition_notes' => 'طبعة أولى فاخرة ومجلدة',
        ]);

        $response->assertSessionHas('success');

        $copy = BookCopy::where('library_id', $lib->id)->where('book_id', $book->id)->latest()->first();
        $this->assertNotNull($copy);
        $this->assertEquals('mint', $copy->condition);
        $this->assertEquals('الرف الذهبي - ز1', $copy->shelf_location);
        $this->assertEquals('طبعة أولى فاخرة ومجلدة', $copy->condition_notes);
    }

    public function test_loan_due_date_extension_and_renewal_limit(): void
    {
        $book = Book::first();
        $lib = Library::first();

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $lib->id,
            'barcode' => 'TEST-EXT-'.uniqid(),
            'status' => 'borrowed',
            'condition' => 'good',
        ]);

        $initialDueDate = Carbon::today()->addDays(5);
        $loan = Loan::create([
            'book_copy_id' => $copy->id,
            'user_id' => $this->reader->id,
            'status' => 'active',
            'borrowed_at' => Carbon::now()->subDays(9),
            'due_date' => $initialDueDate,
            'renewal_count' => 0,
            'max_renewals' => 2,
        ]);

        // 1. Borrower extends loan
        $response = $this->actingAs($this->reader)->post("/libraries/loans/{$loan->id}/extend");
        $response->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals(1, $loan->renewal_count);
        $this->assertTrue($loan->due_date->greaterThan($initialDueDate));

        // 2. Second extension
        $this->actingAs($this->reader)->post("/libraries/loans/{$loan->id}/extend");
        $loan->refresh();
        $this->assertEquals(2, $loan->renewal_count);

        // 3. Third extension MUST FAIL (limit reached)
        $limitResponse = $this->actingAs($this->reader)->post("/libraries/loans/{$loan->id}/extend");
        $limitResponse->assertSessionHas('error');
    }

    public function test_return_copy_updates_condition_and_inspects_notes(): void
    {
        $book = Book::first();
        $lib = Library::first();

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $lib->id,
            'barcode' => 'TEST-RET-'.uniqid(),
            'status' => 'borrowed',
            'condition' => 'mint',
        ]);

        $loan = Loan::create([
            'book_copy_id' => $copy->id,
            'user_id' => $this->reader->id,
            'status' => 'active',
            'borrowed_at' => Carbon::now()->subDays(5),
            'due_date' => Carbon::now()->addDays(9),
        ]);

        // Librarian confirms return and inspects condition
        $response = $this->actingAs($this->owner)->post("/libraries/loans/{$loan->id}/status", [
            'status' => 'returned',
            'return_condition' => 'fair',
            'return_notes' => 'ظهور انثناء خفيف في صفحة الغلاف الخارجي',
        ]);

        $response->assertSessionHas('success');

        $loan->refresh();
        $copy->refresh();

        $this->assertEquals('returned', $loan->status);
        $this->assertEquals('fair', $loan->return_condition);
        $this->assertEquals('fair', $copy->condition);
        $this->assertEquals('available', $copy->status);
    }

    public function test_waitlist_queues_and_auto_reservation_on_return(): void
    {
        $book = Book::first();
        $lib = Library::create([
            'user_id' => $this->owner->id,
            'name' => 'مكتبة الانتظار '.uniqid(),
            'type' => 'club',
        ]);

        LibraryMember::create([
            'library_id' => $lib->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);
        LibraryMember::create([
            'library_id' => $lib->id,
            'user_id' => $this->reader->id,
            'role' => 'member',
        ]);

        // Copy is currently borrowed by someone else
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $lib->id,
            'barcode' => 'TEST-WL-'.uniqid(),
            'status' => 'borrowed',
            'condition' => 'good',
        ]);

        $activeLoan = Loan::create([
            'book_copy_id' => $copy->id,
            'user_id' => $this->owner->id,
            'status' => 'active',
            'due_date' => Carbon::now()->addDays(10),
        ]);

        // Reader joins waitlist for this book in this library
        $wlResponse = $this->actingAs($this->reader)->post("/libraries/{$lib->id}/books/{$book->id}/waitlist");
        $wlResponse->assertSessionHas('success');

        $waitlist = BookWaitlist::where('library_id', $lib->id)->where('book_id', $book->id)->where('user_id', $this->reader->id)->first();
        $this->assertNotNull($waitlist);
        $this->assertEquals('waiting', $waitlist->status);

        // When the active loan is returned, waitlist triggers and reserves copy for reader!
        $this->actingAs($this->owner)->post("/libraries/loans/{$activeLoan->id}/status", [
            'status' => 'returned',
            'return_condition' => 'good',
        ]);

        $copy->refresh();
        $waitlist->refresh();

        $this->assertEquals('reserved', $copy->status); // Reserved for waitlist user!
        $this->assertEquals('notified', $waitlist->status);
    }

    public function test_all_library_tabs_render_cleanly(): void
    {
        $tabs = ['books', 'loans', 'waitlists', 'members', 'all_libraries'];

        foreach ($tabs as $tab) {
            $response = $this->actingAs($this->reader)->get("/libraries?tab={$tab}");
            $response->assertStatus(200);
        }
    }
}
