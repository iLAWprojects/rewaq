<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use App\Models\UserCurrentBook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqExploreCatalogDeduplicationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1. فحص تطبيع العناوين العربية (Arabic Title Normalization)
     */
    public function test_book_title_normalization(): void
    {
        $this->assertEquals('وحي القلم', Book::normalizeTitle('وَحْيُ الْقَلَمِ'));
        $this->assertEquals('وحي القلم', Book::normalizeTitle('  وحـي القلـم  '));
        $this->assertEquals('وحي القلم', Book::normalizeTitle('وحى القلم'));
        $this->assertEquals('الايام', Book::normalizeTitle('الأيّامُ'));
        $this->assertEquals('الايام', Book::normalizeTitle('إلآيـام'));
        $this->assertEquals('مقدمه ابن خلدون', Book::normalizeTitle('مُقَدِّمَةُ ابْنِ خَلْدُونَ'));
        $this->assertEquals('مقدمه ابن خلدون', Book::normalizeTitle('مقدمة ابن خلدون'));
    }

    /**
     * 2. العثور على الكتاب المطابق بمرونة ومنع التكرار (findMatching)
     */
    public function test_book_find_matching(): void
    {
        $canonical = Book::where('title', 'وحي القلم')->first();
        $this->assertNotNull($canonical);

        // Matching with exact
        $found1 = Book::findMatching('وحي القلم', 'مصطفى صادق الرافعي');
        $this->assertEquals($canonical->id, $found1->id);

        // Matching with Alef Maqsura
        $found2 = Book::findMatching('وحى القلم');
        $this->assertEquals($canonical->id, $found2->id);

        // Matching with Tashkeel
        $found3 = Book::findMatching('وَحْيُ الْقَلَمِ');
        $this->assertEquals($canonical->id, $found3->id);
    }

    /**
     * 3. صفحة /explore تعرض كل كتاب مرة واحدة متفردة دون أي تكرار
     */
    public function test_explore_page_displays_unique_books_without_duplicates(): void
    {
        $response = $this->get(route('explore'));
        $response->assertStatus(200);

        $books = $response->viewData('books');
        $this->assertNotNull($books);

        // Verify that all returned books have unique normalized titles
        $normalizedTitles = $books->map(fn ($b) => Book::normalizeTitle($b->title));
        $this->assertEquals($normalizedTitles->count(), $normalizedTitles->unique()->count(), 'Every book on explore must have a unique title');
    }

    /**
     * 4. اختبار أمر riwaq:deduplicate-books لدمج الكتب المكررة ونقل النسخ وسجلات القراءة
     */
    public function test_deduplicate_command_merges_duplicates_and_repoints_relations(): void
    {
        $canonical = Book::where('title', 'وحي القلم')->first();
        $this->assertNotNull($canonical);

        // Create a duplicate book deliberately
        $duplicate = Book::create([
            'title' => 'وحـيُ القلمِ',
            'author' => 'الرافعي',
            'category' => 'أدب',
            'total_pages' => 280,
            'cover_color' => '#123456',
        ]);

        $user = User::factory()->create();
        $library = Library::firstOrCreate(
            ['user_id' => $user->id, 'is_personal' => true],
            ['name' => 'مكتبة الاختبار', 'code' => 'LIB-TEST-DEDUP', 'type' => 'personal']
        );

        // Attach a copy to the duplicate book
        $copy = BookCopy::create([
            'book_id' => $duplicate->id,
            'library_id' => $library->id,
            'copy_number' => '1',
            'status' => 'available',
        ]);

        // Attach a reading record to the duplicate book
        $userBook = UserCurrentBook::create([
            'user_id' => $user->id,
            'book_id' => $duplicate->id,
            'current_page' => 50,
            'status' => 'currently_reading',
            'is_active' => true,
        ]);

        // Run the deduplicate command
        $this->artisan('riwaq:deduplicate-books')
            ->assertExitCode(0);

        // Verify the duplicate was removed and relationships were transferred
        $this->assertDatabaseMissing('books', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'book_id' => $canonical->id,
        ]);
        $this->assertDatabaseHas('user_current_books', [
            'id' => $userBook->id,
            'book_id' => $canonical->id,
        ]);
    }

    public function test_explore_ajax_filters_by_title_author_publisher_and_category(): void
    {
        $tag = uniqid();
        Book::create(['title' => "رواية الأمل $tag", 'author' => "نجيب $tag", 'publisher' => "دار الشروق $tag", 'category' => 'أدب', 'total_pages' => 100]);
        Book::create(['title' => "تاريخ العرب $tag", 'author' => "حتي $tag", 'publisher' => "دار المعارف $tag", 'category' => 'تاريخ', 'total_pages' => 100]);

        $ajax = fn (array $q) => $this->getJson(route('explore', $q + ['title' => $tag]))->assertOk()->json();

        $this->assertSame(2, $ajax([])['count']);
        $byTitle = $this->getJson(route('explore', ['title' => "الأمل $tag"]))->assertOk()->json();
        $this->assertSame(1, $byTitle['count']);
        $this->assertStringContainsString("رواية الأمل $tag", $byTitle['html']);

        $this->assertSame(1, $this->getJson(route('explore', ['author' => "حتي $tag"]))->json('count'));
        $this->assertSame(1, $this->getJson(route('explore', ['publisher' => "الشروق $tag"]))->json('count'));
        $this->assertSame(1, $this->getJson(route('explore', ['title' => $tag, 'category' => 'تاريخ']))->json('count'));
        $this->assertSame(0, $this->getJson(route('explore', ['title' => $tag, 'publisher' => 'لا يوجد']))->json('count'));

        $this->get(route('explore'))->assertOk()->assertSee('id="ex-filter"', false)->assertSee('دار النشر');
    }
}
