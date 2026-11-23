<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use App\Models\UserCurrentBook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqAddToPersonalLibraryAndReadingSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Book $book1;

    protected Book $book2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'user',
            'points' => 100,
        ]);

        $this->book1 = Book::firstOrCreate(
            ['title' => 'وحي القلم'],
            [
                'author' => 'مصطفى صادق الرافعي',
                'category' => 'أدب',
                'total_pages' => 280,
                'cover_color' => '#173D36',
                'description' => 'درة الأدب العربي المعاصر.',
            ]
        );

        $this->book2 = Book::firstOrCreate(
            ['title' => 'سيرة ابن هشام'],
            [
                'author' => 'ابن هشام',
                'category' => 'سيرة',
                'total_pages' => 450,
                'cover_color' => '#245E50',
                'description' => 'السيرة النبوية العطرة.',
            ]
        );
    }

    /**
     * 1. اختبار زر أضف لمكتبتي عبر POST /libraries/add-to-personal
     */
    public function test_user_can_add_book_to_personal_library(): void
    {
        $this->assertFalse($this->user->hasBookInPersonalLibrary($this->book1));

        $response = $this->actingAs($this->user)->post(route('libraries.addToPersonal'), [
            'book_id' => $this->book1->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check copy was created in user's personal library
        $personalLib = $this->user->getPersonalLibrary();
        $this->assertTrue($this->user->hasBookInPersonalLibrary($this->book1));
        $this->assertTrue(BookCopy::where('library_id', $personalLib->id)->where('book_id', $this->book1->id)->exists());

        // Check book was also added to UserCurrentBook (Reading log)
        $this->assertTrue(UserCurrentBook::where('user_id', $this->user->id)->where('book_id', $this->book1->id)->exists());

        // Check points were awarded
        $this->assertEquals(110, $this->user->fresh()->points);

        // Check AuditLog entry
        $this->assertTrue(AuditLog::where('action', 'add_book_to_personal_library')->where('user_id', $this->user->id)->exists());
    }

    /**
     * اختبار استجابة JSON لزر أضف لمكتبتي في المودال 3D
     */
    public function test_add_to_personal_library_returns_json_for_ajax_request(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('libraries.addToPersonal'), [
            'book_id' => $this->book2->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'in_library' => true,
            'book_id' => $this->book2->id,
        ]);

        $this->assertTrue($this->user->hasBookInPersonalLibrary($this->book2));
    }

    /**
     * 2. اختبار صفحة الكتاب /books/{book} وعرض زر أضف لمكتبتي أو حالة الوجود
     */
    public function test_book_show_page_displays_add_to_library_and_in_library_states(): void
    {
        // Initial: Not in library
        $response = $this->actingAs($this->user)->get(route('books.show', $this->book1->id));
        $response->assertStatus(200);
        $response->assertSee('أضف لمكتبتي ✦');

        // Add to library
        $this->user->addBookToPersonalLibrary($this->book1);

        // Reload page: Should now indicate it's already in the personal library
        $response = $this->actingAs($this->user)->get(route('books.show', $this->book1->id));
        $response->assertStatus(200);
        $response->assertSee('موجود في مكتبتك الشخصية ✦');
    }

    /**
     * 3. قاعدة: طالما الكتاب في سجل القراءة /reading يبقى موجود في مكتباتي
     */
    public function test_books_in_reading_log_are_automatically_synced_to_personal_library(): void
    {
        // Add book to reading log
        UserCurrentBook::create([
            'user_id' => $this->user->id,
            'book_id' => $this->book1->id,
            'status' => 'currently_reading',
            'current_page' => 50,
            'progress_percentage' => 18,
            'is_active' => true,
        ]);

        UserCurrentBook::create([
            'user_id' => $this->user->id,
            'book_id' => $this->book2->id,
            'status' => 'want_to_read',
            'current_page' => 0,
            'progress_percentage' => 0,
            'is_active' => false,
        ]);

        // Prior to sync, book copies do not exist yet
        $personalLib = $this->user->getPersonalLibrary();
        BookCopy::where('library_id', $personalLib->id)->delete();

        // 1. Visit /reading
        $response = $this->actingAs($this->user)->get(route('reading.index'));
        $response->assertStatus(200);

        // Books from reading log should now be synced into personal library
        $this->assertTrue($this->user->hasBookInPersonalLibrary($this->book1));
        $this->assertTrue($this->user->hasBookInPersonalLibrary($this->book2));

        // 2. Visit /libraries
        $response = $this->actingAs($this->user)->get(route('libraries.index'));
        $response->assertStatus(200);
        $response->assertSee('وحي القلم');
        $response->assertSee('سيرة ابن هشام');
    }

    /**
     * اختبار إضافة كتاب عبر switchCurrentBook أو updateBookStatus في سجل القراءة ينعكس فورياً في المكتبة
     */
    public function test_switching_or_updating_reading_book_adds_it_to_personal_library(): void
    {
        $book3 = Book::create([
            'title' => 'مقدمة في الفلسفة الإسلامية',
            'author' => 'د. محمد عمارة',
            'category' => 'فكر',
            'total_pages' => 200,
        ]);

        $this->assertFalse($this->user->hasBookInPersonalLibrary($book3));

        // Switch active reading book
        $response = $this->actingAs($this->user)->post(route('reading.switchBook'), [
            'book_id' => $book3->id,
        ]);
        $response->assertRedirect(route('reading.index'));

        // It must now exist in personal library
        $this->assertTrue($this->user->fresh()->hasBookInPersonalLibrary($book3));
    }

    /**
     * اختبار أن صفحة /explore تعرض data-in-library="1" لكتب المستخدم في مكتبته الشخصية بمرونة تامة
     */
    public function test_explore_page_correctly_marks_in_library_for_user_books(): void
    {
        // Add book1 to user's personal library
        $this->user->addBookToPersonalLibrary($this->book1);
        $this->assertTrue($this->user->hasBookInPersonalLibrary($this->book1));

        // Create an alternate edition of book1 with duplicate title to ensure resilience
        $altBook1 = Book::create([
            'title' => 'وَحْيُ الْقَلَمِ',
            'author' => 'الرافعي',
            'category' => 'أدب',
            'total_pages' => 280,
            'cover_color' => '#173D36',
        ]);
        $this->assertTrue($this->user->hasBookInPersonalLibrary($altBook1));

        $response = $this->actingAs($this->user)->get(route('explore'));
        $response->assertStatus(200);

        // Assert that the page rendered data-in-library="1" for book1
        $response->assertSee('data-id="'.$this->book1->id.'"', false);
        $response->assertSee('data-in-library="1"', false);
    }
}
