<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_auth_pages_load_shared_ajax_validation(): void
    {
        foreach (['customer.auth.login', 'customer.auth.register'] as $route) {
            $this->get(route($route, ['locale' => 'vi']))->assertOk()
                ->assertSee('js/auth-client.js', false)->assertSee('data-auth-client', false);
        }
    }

    public function test_ajax_registration_returns_field_errors_and_creates_only_customer_account(): void
    {
        $url = route('customer.auth.register.store', ['locale' => 'vi']);
        $payload = ['name' => 'Khách AJAX', 'email' => 'auth-client@example.test', 'password' => 'password123', 'password_confirmation' => 'different123'];
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('customers', 0);
        $payload['password_confirmation'] = $payload['password'];
        $this->postJson($url, $payload)->assertOk()
            ->assertJsonPath('message', 'Đăng ký tài khoản thành công.')
            ->assertJsonPath('data.redirect_to', route('customer.account'));
        $this->assertAuthenticatedAs(Customer::firstOrFail(), 'customer');
        $this->assertGuest('admin');
        Auth::guard('customer')->logout();
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_ajax_wrong_credentials_return_field_error_without_authentication(): void
    {
        $this->postJson(route('customer.auth.store', ['locale' => 'vi']), ['login' => 'unknown-user', 'password' => 'password123'])
            ->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->assertGuest('admin');
        $this->assertGuest('customer');
    }
}
