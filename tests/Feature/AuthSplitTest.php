<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Support\FrontendLocalization;
use Tests\Concerns\InteractsWithStorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthSplitTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStorefrontRoutes;

    public function test_seeded_admin_defaults_to_active_and_unlocked(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'admin',
            'email' => 'admin@aio.local',
        ]);

        $this->assertTrue((bool) $admin->is_active);
        $this->assertNull($admin->locked_at);
        $this->assertNull($admin->locked_reason);
    }

    public function test_shared_login_route_authenticates_admin_before_customer(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'admin',
            'email' => 'admin@aio.local',
            'password' => 'password',
        ]);

        Customer::factory()->create([
            'email' => 'customer@aio.local',
            'password' => 'password',
        ]);

        $response = $this->postJson($this->storefrontRoute('customer.auth.store'), [
            'login' => 'admin',
            'password' => 'password',
            'redirect_to' => $this->storefrontRoute('site.home'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.guard', 'admin')
            ->assertJsonPath('data.redirect_to', route('admin.index'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }

    public function test_customer_registration_and_login_use_customer_guard_only(): void
    {
        $registerResponse = $this->post($this->storefrontRoute('customer.auth.register.store'), [
            'name' => 'Customer Demo',
            'email' => 'customer@aio.local',
            'phone' => '0900000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $registerResponse->assertRedirect($this->storefrontRoute('customer.account'));
        $this->assertAuthenticated('customer');
        $this->assertGuest('admin');

        Auth::guard('customer')->logout();

        $loginResponse = $this->post($this->storefrontRoute('customer.auth.store'), [
            'email' => 'customer@aio.local',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect($this->storefrontRoute('customer.account'));
        $this->assertAuthenticatedAs(Customer::first(), 'customer');
        $this->assertGuest('admin');
    }

    public function test_admin_guest_is_redirected_to_storefront_home_when_session_is_missing(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect($this->storefrontRoute('site.home'));
        $this->assertGuest('admin');
    }

    public function test_guest_accessing_customer_account_is_redirected_to_homepage(): void
    {
        $this->get($this->storefrontRoute('customer.account'))
            ->assertRedirect($this->storefrontRoute('site.home'));
    }

    public function test_customer_logout_redirects_to_homepage(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');

        $this->post($this->storefrontRoute('customer.auth.logout'))
            ->assertRedirect($this->storefrontRoute('site.home'));

        $this->assertGuest('customer');
    }
}
