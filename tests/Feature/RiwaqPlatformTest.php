<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqPlatformTest extends TestCase
{
    use DatabaseTransactions;

    public function test_landing_page_renders_successfully_for_guest(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('رِواق');
        $response->assertSee('اقرأ على مهل');
        $response->assertSee('تسجيل الدخول');
        $response->assertSee('إنشاء حساب');
    }

    public function test_guest_sees_public_layout_without_dashboard_sidebar(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response->assertDontSee('id="riwaq-sidebar"', false);
        $response->assertDontSee('<span>مكتباتي</span>', false);
        $response->assertDontSee('لوحة الإدارة');

        $response->assertSee('تصفح الكتب');
        $response->assertSee('نوادي القراءة');
        $response->assertSee('التحديات');
        $response->assertSee('إنشاء حساب');
    }

    public function test_authenticated_user_sees_sidebar_in_required_order_and_top_bar(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $html = $this->actingAs($user)->get('/libraries')->assertStatus(200)->getContent();

        $this->assertStringContainsString('id="riwaq-sidebar"', $html);
        $order = ['<span>مكتباتي</span>', '<span>نوادي القراءة</span>', '<span>التحديات</span>', '<span>التجمعات</span>', '<span>تصفح الكتب</span>', '<span>الإعدادات</span>'];
        $last = -1;
        foreach ($order as $label) {
            $pos = strpos($html, $label);
            $this->assertNotFalse($pos, $label.' missing');
            $this->assertGreaterThan($last, $pos, $label.' out of order');
            $last = $pos;
        }

        $this->assertStringContainsString('rq-search', $html);
        $this->assertStringContainsString('aria-label="الإشعارات"', $html);
        $this->assertStringContainsString('aria-label="قائمة المستخدم"', $html);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/home');
        $response->assertRedirect('/login');

        $response = $this->get('/libraries');
        $response->assertRedirect('/login');

        $response = $this->get('/reading');
        $response->assertRedirect('/login');
    }

    public function test_home_redirects_authenticated_user_to_libraries(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $this->actingAs($user)->get('/home')->assertRedirect(route('libraries.index'));
    }

    public function test_explore_page_renders_for_guests(): void
    {
        $response = $this->get('/explore');
        $response->assertStatus(200);
        $response->assertSee('اتّسع بكتاب جديد');
        $response->assertSee('من الأدب إلى العمران');
    }

    public function test_libraries_page_renders_for_authenticated_user(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $response = $this->actingAs($user)->get('/libraries');
        $response->assertStatus(200);
        $response->assertSee('المكتبة الشخصية');
        $response->assertSee('رفوف الكتب');
        $response->assertSee('طلبات الاستعارة');
    }

    public function test_reading_page_renders_for_authenticated_user(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $response = $this->actingAs($user)->get('/reading');
        $response->assertStatus(200);
        $response->assertSee('سجلّ الأثر');
        $response->assertSee('جلسات وتأمّلات');
    }

    public function test_communities_page_renders_for_guest(): void
    {
        $response = $this->get('/communities');
        $response->assertStatus(200);
        $response->assertSee('مجلس الأدب');
        $response->assertSee('سؤال المجلس');
        $response->assertSee('أيّ كتاب نقرأ بعد ذلك؟');
    }

    public function test_challenges_page_renders_for_guest(): void
    {
        $response = $this->get('/challenges');
        $response->assertStatus(200);
        $response->assertSee('آفاق جديدة');
    }

    public function test_calendar_page_renders_for_guest(): void
    {
        $response = $this->get('/calendar');
        $response->assertStatus(200);
        $response->assertSee('سبتمبر 2026');
        $response->assertSee('نقاش «وحي القلم»');
    }

    public function test_settings_page_requires_auth(): void
    {
        $response = $this->get('/settings');
        $response->assertRedirect('/login');

        $user = User::where('email', 'demo@riwaq.test')->first();
        $response = $this->actingAs($user)->get('/settings');
        $response->assertStatus(200);
        $response->assertSee('تفضيلات مساحتك');
    }

    public function test_admin_dashboard_requires_auth(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');

        $admin = User::where('role', 'super_admin')->first();
        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('لوحة إدارة المنصة (Super Admin)');
        $response->assertSee('عناوين الكتب');
    }

    public function test_api_endpoints_return_valid_json(): void
    {
        $response = $this->getJson('/api/v1/books');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);

        $response = $this->getJson('/api/v1/current-book');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data' => ['current_page', 'total_pages', 'percentage', 'book']]);
    }
}
