<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqDemoAccessSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function asProduction(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_quick_login_and_switch_routes_do_not_exist_in_production(): void
    {
        $this->asProduction();

        $this->get('/auth/quick-login/admin')->assertNotFound();
        $this->get('/auth/quick-login/user')->assertNotFound();
        $this->get('/auth/switch/admin')->assertNotFound();
        $this->assertGuest();
    }

    public function test_default_demo_credentials_do_not_auto_create_accounts_in_production(): void
    {
        $this->asProduction();
        User::where('email', 'like', 'nobody-%@riwaq.test')->delete();
        $email = 'admin@riwaq.test';
        $existed = User::where('email', $email)->exists();
        if ($existed) {
            $this->markTestSkipped('حساب الأدمن موجود في قاعدة الاختبار، لا يمكن فحص عدم إنشائه.');
        }

        $this->post('/login', ['email' => $email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertGuest();
    }

    public function test_api_requires_authentication_in_production_but_not_in_local_testing(): void
    {
        $this->asProduction();
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->getJson('/api/v1/current-book')->assertUnauthorized();
        $this->getJson('/api/v1/reading-sessions')->assertUnauthorized();

        $this->app['env'] = 'testing';
        $this->getJson('/api/v1/books')->assertOk();
    }

    public function test_quick_login_still_works_for_local_development(): void
    {
        $this->app['env'] = 'local';

        $this->get('/auth/quick-login/user')->assertRedirect();
        $this->assertAuthenticated();
    }
}
