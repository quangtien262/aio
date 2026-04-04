<?php

namespace Tests\Feature;

use App\Mail\OrderPlacedMail;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ThemeDemoContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_demo_data_for_a_theme(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $this->postJson('/admin/api/themes/TH0001/demo-data', [
            'preset' => 'electronics-superstore',
        ])
            ->assertOk()
            ->assertJsonPath('data.preset.key', 'electronics-superstore');

        $this->assertDatabaseHas('site_banners', [
            'theme_key' => 'TH0001',
            'placement' => 'hero-main',
            'website_key' => 'website-main',
        ]);

        $this->assertGreaterThanOrEqual(10, CatalogCategory::query()->whereNull('parent_id')->count());
        $this->assertGreaterThanOrEqual(40, CatalogProduct::query()->count());
        $this->assertDatabaseHas('cms_menus', [
            'location' => 'product-navigation',
            'website_key' => 'website-main',
        ]);
    }

    public function test_th0001_homepage_can_render_generated_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/themes/TH0001/activate')->assertOk();
        $this->postJson('/admin/api/themes/TH0001/demo-data', [
            'preset' => 'electronics-superstore',
        ])->assertOk();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Điện thoại');
        $response->assertSee('Deal sốc cho điện thoại, laptop và điện gia dụng');
        $response->assertSee('Tin tức');
    }

    public function test_admin_can_manage_catalog_categories_and_site_banners(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/modules/catalog/install')->assertOk();
        $this->postJson('/admin/api/modules/catalog/enable')->assertOk();

        $categoryId = $this->postJson('/admin/api/catalog/categories', [
            'name' => 'Thiết bị demo',
            'slug' => 'thiet-bi-demo',
            'description' => 'Danh mục phục vụ test CRUD.',
            'sort_order' => 1,
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/admin/api/catalog/categories')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'thiet-bi-demo']);

        $this->putJson("/admin/api/catalog/categories/{$categoryId}", [
            'name' => 'Thiết bị demo cập nhật',
            'slug' => 'thiet-bi-demo-cap-nhat',
            'description' => 'Danh mục đã cập nhật.',
            'sort_order' => 2,
            'is_active' => true,
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])->assertOk();

        $bannerId = $this->postJson('/admin/api/site-banners', [
            'theme_key' => 'TH0001',
            'placement' => 'hero-side',
            'title' => 'Banner test',
            'subtitle' => 'Subtitle test',
            'image_url' => 'https://picsum.photos/seed/banner-test/360/180',
            'link_url' => '/danh-muc/thiet-bi-demo-cap-nhat',
            'badge' => 'Test',
            'eyebrow' => 'Eyebrow',
            'summary' => 'Summary',
            'button_label' => 'Xem ngay',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/admin/api/site-banners')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Banner test']);

        $this->putJson("/admin/api/site-banners/{$bannerId}", [
            'theme_key' => 'TH0001',
            'placement' => 'hero-side',
            'title' => 'Banner test updated',
            'subtitle' => 'Subtitle updated',
            'image_url' => 'https://picsum.photos/seed/banner-test-2/360/180',
            'link_url' => '/danh-muc/thiet-bi-demo-cap-nhat',
            'badge' => 'Updated',
            'eyebrow' => 'New Eyebrow',
            'summary' => 'Updated summary',
            'button_label' => 'Mở ngay',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])->assertOk();

        $this->deleteJson("/admin/api/site-banners/{$bannerId}")->assertOk();
        $this->deleteJson("/admin/api/catalog/categories/{$categoryId}")->assertOk();

        $this->assertDatabaseMissing('site_banners', ['id' => $bannerId]);
        $this->assertDatabaseMissing('catalog_categories', ['id' => $categoryId]);
    }

    public function test_admin_can_manage_catalog_product_gallery_and_detail_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/modules/catalog/install')->assertOk();
        $this->postJson('/admin/api/modules/catalog/enable')->assertOk();

        $category = CatalogCategory::query()->create([
            'name' => 'Deal buffet',
            'slug' => 'deal-buffet',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ]);

        $productId = $this->postJson('/admin/api/catalog/products', [
            'catalog_category_id' => $category->id,
            'name' => 'Buffet tối cuối tuần',
            'slug' => 'buffet-toi-cuoi-tuan',
            'sku' => 'BUFFET-001',
            'price' => 466000,
            'original_price' => 549000,
            'stock' => 30,
            'short_description' => 'Buffet tối với hải sản và món Nhật.',
            'detail_content' => "Đoạn 1".PHP_EOL.PHP_EOL."Đoạn 2",
            'highlights' => "Hải sản nướng".PHP_EOL.'Không gian 5 sao',
            'usage_terms' => "Đặt chỗ trước".PHP_EOL.'Áp dụng tối thứ 7',
            'usage_location' => "La Brasserie".PHP_EOL.'Hải Phòng',
            'image_url' => 'https://picsum.photos/seed/product-cover/640/420',
            'gallery_images' => [
                'https://picsum.photos/seed/product-gallery-1/960/720',
                'https://picsum.photos/seed/product-gallery-2/960/720',
            ],
            'sold_count' => 12,
            'deal_end_at' => '2026-06-30 21:00:00',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])
            ->assertCreated()
            ->assertJsonPath('data.gallery_images.1', 'https://picsum.photos/seed/product-gallery-2/960/720')
            ->json('data.id');

        $this->putJson('/admin/api/catalog/products/'.$productId, [
            'catalog_category_id' => $category->id,
            'name' => 'Buffet tối cuối tuần cập nhật',
            'slug' => 'buffet-toi-cuoi-tuan-cap-nhat',
            'sku' => 'BUFFET-001',
            'price' => 466000,
            'original_price' => 549000,
            'stock' => 28,
            'short_description' => 'Buffet tối với hải sản và món Nhật.',
            'detail_content' => "Đoạn 1".PHP_EOL.PHP_EOL."Đoạn 2 cập nhật",
            'highlights' => "Hải sản nướng".PHP_EOL.'Không gian 5 sao',
            'usage_terms' => "Đặt chỗ trước".PHP_EOL.'Áp dụng tối thứ 7',
            'usage_location' => "La Brasserie".PHP_EOL.'Hải Phòng',
            'image_url' => 'https://picsum.photos/seed/product-cover/640/420',
            'gallery_images' => [
                'https://picsum.photos/seed/product-gallery-3/960/720',
            ],
            'sold_count' => 18,
            'deal_end_at' => '2026-07-01 21:00:00',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])
            ->assertOk()
            ->assertJsonPath('data.gallery_images.0', 'https://picsum.photos/seed/product-gallery-3/960/720')
            ->assertJsonPath('data.sold_count', 18);

        $product = CatalogProduct::query()->findOrFail($productId);

        $this->assertCount(1, $product->images);
        $this->assertDatabaseHas('catalog_product_images', [
            'catalog_product_id' => $productId,
            'image_url' => 'https://picsum.photos/seed/product-gallery-3/960/720',
        ]);
        $this->assertSame(1, CatalogProductImage::query()->where('catalog_product_id', $productId)->count());
    }

    public function test_th0001_category_and_product_pages_render_real_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/themes/TH0001/activate')->assertOk();
        $this->postJson('/admin/api/themes/TH0001/demo-data', [
            'preset' => 'electronics-superstore',
        ])->assertOk();

        $category = CatalogCategory::query()->whereNull('parent_id')->orderBy('id')->firstOrFail();
        $product = CatalogProduct::query()->whereNotNull('slug')->orderBy('id')->firstOrFail();

        $this->get('/danh-muc/'.$category->slug)
            ->assertOk()
            ->assertSee($category->name);

        $this->get('/san-pham/'.$product->slug)
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->sku)
            ->assertSee('THÔNG TIN CHI TIẾT')
            ->assertSee((string) $product->images()->orderBy('sort_order')->value('image_url'), false);
    }

    public function test_th0001_category_page_can_filter_by_price_and_sort_results(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/themes/TH0001/activate')->assertOk();
        $this->postJson('/admin/api/themes/TH0001/demo-data', [
            'preset' => 'electronics-superstore',
        ])->assertOk();

        $category = CatalogCategory::query()->where('slug', 'electronics-superstore-laptop')->firstOrFail();
        $productsInCategory = CatalogProduct::query()
            ->where('is_active', true)
            ->whereIn('catalog_category_id', $category->children()->pluck('id')->prepend($category->id))
            ->orderBy('price')
            ->get();

        $targetMinProduct = $productsInCategory->skip(1)->first();
        $targetMaxProduct = $productsInCategory->last();

        $this->assertNotNull($targetMinProduct);
        $this->assertNotNull($targetMaxProduct);

        $targetMinPrice = (int) $targetMinProduct->price;
        $targetMaxPrice = (int) $targetMaxProduct->price;

        $response = $this->get('/danh-muc/'.$category->slug.'?sort=price_asc&min_price='.$targetMinPrice.'&max_price='.$targetMaxPrice);

        $response->assertOk();

        $expectedTitles = $productsInCategory
            ->filter(fn (CatalogProduct $product): bool => (int) $product->price >= $targetMinPrice && (int) $product->price <= $targetMaxPrice)
            ->pluck('name')
            ->values();

        $response->assertSee($expectedTitles->first());
        $response->assertDontSee($productsInCategory->first()->name);

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertLessThan(
            strpos($content, $expectedTitles->get(1)),
            strpos($content, $expectedTitles->first()),
        );
    }

    public function test_th0001_product_page_can_add_to_cart_and_buy_now(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');
        $this->postJson('/admin/api/themes/TH0001/activate')->assertOk();
        $this->postJson('/admin/api/themes/TH0001/demo-data', [
            'preset' => 'electronics-superstore',
        ])->assertOk();

        $product = CatalogProduct::query()->whereNotNull('slug')->where('is_active', true)->orderBy('id')->firstOrFail();

        $this->from('/san-pham/'.$product->slug)
            ->post(route('site.cart.add', ['slug' => $product->slug]), [
                'quantity' => 2,
            ])
            ->assertRedirect('/san-pham/'.$product->slug)
            ->assertSessionHas('cart_success');

        $this->assertSame(2, data_get(session('storefront_cart'), $product->id.'.quantity'));

        $this->post(route('site.cart.update', ['productId' => $product->id]), [
            'quantity' => 4,
        ])
            ->assertRedirect()
            ->assertSessionHas('cart_success');

        $this->assertSame(4, data_get(session('storefront_cart'), $product->id.'.quantity'));

        $this->get('/san-pham/'.$product->slug)
            ->assertOk()
            ->assertSee('GIỎ HÀNG (4)');

        $this->post(route('site.cart.buy_now', ['slug' => $product->slug]), [
            'quantity' => 1,
        ])
            ->assertRedirect(route('site.checkout.index'))
            ->assertSessionHas('cart_success');

        $this->assertSame(5, data_get(session('storefront_cart'), $product->id.'.quantity'));

        $this->get(route('site.checkout.index'))
            ->assertOk()
            ->assertSee('Thanh toán đơn hàng')
            ->assertSee($product->name);

        $checkoutResponse = $this->post(route('site.checkout.store'), [
            'customer_name' => 'Nguyen Van A',
            'customer_phone' => '0909123456',
            'customer_email' => 'customer@example.com',
            'delivery_address' => '123 Duong Demo, Quan 1, TP.HCM',
            'note' => 'Giao gio hanh chinh',
            'payment_method' => 'bank_transfer',
        ]);

        $checkoutResponse
            ->assertSessionHas('cart_success');

        $order = Order::query()->with('items')->latest('id')->firstOrFail();

        $checkoutResponse->assertRedirect(route('site.checkout.success', ['order' => $order->id]));

        $this->assertNull(session('storefront_cart'));
        $this->assertSame('placed', $order->status);
        $this->assertSame('Nguyen Van A', $order->customer_name);
        $this->assertSame('0909123456', $order->customer_phone);
        $this->assertSame('customer@example.com', $order->customer_email);
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame(1, $order->items->count());
        $this->assertNotNull($order->email_queued_at);
        $this->assertNull($order->email_sent_at);
        $this->assertNotNull($order->sms_sent_at);

        Mail::assertQueued(OrderPlacedMail::class, function (OrderPlacedMail $mail) use ($order): bool {
            return $mail->order->is($order);
        });

        $this->get(route('site.checkout.success', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Đặt hàng thành công')
            ->assertSee('Nguyen Van A')
            ->assertSee($order->order_code)
            ->assertSee($product->name);

        $this->get(route('site.cart.index'))
            ->assertOk()
            ->assertSee('Giỏ hàng hiện đang trống');
    }
}
