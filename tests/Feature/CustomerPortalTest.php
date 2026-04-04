<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\Customer;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_redirects_back_to_cart_and_requests_login(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = CatalogProduct::query()->create([
            'name' => 'Guest Checkout Product',
            'slug' => 'guest-checkout-product',
            'sku' => 'GUEST-001',
            'price' => 125000,
            'stock' => 8,
            'is_active' => true,
        ]);

        $this->post(route('site.cart.add', ['slug' => $product->slug]), [
            'quantity' => 1,
        ]);

        $this->get(route('site.checkout.index'))
            ->assertRedirect(route('site.cart.index'))
            ->assertSessionHas('open_auth_modal', 'login');
    }

    public function test_customer_can_subscribe_newsletter_and_view_portal_overview(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = Customer::factory()->create([
            'name' => 'Portal Customer',
            'email' => 'portal@example.com',
            'phone' => '0988123123',
        ]);

        $product = CatalogProduct::query()->create([
            'name' => 'Portal Favorite Product',
            'slug' => 'portal-favorite-product',
            'sku' => 'PORTAL-001',
            'price' => 125000,
            'stock' => 6,
            'is_active' => true,
        ]);

        Order::query()->create([
            'order_code' => 'AIOPORTAL001',
            'customer_id' => $customer->id,
            'status' => 'placed',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_email' => $customer->email,
            'delivery_address' => '123 Nguyen Trai',
            'payment_method' => 'cod',
            'payment_label' => 'Thanh toán khi nhận hàng',
            'subtotal' => 125000,
            'item_count' => 1,
            'placed_at' => now(),
        ]);

        $this->actingAs($customer, 'customer');

        $this->postJson(route('site.newsletter.subscribe'))
            ->assertOk()
            ->assertJsonPath('data.email', 'portal@example.com');

        $this->post(route('site.favorite.toggle', ['product' => $product->slug]))
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'portal@example.com',
            'customer_id' => $customer->id,
        ]);

        $this->getJson(route('customer.api.overview'))
            ->assertOk()
            ->assertJsonPath('data.customer.email', 'portal@example.com')
            ->assertJsonPath('data.newsletter.is_subscribed', true)
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonCount(1, 'data.favorites');

        $this->assertSame(1, NewsletterSubscriber::query()->count());
    }

    public function test_guest_can_subscribe_newsletter_from_header_modal_flow(): void
    {
        $this->postJson(route('site.newsletter.subscribe'), [
            'email' => 'guest-newsletter@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'guest-newsletter@example.com');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'guest-newsletter@example.com',
            'customer_id' => null,
        ]);
    }
}