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
use Illuminate\Support\Str;
use Tests\TestCase;

class RiwaqComprehensiveLendingLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;

    protected User $borrower;

    protected User $waitlistUser;

    protected Library $library;

    protected Book $book;

    protected BookCopy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('enable_book_lending', '1', 'boolean', 'lending');

        $this->owner = User::factory()->create(['name' => 'مالك الخزانة']);
        $this->borrower = User::factory()->create(['name' => 'القارئ المستعير']);
        $this->waitlistUser = User::factory()->create(['name' => 'قارئ الانتظار']);

        $this->library = Library::create([
            'user_id' => $this->owner->id,
            'name' => 'خزانة المعرفة '.Str::random(6),
            'code' => 'LIB-'.Str::random(6),
            'type' => 'club',
            'loan_duration_days' => 14,
            'max_renewals' => 2,
            'grace_period_days' => 2,
        ]);

        LibraryMember::create([
            'library_id' => $this->library->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        LibraryMember::create([
            'library_id' => $this->library->id,
            'user_id' => $this->borrower->id,
            'role' => 'member',
        ]);

        LibraryMember::create([
            'library_id' => $this->library->id,
            'user_id' => $this->waitlistUser->id,
            'role' => 'member',
        ]);

        $this->book = Book::first() ?? Book::create([
            'title' => 'كتاب تجريبي لاختبار دورة الإعارة '.Str::random(4),
            'author' => 'مؤلف معتمد',
            'category' => 'فكر',
            'total_pages' => 300,
        ]);

        $this->copy = BookCopy::create([
            'book_id' => $this->book->id,
            'library_id' => $this->library->id,
            'barcode' => 'BAR-'.Str::random(8),
            'copy_number' => '1',
            'status' => 'available',
            'condition' => 'mint',
            'shelf_location' => 'الرف الخامس - ج3',
        ]);
    }

    /**
     * 1. Test full lending lifecycle:
     * طلب استعارة → موافقة → تسليم (نشط) → تمديد اختياري → إرجاع → إغلاق
     */
    public function test_full_lending_lifecycle_request_approve_handover_extend_return_close(): void
    {
        // 1. Borrower requests loan on available copy
        $reqResp = $this->actingAs($this->borrower)->post("/libraries/copies/{$this->copy->id}/loan", [
            'notes' => 'أرغب في قراءة هذا الكتاب لمدة أسبوعين',
        ]);
        $reqResp->assertSessionHas('success');

        $this->copy->refresh();
        $this->assertEquals('reserved', $this->copy->status);

        $loan = Loan::where('book_copy_id', $this->copy->id)->where('user_id', $this->borrower->id)->first();
        $this->assertNotNull($loan);
        $this->assertEquals('pending', $loan->status);

        // 2. Library Manager Approves the Request
        $approveResp = $this->actingAs($this->owner)->post("/libraries/loans/{$loan->id}/status", [
            'status' => 'approved',
        ]);
        $approveResp->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals('approved', $loan->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->borrower->id,
            'type' => 'loan',
        ]);

        // 3. Handover / Delivery with initial condition inspection
        $handoverResp = $this->actingAs($this->owner)->post("/libraries/loans/{$loan->id}/status", [
            'status' => 'active',
            'handover_condition' => 'mint',
            'handover_notes' => 'تم فحص النسخة والتأكد من سلامة الغلاف وكافة الصفحات قبل تسليمها.',
        ]);
        $handoverResp->assertSessionHas('success');

        $loan->refresh();
        $this->copy->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertEquals('mint', $loan->handover_condition);
        $this->assertEquals($this->owner->id, $loan->handover_inspector_id);
        $this->assertNotNull($loan->borrowed_at);
        $this->assertNotNull($loan->handover_at);
        $this->assertEquals('borrowed', $this->copy->status);

        // 4. Optional Extension
        $extendResp = $this->actingAs($this->borrower)->post("/libraries/loans/{$loan->id}/extend");
        $extendResp->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals(1, $loan->renewal_count);

        // 5. Return copy with return condition inspection
        $returnResp = $this->actingAs($this->owner)->post("/libraries/loans/{$loan->id}/status", [
            'status' => 'returned',
            'return_condition' => 'good',
            'return_notes' => 'استُرجعت النسخة بحالة جيدة مع انثناء خفيف في الزاوية العلوية للغلاف.',
        ]);
        $returnResp->assertSessionHas('success');

        $loan->refresh();
        $this->copy->refresh();
        $this->assertEquals('returned', $loan->status);
        $this->assertEquals('good', $loan->return_condition);
        $this->assertEquals($this->owner->id, $loan->return_inspector_id);
        $this->assertEquals('good', $this->copy->condition);
        $this->assertEquals('available', $this->copy->status);

        // 6. Close and finalize loan file
        $closeResp = $this->actingAs($this->owner)->post("/libraries/loans/{$loan->id}/status", [
            'status' => 'closed',
        ]);
        $closeResp->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals('closed', $loan->status);
        $this->assertNotNull($loan->closed_at);
        $this->assertEquals($this->owner->id, $loan->closed_by);

        // Verify Audit Logs exist for sensitive steps
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'loan_approved',
            'auditable_id' => $loan->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'loan_handover',
            'auditable_id' => $loan->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'loan_returned',
            'auditable_id' => $loan->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'loan_closed',
            'auditable_id' => $loan->id,
        ]);
    }

    /**
     * 2. Test: لا يمكن إعارة نسخة حالتها ليست Available
     */
    public function test_cannot_borrow_copy_if_status_is_not_available(): void
    {
        // Set copy to maintenance
        $this->copy->update(['status' => 'maintenance']);

        $response = $this->actingAs($this->borrower)->post("/libraries/copies/{$this->copy->id}/loan");
        $response->assertSessionHas('error');

        // Set copy to damaged condition
        $this->copy->update(['status' => 'available', 'condition' => 'damaged']);

        $damagedResp = $this->actingAs($this->borrower)->post("/libraries/copies/{$this->copy->id}/loan");
        $damagedResp->assertSessionHas('error');

        // Set copy to borrowed
        $this->copy->update(['status' => 'borrowed', 'condition' => 'good']);

        $borrowedResp = $this->actingAs($this->borrower)->post("/libraries/copies/{$this->copy->id}/loan");
        $borrowedResp->assertSessionHas('error');

        // Verify no loan was created
        $this->assertDatabaseMissing('loans', [
            'book_copy_id' => $this->copy->id,
            'user_id' => $this->borrower->id,
        ]);
    }

    /**
     * 3. Test: قائمة انتظار عند عدم توفر النسخ وإشعار أول مستفيد عند توفرها
     */
    public function test_waitlist_notifies_first_beneficiary_and_allows_them_to_claim_reserved_copy(): void
    {
        // 1. Copy is currently borrowed by borrower
        $this->copy->update(['status' => 'borrowed']);
        $activeLoan = Loan::create([
            'book_copy_id' => $this->copy->id,
            'user_id' => $this->borrower->id,
            'status' => 'active',
            'borrowed_at' => Carbon::now()->subDays(5),
            'due_date' => Carbon::now()->addDays(9),
        ]);

        // 2. WaitlistUser joins waitlist
        $wlResp = $this->actingAs($this->waitlistUser)->post("/libraries/{$this->library->id}/books/{$this->book->id}/waitlist", [
            'notes' => 'بانتظار توفر النسخة',
        ]);
        $wlResp->assertSessionHas('success');

        $waitlist = BookWaitlist::where('library_id', $this->library->id)
            ->where('book_id', $this->book->id)
            ->where('user_id', $this->waitlistUser->id)
            ->first();
        $this->assertNotNull($waitlist);
        $this->assertEquals('waiting', $waitlist->status);

        // 3. When active loan is returned, waitlist triggers and notifies first in queue!
        $this->actingAs($this->owner)->post("/libraries/loans/{$activeLoan->id}/status", [
            'status' => 'returned',
            'return_condition' => 'mint',
        ]);

        $this->copy->refresh();
        $waitlist->refresh();

        // Copy is reserved for the notified waitlist user
        $this->assertEquals('reserved', $this->copy->status);
        $this->assertEquals('notified', $waitlist->status);

        // Notification received by waitlist user
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->waitlistUser->id,
            'type' => 'waitlist',
        ]);

        // 4. Another random reader cannot steal the reserved copy!
        $stranger = User::factory()->create();
        LibraryMember::create(['library_id' => $this->library->id, 'user_id' => $stranger->id, 'role' => 'member']);

        $strangerResp = $this->actingAs($stranger)->post("/libraries/copies/{$this->copy->id}/loan");
        $strangerResp->assertSessionHas('error');

        // 5. But the notified waitlist user CAN borrow the reserved copy!
        $claimResp = $this->actingAs($this->waitlistUser)->post("/libraries/copies/{$this->copy->id}/loan");
        $claimResp->assertSessionHas('success');

        $waitlist->refresh();
        $this->assertEquals('fulfilled', $waitlist->status);

        $newLoan = Loan::where('book_copy_id', $this->copy->id)->where('user_id', $this->waitlistUser->id)->first();
        $this->assertNotNull($newLoan);
        $this->assertEquals('pending', $newLoan->status);
    }

    /**
     * 4. Test: تقرير حالة النسخة عند التسليم والاسترجاع للجهات التي تحتاجه
     */
    public function test_condition_report_view_and_inspection_certificate(): void
    {
        $loan = Loan::create([
            'book_copy_id' => $this->copy->id,
            'user_id' => $this->borrower->id,
            'borrower_name' => $this->borrower->name,
            'status' => 'returned',
            'borrowed_at' => Carbon::now()->subDays(10),
            'handover_at' => Carbon::now()->subDays(10),
            'handover_inspector_id' => $this->owner->id,
            'handover_condition' => 'mint',
            'handover_notes' => 'نسخة مطبوعة ومجلدة بفاخر التجليد وخالية من أي ثنيات.',
            'due_date' => Carbon::now()->addDays(4),
            'returned_at' => Carbon::now(),
            'return_inspector_id' => $this->owner->id,
            'return_condition' => 'mint',
            'return_notes' => 'ورق سليم تماماً مع أثر تصفح طبيعي.',
            'qr_token' => 'RWQ-CERT-TEST',
        ]);

        $response = $this->actingAs($this->owner)->get("/libraries/loans/{$loan->id}/condition-report");
        $response->assertStatus(200);
        $response->assertSee('وثيقة فحص وتقرير حالة النسخة المادية');
        $response->assertSee($this->book->title);
        $response->assertSee($this->copy->barcode);
        $response->assertSee('تقرير الفحص عند التسليم');
        $response->assertSee('تقرير الفحص عند الاسترجاع');
        $response->assertSee('سليمة ومطابقة');

        // Test degraded condition branch
        $loan->update(['return_condition' => 'damaged']);
        $degradedResp = $this->actingAs($this->owner)->get("/libraries/loans/{$loan->id}/condition-report");
        $degradedResp->assertSee('لوحظ تراجع في حالة النسخة');
    }

    /**
     * 5. Test: إشعارات قبل موعد الاستحقاق وبعد التأخير وفق سياسة المكتبة
     */
    public function test_due_date_and_overdue_notifications_are_dispatched(): void
    {
        // 1. Due soon loan (due in 2 days)
        $dueSoonLoan = Loan::create([
            'book_copy_id' => $this->copy->id,
            'user_id' => $this->borrower->id,
            'status' => 'active',
            'borrowed_at' => Carbon::now()->subDays(12),
            'due_date' => Carbon::today()->addDays(2),
        ]);

        $this->assertTrue($dueSoonLoan->isDueSoon());
        $this->assertFalse($dueSoonLoan->isOverdue());

        // 2. Overdue loan (due 3 days ago)
        $copy2 = BookCopy::create([
            'book_id' => $this->book->id,
            'library_id' => $this->library->id,
            'barcode' => 'BAR-OVERDUE-'.Str::random(4),
            'status' => 'borrowed',
        ]);

        $overdueLoan = Loan::create([
            'book_copy_id' => $copy2->id,
            'user_id' => $this->waitlistUser->id,
            'status' => 'active',
            'borrowed_at' => Carbon::now()->subDays(20),
            'due_date' => Carbon::today()->subDays(3),
        ]);

        $this->assertTrue($overdueLoan->isOverdue());

        // Trigger dispatcher
        $dispatched = Loan::checkAndSendDueNotifications();
        $this->assertGreaterThanOrEqual(2, $dispatched);

        // Borrower received due soon notification
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->borrower->id,
            'title' => 'تذكير بقرب موعد استحقاق الإعارة ⏳',
        ]);

        // Waitlist user received overdue notification
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->waitlistUser->id,
            'title' => 'تنبيه تأخير استحقاق الإعارة ⚠️',
        ]);

        // Run Artisan command
        $this->artisan('riwaq:check-due-loans')->assertSuccessful();
    }
}
