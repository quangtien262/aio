<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ca0050ThemeTest extends TestCase
{
    use RefreshDatabase;

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
