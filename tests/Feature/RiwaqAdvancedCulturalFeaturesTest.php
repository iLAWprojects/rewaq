<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqAdvancedCulturalFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_landing_page_renders_book_slider_with_catalog_books(): void
    {
        $book = Book::create([
            'title' => 'كتاب السلايدر التجريبي',
            'author' => 'مؤلف تجريبي',
            'category' => 'أدب',
            'total_pages' => 120,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('data-coverflow', false);
        $response->assertSee('cf-item', false);
        $response->assertSee($book->title);
        $response->assertSee('أضف للمكتبة');
    }

    public function test_home_route_redirects_authenticated_user_to_libraries(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $this->actingAs($user)->get('/home')->assertRedirect(route('libraries.index'));
    }

    public function test_book_swap_loan_request_stores_swap_title_and_qr_token(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $library = Library::firstOrCreate(['user_id' => $user->id, 'is_personal' => true], ['name' => 'مكتبتي']);
        $book = Book::create([
            'title' => 'كتاب التبادل التذكاري '.uniqid(),
            'author' => 'الطيب صالح',
            'category' => 'أدب',
            'total_pages' => 190,
        ]);

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => '99',
            'status' => 'available',
            'barcode' => 'TEST-SWAP-99',
        ]);

        $borrower = User::where('role', 'super_admin')->where('id', '!=', $user->id)->first();
        $response = $this->actingAs($borrower)->post("/libraries/copies/{$copy->id}/loan", [
            'swap_book_title' => 'رواية موسم الهجرة إلى الشمال',
        ]);

        $response->assertSessionHas('success');

        $loan = Loan::where('book_copy_id', $copy->id)->latest()->first();
        $this->assertNotNull($loan);
        $this->assertEquals('رواية موسم الهجرة إلى الشمال', $loan->swap_book_title);
        $this->assertNotNull($loan->qr_token);
        $this->assertStringStartsWith('RWQ-QR-', $loan->qr_token);
    }

    public function test_qr_handshake_transitions_loan_to_active_and_records_timestamp(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $library = Library::firstOrCreate(['user_id' => $user->id, 'is_personal' => true], ['name' => 'مكتبتي']);
        $book = Book::first();

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => '100',
            'status' => 'reserved',
            'barcode' => 'TEST-QR-100',
            'readers_count' => 2,
        ]);

        $loan = Loan::create([
            'book_copy_id' => $copy->id,
            'user_id' => $user->id,
            'borrower_name' => $user->name,
            'status' => 'pending',
            'qr_token' => 'RWQ-QR-HAND123',
            'requested_at' => now(),
            'due_date' => now()->addDays(14),
        ]);

        $response = $this->actingAs($user)->post("/libraries/loans/{$loan->id}/handshake", [
            'qr_token' => 'RWQ-QR-HAND123',
        ]);

        $response->assertSessionHas('success');

        $loan->refresh();
        $copy->refresh();

        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->handshake_at);
        $this->assertEquals('borrowed', $copy->status);
        $this->assertEquals(3, $copy->readers_count);
    }

    public function test_physical_copy_passport_records_margin_note_and_city(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $library = Library::firstOrCreate(['user_id' => $user->id, 'is_personal' => true], ['name' => 'مكتبتي']);
        $book = Book::first();

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => '101',
            'status' => 'available',
            'barcode' => 'TEST-PASS-101',
            'cities_traveled' => 'الرياض',
        ]);

        $response = $this->actingAs($user)->post("/libraries/copies/{$copy->id}/passport-note", [
            'margin_note' => 'كتاب استثنائي يغير زاوية الرؤية للأشياء',
            'city' => 'المدينة المنورة',
        ]);

        $response->assertSessionHas('success');

        $copy->refresh();
        $this->assertStringContainsString('كتاب استثنائي', $copy->margin_notes);
        $this->assertStringContainsString('المدينة المنورة', $copy->cities_traveled);
    }

    public function test_communities_renders_highlights_and_silent_reading_room(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/communities');

        $response->assertStatus(200);
        $response->assertSee('معجم أفكار المجلس');
        $response->assertSee('غرفة القراءة الصامتة');
        $response->assertSee('45:00');
    }

    public function test_challenges_renders_team_battle_and_verifiable_certificate(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/challenges');

        $response->assertStatus(200);
        $response->assertSee('تحديات الفرق');
        $response->assertSee('شهادة إنجاز معتمدة');
        $response->assertSee('certificate-modal');
    }

    public function test_calendar_renders_whatsapp_telegram_reminders_and_audio_archive(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertStatus(200);
        $response->assertSee('واتساب');
        $response->assertSee('تيليجرام');
        $response->assertSee('أرشيف الجلسات والتوثيق الصوتي');
        $response->assertSee('<audio', false);
    }

    public function test_settings_updates_notification_digest_preferences(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->post('/settings', [
            'name' => 'قارئ رِواق المتميز',
            'annual_goal' => 30,
            'bio' => 'مهتم بالأدب والفكر وتاريخ الحضارة',
            'digest_enabled' => '1',
            'digest_time' => '21:30',
        ]);

        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('قارئ رِواق المتميز', $user->name);
        $this->assertEquals(30, $user->annual_goal);
        $this->assertTrue($user->digest_enabled);
        $this->assertEquals('21:30', $user->digest_time);
    }
}
