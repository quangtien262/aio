<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsCategory;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ca0050ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_menu_links_to_owned_pages_and_services_and_can_be_regenerated(): void
    {
        $customPage = \App\Models\CmsPage::create(['title' => 'Trang riêng', 'slug' => 'gioi-thieu', 'status' => 'published', 'body' => 'Nội dung cần giữ.']);
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('CA0050');
        foreach (range(1, 2) as $run) {
            $result = $provider->generate('ca0050-sudes-aquarium');
            $this->assertSame(3, $result['counts']['services']);
            $menu = \App\Models\CmsMenu::where('name', 'CA0050 Main Menu')->firstOrFail();
            $this->assertSame(['home', 'page', 'catalog-index', 'service-category', 'post-category', 'contact'], array_column($menu->items, 'link_type'));
            $about = \App\Models\CmsPage::findOrFail($menu->items[1]['link_value']);
            $this->assertSame('ca0050-gioi-thieu', $about->slug);
            $this->get(route('site.pages.show', ['locale' => 'vi', 'slug' => $about->slug]))->assertOk()->assertSee('Không gian xanh từ thế giới dưới nước');
            $category = \App\Models\CmsServiceCategory::findOrFail($menu->items[3]['link_value']);
            $this->get(route('site.services.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee('Chăm sóc bể cá định kỳ');
            $this->assertSame(3, \App\Models\CmsService::where('slug', 'like', 'ca0050-%')->count());
            app()->forgetInstance(\App\Core\Cms\CmsMenuResolver::class);
            $links = app(\App\Core\Cms\CmsMenuResolver::class)->all(null, 'vi', 'CA0050')['primary-navigation'];
            $this->assertCount(6, $links);
            foreach ($links as $link) {
                $this->assertNotEmpty($link['url']);
                $this->assertStringNotContainsString('#', $link['url']);
                $this->get($link['url'])->assertSuccessful();
            }
            $newsCategory = CmsCategory::findOrFail($menu->items[4]['link_value']);
            $this->get(route('site.blog.category', ['locale' => 'vi', 'slug' => $newsCategory->slug]))->assertOk()->assertSee('Cách chọn đèn bể thủy sinh cho người mới');
        }
        $provider->delete();
        $this->assertDatabaseHas('cms_pages', ['id' => $customPage->id, 'body' => 'Nội dung cần giữ.']);
        $this->assertDatabaseMissing('cms_pages', ['slug' => 'ca0050-gioi-thieu']);
        $this->assertSame(0, \App\Models\CmsService::where('slug', 'like', 'ca0050-%')->count());
    }

    public function test_inner_pages_use_the_actual_catalog_and_cms_data(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('CA0050')->generate('ca0050-sudes-aquarium');
        $product = CatalogProduct::query()->firstOrFail();
        $this->get(route('site.catalog.search', ['locale' => 'vi']))->assertOk()->assertSee($product->name)->assertSee('ca50-catalog-grid', false);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'no-match-xyz']))->assertOk()->assertSee('Chưa tìm thấy sản phẩm phù hợp')->assertDontSee($product->name);
        $page = \App\Models\CmsPage::create(['title' => 'Giới thiệu cửa hàng', 'slug' => 'about-test', 'status' => 'published', 'body' => '<p>Nội dung giới thiệu thực tế.</p>', 'publish_at' => now()]);
        $this->get(route('site.pages.show', ['locale' => 'vi', 'slug' => $page->slug]))->assertOk()->assertSee($page->title)->assertSee('Nội dung giới thiệu thực tế.');
        foreach (['Chăm sóc bể cá', 'Thiết kế hồ cá'] as $i => $title) {
            $service = \App\Models\CmsService::create(['title' => $title, 'slug' => 'service-'.$i, 'status' => 'published', 'summary' => 'Tư vấn theo nhu cầu.', 'content' => '<p>Chi tiết dịch vụ thực tế.</p>', 'publish_at' => now()]);
        }
        $this->get(route('site.services.index', ['locale' => 'vi']))->assertOk()->assertSee('Chăm sóc bể cá')->assertSee('Thiết kế hồ cá')->assertSee('ca50-service-card', false);
        $this->get(route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]))->assertOk()->assertSee($service->title)->assertSee('Chi tiết dịch vụ thực tế.');
    }

    public function test_contact_form_preserves_errors_and_displays_submission_confirmation(): void
    {
        config(['session.serialization' => 'php']);
        \Illuminate\Support\Facades\Mail::fake();
        app(ThemeDemoContentProviderRegistry::class)->forTheme('CA0050')->generate('ca0050-sudes-aquarium');
        $url = route('site.contact', ['locale' => 'vi']);
        $submit = route('site.contact.submit', ['locale' => 'vi']);
        $this->get($url)->assertOk()->assertSee('Kết nối với chúng tôi')->assertSee('ca50-contact-name', false);
        $this->from($url)->post($submit, ['name' => 'Khách thử', 'email' => 'invalid', 'message' => 'ngắn'])
            ->assertSessionHasErrors(['email', 'message']);

        $this->get($url)->assertOk()->assertSee('Khách thử')->assertSee('role="alert"', false);
        $this->from($url)->post($submit, ['source' => 'contact', 'name' => 'Khách thử', 'email' => 'contact@example.test', 'message' => 'Tôi cần tư vấn về bể thủy sinh.'])
            ->assertRedirect($url)->assertSessionHas('contact_status');
        $this->get($url)->assertOk()->assertSee('Đã gửi yêu cầu liên hệ.')->assertSee('role="status"', false);
        $this->assertDatabaseHas('contact_inquiries', ['email' => 'contact@example.test', 'source' => 'contact']);
    }

    public function test_product_detail_has_gallery_price_purchase_and_related_products(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('CA0050')->generate('ca0050-sudes-aquarium');
        $product = CatalogProduct::query()->firstOrFail();
        $product->update(['stock' => 5, 'price' => 120000, 'original_price' => 150000]);
        $product->images()->create(['image_url' => '/theme-demo/ca0050/hero-goldfish.png', 'alt_text' => 'Ảnh phụ', 'sort_order' => 1]);
        $url = route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]);
        $response = $this->get($url)->assertOk()->assertSee('120.000đ')->assertSee('150.000đ')
            ->assertSee('Sản phẩm liên quan')->assertSee('data-ca50-photo', false)
            ->assertSee('name="quantity"', false)->assertSee('max="5"', false)
            ->assertSee(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        $product->update(['stock' => 0]);
        $this->get($url)->assertOk()->assertSee('Hết hàng')->assertSee('disabled', false);
    }

    public function test_ca0050_is_registered_with_the_reference_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'CA0050');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertSame(['vi', 'en'], data_get($theme, 'localization.supported_locales'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('CA0050'));
        $this->assertSame([
            'hero_slider',
            'featured_categories',
            'ca0050_about',
            'ca0050_fish_products',
            'ca0050_tiktok',
            'ca0050_setup',
            'ca0050_accessories',
            'testimonials',
            'latest_posts',
            'ca0050_faq',
            'partner_logos',
            'ca0050_footer',
        ], collect($builder->availableBlocks('CA0050'))->pluck('block_type')->all());

        foreach (['ca0050_fish_products', 'ca0050_accessories'] as $type) {
            $block = collect($builder->availableBlocks('CA0050'))->firstWhere('block_type', $type);
            $this->assertSame('cms_products', data_get($block, 'settings_schema.source.options.0.value'));
            $this->assertArrayHasKey('category_id', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('search', data_get($block, 'settings_schema'));
        }
    }

    public function test_ca0050_demo_data_and_storefront_routes_render(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('CA0050');

        $this->assertNotNull($provider);
        $result = $provider->generate('ca0050-sudes-aquarium');
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(4, data_get($result, 'counts.posts'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'CA0050', 'placement' => 'ca0050-hero-slider']);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-block-type="ca0050_fish_products"', false)
            ->assertSee('data-block-type="ca0050_setup"', false)
            ->assertSee('data-block-type="ca0050_faq"', false)
            ->assertSee('data-block-type="ca0050_footer"', false)
            ->assertSee('Sudes Aquarium');

        $landing = LandingPage::query()->where('theme_key', 'CA0050')->where('is_home', true)->firstOrFail();
        $this->assertCount(12, $landing->blocks);

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'cá']))->assertOk()->assertSee('Kết quả tìm kiếm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);

        $filtered = app(LandingPageBuilder::class)->previewDynamicItems(
            $landing->blocks->firstWhere('block_type', 'ca0050_fish_products'),
            'vi',
            ['search' => 'Oranda'],
        );
        $this->assertCount(1, $filtered);
        $this->assertSame('Cá Ba Đuôi Oranda Đuôi Lụa', $filtered[0]['title']);

        $accessories = app(LandingPageBuilder::class)->previewDynamicItems(
            $landing->blocks->firstWhere('block_type', 'ca0050_accessories'),
            'vi',
        );
        $this->assertCount(4, $accessories);
        $this->assertTrue(collect($accessories)->every(fn (array $item): bool => str_contains($item['title'], 'Hồ cá')));
    }
}
