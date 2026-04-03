<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_uses_admin_guard_only(): void
    {
        Admin::factory()->create([
            'email' => 'admin@aio.local',
            'password' => 'password',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@aio.local',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs(Admin::first(), 'admin');
        $this->assertGuest('customer');
    }

    public function test_customer_registration_and_login_use_customer_guard_only(): void
    {
        $registerResponse = $this->post('/register', [
            'name' => 'Customer Demo',
            'email' => 'customer@aio.local',
            'phone' => '0900000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $registerResponse->assertRedirect('/account');
        $this->assertAuthenticated('customer');
        $this->assertGuest('admin');

        Auth::guard('customer')->logout();

        $loginResponse = $this->post('/login', [
            'email' => 'customer@aio.local',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/account');
        $this->assertAuthenticatedAs(Customer::first(), 'customer');
        $this->assertGuest('admin');
    }
}
