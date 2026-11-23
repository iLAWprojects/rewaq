<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Community;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Meeting;
use App\Models\Reflection;
use App\Models\User;
use App\Models\UserCurrentBook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReadingWidgetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_pages_show_start_reading_widget_with_current_book_and_log_link(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'كتاب المؤقت', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 200]);
        UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $book->id, 'current_page' => 10, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('libraries.index'));

        $response->assertOk();
        $response->assertSee('id="rq-reading"', false);
        $response->assertSee('ابدأ القراءة');
        $response->assertSee('كتاب المؤقت');
        $response->assertSee(route('reading.index'), false);
        $response->assertSee(route('reading.storeSession'), false);
    }

    public function test_widget_asks_to_pick_a_book_when_there_is_no_current_book(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('explore'));

        $response->assertOk();
        $response->assertSee('id="rq-reading"', false);
        $response->assertSee('لم تحدد كتابًا لتقرأه بعد');
        $response->assertSee('data-rw-change', false)->assertSee('id="rw-switch"', false);
    }

    public function test_widget_is_hidden_from_guests_and_public_landing(): void
    {
        $this->get('/')->assertDontSee('id="rq-reading"', false);
        $this->get(route('explore'))->assertDontSee('id="rq-reading"', false);
    }

    public function test_finishing_a_timed_session_saves_it_with_pages_and_minutes(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'كتاب الجلسة', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 200]);
        UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $book->id, 'current_page' => 10, 'is_active' => true]);

        $this->actingAs($user)->post(route('reading.storeSession'), [
            'book_id' => $book->id,
            'start_page' => 10,
            'page' => 25,
            'minutes' => 12,
            'device' => 'كتاب ورقي',
        ])->assertRedirect();

        $this->assertDatabaseHas('reading_sessions', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'minutes' => 12,
            'end_page' => 25,
            'pages_read' => 15,
        ]);
    }

    public function test_switching_book_with_stay_returns_back_and_widget_shows_new_book(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'كتاب اقرأ الآن', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 120]);

        $this->actingAs($user)->from(route('explore'))
            ->post(route('reading.switchBook'), ['book_id' => $book->id, 'stay' => 1])
            ->assertRedirect(route('explore'));

        $this->assertDatabaseHas('user_current_books', ['user_id' => $user->id, 'book_id' => $book->id, 'is_active' => true]);
        $this->actingAs($user)->get(route('explore'))->assertSee('كتاب اقرأ الآن');
    }

    public function test_book_modal_has_read_now_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('explore'))
            ->assertSee('rq-bm-read-form', false)
            ->assertSee('اقرأ الآن');
    }

    public function test_dashboard_has_mobile_bottom_nav_with_active_item_and_public_pages_do_not(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('explore'))->assertOk()->getContent();

        $this->assertStringContainsString('id="rq-bottom-nav"', $html);
        $this->assertStringContainsString('style="--i: 2;"', $html);
        foreach (['مكتباتي', 'نوادي القراءة', 'الكتب', 'الإشعارات', 'الإعدادات'] as $label) {
            $this->assertStringContainsString('<span>'.$label.'</span>', $html);
        }

        $this->get('/')->assertDontSee('id="rq-bottom-nav"', false);
    }

    public function test_mobile_header_has_global_search_button_and_sheet_for_members_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('libraries.index'))
            ->assertOk()
            ->assertSee('id="rq-search-open"', false)
            ->assertSee('id="rq-search-sheet"', false);

        $this->get('/')->assertDontSee('id="rq-search-sheet"', false);
    }

    public function test_new_account_reading_log_is_empty_and_has_no_fake_book(): void
    {
        $user = User::factory()->create();
        Book::create(['title' => 'وحي القلم', 'author' => 'الرافعي', 'category' => 'أدب', 'total_pages' => 300]);

        $this->actingAs($user)->get(route('reading.index'))->assertOk();

        $this->assertSame(0, UserCurrentBook::where('user_id', $user->id)->count());
    }

    public function test_can_add_a_brand_new_book_to_reading_shelves_and_it_lands_in_personal_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reading.updateBookStatus'), [
            'new_title' => 'كتاب لم يكن في الفهرس',
            'new_author' => 'مؤلف جديد',
            'new_pages' => 150,
            'new_category' => 'فكر',
            'status' => 'want_to_read',
        ])->assertSessionHasNoErrors();

        $book = Book::where('title', 'كتاب لم يكن في الفهرس')->firstOrFail();
        $this->assertSame(150, (int) $book->total_pages);
        $this->assertTrue(UserCurrentBook::where('user_id', $user->id)->where('book_id', $book->id)->where('status', 'want_to_read')->exists());
        $this->assertTrue($user->hasBookInPersonalLibrary($book));

        $this->actingAs($user)->post(route('reading.updateBookStatus'), ['status' => 'want_to_read'])
            ->assertSessionHasErrors(['book_id', 'new_title']);
    }

    public function test_diversity_tab_shows_real_charts_instead_of_raw_formula(): void
    {
        $user = User::factory()->create();
        $a = Book::create(['title' => 'كتاب أدب', 'author' => 'مؤلف 1', 'category' => 'أدب', 'total_pages' => 100]);
        $b = Book::create(['title' => 'كتاب تاريخ', 'author' => 'مؤلف 2', 'category' => 'تاريخ', 'total_pages' => 100]);
        foreach ([$a, $b] as $book) {
            UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'completed', 'current_page' => 100, 'progress_percentage' => 100, 'is_active' => false]);
        }

        $this->actingAs($user)->get(route('reading.index', ['tab' => 'diversity']))
            ->assertOk()
            ->assertSee('بصمة تنوعك المعرفي')
            ->assertSee('توزيع قراءاتك حسب التصنيف')
            ->assertSee('50%')
            ->assertDontSee('\text{KDI}', false);
    }

    public function test_book_filter_limits_every_tab_to_selected_book_of_my_library(): void
    {
        $user = User::factory()->create();
        $a = Book::create(['title' => 'كتاب الألف', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 100]);
        $b = Book::create(['title' => 'كتاب الباء', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 100]);
        foreach ([$a, $b] as $book) {
            UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'want_to_read', 'current_page' => 0, 'progress_percentage' => 0, 'is_active' => false]);
            Reflection::create(['user_id' => $user->id, 'book_id' => $book->id, 'content' => 'اقتباس '.$book->title, 'privacy' => 'private']);
        }

        $page = $this->actingAs($user)->get(route('reading.index', ['tab' => 'quotes', 'book' => $a->id]));
        $page->assertOk()->assertSee('تصفية حسب الكتاب')->assertSee('اقتباس كتاب الألف')->assertDontSee('اقتباس كتاب الباء');
        $page->assertSee('book='.$a->id, false);

        $this->actingAs($user)->get(route('reading.index', ['tab' => 'shelves', 'book' => $a->id]))
            ->assertOk()->assertSee('كتاب الألف');

        // بطاقة «بين يديك الآن» تعرض الكتاب المصفّى وليس الكتاب النشط
        UserCurrentBook::where('user_id', $user->id)->where('book_id', $b->id)->update(['status' => 'currently_reading', 'is_active' => true]);
        UserCurrentBook::where('user_id', $user->id)->where('book_id', $a->id)->update(['is_active' => false]);
        $this->actingAs($user)->get(route('reading.index', ['tab' => 'journey', 'book' => $a->id]))
            ->assertOk()->assertSee('بين يديك الآن')->assertSee('كتاب الألف');

        // كتاب ليس في مكتبتي يُتجاهل فلتره
        $stranger = Book::create(['title' => 'كتاب غريب', 'author' => 'x', 'category' => 'أدب', 'total_pages' => 10]);
        $this->actingAs($user)->get(route('reading.index', ['tab' => 'quotes', 'book' => $stranger->id]))
            ->assertOk()->assertSee('اقتباس كتاب الباء');
    }

    public function test_new_user_sees_no_fake_calendar_commitments_or_presence(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('calendar.index'))->assertOk()
            ->assertSee('لا توجد التزامات قادمة');

        $this->actingAs($user)->get(route('communities.index'))->assertOk()
            ->assertDontSee('16 قارئاً حاضرون الآن')
            ->assertDontSee('سارة أحمد');
    }

    public function test_calendar_shows_only_meetings_of_my_clubs_and_general_meetings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Community::create([
            'name' => 'مجلس خاص بالاختبار', 'category' => 'أدب', 'description' => 'د', 'cover_color' => '#173D36',
            'creator_id' => $owner->id, 'type' => 'public', 'language' => 'العربية', 'city' => 'الرياض', 'max_members' => 10,
        ]);
        $club->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $mk = fn (array $o) => Meeting::create($o + [
            'meeting_date' => now()->addDays(2), 'end_time' => now()->addDays(2)->addHours(2),
            'location' => 'قاعة', 'meeting_type' => 'in_person', 'capacity' => 10, 'is_past' => false,
        ]);
        $mk(['community_id' => $club->id, 'title' => 'لقاء المجلس الخاص']);
        $mk(['community_id' => null, 'title' => 'لقاء عام للجميع']);

        $this->actingAs($member)->get(route('calendar.index', ['view' => 'list']))->assertOk()
            ->assertSee('لقاء المجلس الخاص')->assertSee('لقاء عام للجميع');
        $this->actingAs($outsider)->get(route('calendar.index', ['view' => 'list']))->assertOk()
            ->assertDontSee('لقاء المجلس الخاص')->assertSee('لقاء عام للجميع');
    }

    public function test_diversity_index_is_pure_math_of_real_books_with_no_hidden_floors(): void
    {
        $user = User::factory()->create();
        $mk = fn (string $t, string $a, string $c) => Book::create(['title' => $t, 'author' => $a, 'category' => $c, 'total_pages' => 100, 'language' => 'العربية']);
        $log = fn (Book $b) => UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $b->id, 'status' => 'completed', 'current_page' => 100, 'progress_percentage' => 100, 'is_active' => false]);

        // كتاب واحد = لا تنوع على الإطلاق (بدون حدود دنيا مصطنعة)
        $log($mk('ك1', 'م1', 'أدب'));
        $one = $user->calculateKnowledgeDiversityIndex();
        $this->assertSame([0, 0, 0, 0], [$one['score'], $one['category_score'], $one['author_score'], $one['language_score']]);

        // ثلاثة كتب بتصنيفات ومؤلفين مختلفين وبلغة واحدة
        $log($mk('ك2', 'م2', 'سيرة'));
        $log($mk('ك3', 'م3', 'تاريخ'));
        $three = $user->calculateKnowledgeDiversityIndex();
        $this->assertSame(76, $three['category_score']);
        $this->assertSame(67, $three['author_score']);
        $this->assertSame(0, $three['language_score']);
        $this->assertSame(3, $three['books_count']);

        // كتاب رابع في «أريد قراءته» لا يدخل الحساب لكنه يظهر في العدّاد الكلي للمكتبة
        $wish = $mk('ك4', 'م4', 'فكر');
        UserCurrentBook::create(['user_id' => $user->id, 'book_id' => $wish->id, 'status' => 'want_to_read', 'current_page' => 0, 'progress_percentage' => 0, 'is_active' => false]);
        $this->assertSame(3, $user->calculateKnowledgeDiversityIndex()['books_count']);
        $this->actingAs($user)->get(route('reading.index', ['tab' => 'diversity']))
            ->assertOk()->assertSee('محسوبة من 3 كتب')->assertSee('من أصل 4');
    }

    public function test_new_user_starts_with_zero_reading_streak(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(0, (int) $user->reading_streak);
        $this->actingAs($user)->get(route('reading.index'))->assertOk()->assertDontSee('3 أيام متتالية');
    }

    public function test_deleting_book_from_personal_library_is_permanent_even_if_in_reading_log(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'كتاب للحذف', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 100]);
        UserCurrentBook::create([
            'user_id' => $user->id, 'book_id' => $book->id, 'status' => 'currently_reading',
            'current_page' => 10, 'progress_percentage' => 10, 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('reading.index'))->assertOk();
        $copy = BookCopy::where('library_id', $user->getPersonalLibrary()->id)->where('book_id', $book->id)->firstOrFail();

        $this->actingAs($user)->delete(route('libraries.destroyCopy', $copy->id))->assertSessionHas('success');
        $this->actingAs($user)->get(route('libraries.index'))->assertOk();
        $this->actingAs($user)->get(route('reading.index'))->assertOk();

        $this->assertSame(0, BookCopy::where('library_id', $user->getPersonalLibrary()->id)->where('book_id', $book->id)->count());
    }

    public function test_owner_cannot_borrow_from_own_library_and_ui_hides_loan_button(): void
    {
        $owner = User::factory()->create();
        $library = Library::create(['user_id' => $owner->id, 'name' => 'مكتبتي الخاصة', 'type' => 'personal', 'is_personal' => true, 'code' => 'LIB-OWN-'.uniqid()]);
        $book = Book::create(['title' => 'كتاب مكتبتي', 'author' => 'مؤلف', 'category' => 'أدب', 'total_pages' => 90]);
        $copy = BookCopy::create([
            'book_id' => $book->id, 'library_id' => $library->id, 'copy_number' => '1', 'status' => 'available', 'barcode' => 'OWN-'.uniqid(),
        ]);

        $this->actingAs($owner)->post("/libraries/copies/{$copy->id}/loan")->assertSessionHas('error');
        $this->assertSame(0, Loan::where('book_copy_id', $copy->id)->count());

        $this->actingAs($owner)->get(route('libraries.index', ['library_id' => $library->id]))
            ->assertOk()
            ->assertDontSee('طلب استعارة النسخة');
    }
}
