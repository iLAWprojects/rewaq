<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\LibraryMember;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqBookLendingToggleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $user;

    protected Library $library;

    protected Book $book;

    protected BookCopy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'المشرف العام',
            'role' => 'super_admin',
            'email' => 'superadmin_toggle_'.uniqid().'@riwaq.test',
        ]);

        $this->user = User::factory()->create([
            'name' => 'القارئ أحمد',
            'role' => 'user',
            'email' => 'ahmed_toggle_'.uniqid().'@riwaq.test',
        ]);

        $this->library = Library::create([
            'user_id' => $this->superAdmin->id,
            'name' => 'خزانة الإعارة التجريبية',
            'type' => 'public',
            'is_personal' => false,
            'description' => 'مكتبة عامة لاختبار نظام الإعارة',
        ]);

        LibraryMember::create([
            'library_id' => $this->library->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->book = Book::first() ?? Book::create([
            'title' => 'مقدمة ابن خلدون',
            'author' => 'عبد الرحمن بن خلدون',
            'category' => 'فكر',
            'total_pages' => 500,
        ]);

        $this->copy = BookCopy::create([
            'library_id' => $this->library->id,
            'book_id' => $this->book->id,
            'barcode' => 'WQ-TOGGLE-'.uniqid(),
            'condition' => 'good',
            'status' => 'available',
        ]);
    }

    public function test_admin_settings_displays_lending_toggle_button_and_status(): void
    {
        SystemSetting::set('enable_book_lending', '1', 'boolean', 'lending');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard', ['tab' => 'settings']));

        $response->assertStatus(200);
        $response->assertSee('نظام إعارة واستعارة الكتب الورقية');
        $response->assertSee('settings[enable_book_lending]');
        $response->assertSee('تشغيل الإعارة');
        $response->assertSee('مفعّل في الموقع');
    }

    public function test_super_admin_can_toggle_lending_off_and_on(): void
    {
        // 1. Toggle OFF: Send updateSettings without enable_book_lending
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateSettings'), [
            'settings' => [
                'site_name' => 'رِواق المعرفة',
                'max_active_loans' => 3,
                'loan_duration_days' => 14,
            ],
        ]);

        $response->assertRedirect();
        $this->assertFalse(SystemSetting::isLendingEnabled());

        // 2. Toggle ON: Send updateSettings with enable_book_lending = 1
        $response = $this->actingAs($this->superAdmin)->post(route('admin.updateSettings'), [
            'settings' => [
                'site_name' => 'رِواق المعرفة',
                'enable_book_lending' => '1',
                'max_active_loans' => 3,
                'loan_duration_days' => 14,
            ],
        ]);

        $response->assertRedirect();
        $this->assertTrue(SystemSetting::isLendingEnabled());
    }

    public function test_when_lending_is_disabled_all_borrowing_ui_is_hidden(): void
    {
        SystemSetting::set('enable_book_lending', '0', 'boolean', 'lending');
        $this->assertFalse(SystemSetting::isLendingEnabled());

        // 1. Library page: Loans tab, waitlists tab, and borrow button must be hidden
        $response = $this->actingAs($this->user)->get(route('libraries.index', ['library_id' => $this->library->id]));

        $response->assertStatus(200);
        $response->assertDontSee('طلبات الاستعارة وسجل الإعارات');
        $response->assertDontSee('قوائم الانتظار');
        $response->assertDontSee('طلب استعارة النسخة');
        $response->assertDontSee('id="loan-modal"', false);

        // 2. Direct navigation to ?tab=loans must redirect back
        $loansRedirect = $this->actingAs($this->user)->get(route('libraries.index', [
            'library_id' => $this->library->id,
            'tab' => 'loans',
        ]));
        $loansRedirect->assertRedirect(route('libraries.index', ['library_id' => $this->library->id]));

        // 3. Book Details modal across the app must not show loan button
        $response->assertDontSee('id="bmodal-loan-form"', false);
        $response->assertDontSee('طلب استعارة نسخة ورقية');
        $response->assertDontSee('sanctuary-charter-note');

        // 4. Explore page: lending buttons, status badges, and modals must not be rendered
        $exploreResponse = $this->actingAs($this->user)->get(route('explore'));
        $exploreResponse->assertStatus(200);
        $exploreResponse->assertDontSee('id="loan-modal"', false);
        $exploreResponse->assertDontSee('طلب استعارة النسخة الورقية');
        $exploreResponse->assertDontSee('طلب استعارة النسخة');
        $exploreResponse->assertDontSee('معار بالكامل');
        $exploreResponse->assertDontSee('متاح للإعارة');
        $exploreResponse->assertDontSee('نسخ متاحة للإعارة');
        $exploreResponse->assertSee('ex-card', false);

        // 5. Home page: upcoming loan commitments must be hidden
        Loan::create([
            'book_copy_id' => $this->copy->id,
            'user_id' => $this->user->id,
            'borrower_name' => $this->user->name,
            'status' => 'active',
            'borrowed_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $homeResponse = $this->actingAs($this->user)->get(route('libraries.index'));
        $homeResponse->assertStatus(200);
        $homeResponse->assertDontSee('مكتبة العائلة · إعارة نشطة');
    }

    public function test_when_lending_is_disabled_backend_endpoints_are_blocked(): void
    {
        SystemSetting::set('enable_book_lending', '0', 'boolean', 'lending');

        // 1. Attempt to request a loan
        $loanReq = $this->actingAs($this->user)->post(route('libraries.requestLoan', $this->copy->id));
        $loanReq->assertSessionHas('error', 'خدمة استعارة الكتب الورقية معطلة حالياً بقرار من إدارة المنصة.');
        $this->assertEquals(0, Loan::where('book_copy_id', $this->copy->id)->count());

        // 2. Existing loan actions should also be blocked
        $loan = Loan::create([
            'book_copy_id' => $this->copy->id,
            'user_id' => $this->user->id,
            'borrower_name' => $this->user->name,
            'status' => 'active',
            'borrowed_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        // Extension blocked
        $extReq = $this->actingAs($this->user)->post(route('libraries.extendLoan', $loan->id));
        $extReq->assertSessionHas('error', 'خدمة تمديد الإعارة معطلة حالياً بقرار من إدارة المنصة.');

        // Handshake blocked
        $hsReq = $this->actingAs($this->superAdmin)->post(route('libraries.handshake', $loan->id), [
            'qr_token' => $loan->qr_token,
        ]);
        $hsReq->assertSessionHas('error', 'خدمة توثيق تسليم الإعارة معطلة حالياً بقرار من إدارة المنصة.');

        // Condition report blocked
        $repReq = $this->actingAs($this->user)->get(route('libraries.conditionReport', $loan->id));
        $repReq->assertStatus(403);
    }

    public function test_when_lending_is_re_enabled_everything_functions_normally(): void
    {
        SystemSetting::set('enable_book_lending', '1', 'boolean', 'lending');
        $this->assertTrue(SystemSetting::isLendingEnabled());

        // 1. UI elements are visible
        $response = $this->actingAs($this->user)->get(route('libraries.index', ['library_id' => $this->library->id]));
        $response->assertStatus(200);
        $response->assertSee('طلبات الاستعارة وسجل الإعارات');
        $response->assertSee('طلب استعارة النسخة');
        $response->assertSee('id="loan-modal"', false);
        $response->assertSee('id="bmodal-loan-form"', false);

        // 2. User can request a loan successfully
        $loanReq = $this->actingAs($this->user)->post(route('libraries.requestLoan', $this->copy->id));
        $loanReq->assertSessionHas('success');
        $this->assertEquals(1, Loan::where('book_copy_id', $this->copy->id)->count());
    }
}
