<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ec911ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec911_is_registered_with_ten_editable_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC911');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC911'));
        $this->assertSame([
            'hero_slider', 'ec911_benefits', 'ec911_category_rail', 'ec911_flash_sale', 'ec911_camera_products',
            'ec911_campaign_banner', 'ec911_brand_cards', 'ec911_news', 'ec911_newsletter', 'ec911_footer',
        ], collect($builder->availableBlocks('EC911'))->pluck('block_type')->all());
        $this->assertSame('cms_products', data_get(collect($builder->availableBlocks('EC911'))->firstWhere('block_type', 'ec911_flash_sale'), 'settings_schema.source.options.0.value'));
    }

    public function test_ec911_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC911');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec911-digitech');
        $this->assertSame(5, data_get($result, 'counts.categories'));
        $this->assertSame(10, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('data-block-type="ec911_flash_sale"', false)
            ->assertSee('data-block-type="ec911_camera_products"', false)
            ->assertSee('data-block-type="ec911_news"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('DIGITECH')
            ->assertSee('ĐĂNG KÝ ĐỂ NHẬN TIN TỨC KHUYẾN MÃI MỚI NHẤT');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'DIGITECH']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ DIGITECH');
        $this->assertCount(10, LandingPage::query()->where('theme_key', 'EC911')->where('is_home', true)->firstOrFail()->blocks);
    }

    public function test_ec911_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create(['site_name' => 'Website', 'website_type' => 'ecommerce', 'active_theme_key' => 'SHOP601', 'branding' => ['logo_url' => '/storage/branding/custom-logo.svg']]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC911')?->generate('ec911-digitech');
        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }

    public function test_ec911_admin_mode_renders_real_block_edit_buttons(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC911')?->generate('ec911-digitech');
        $this->actingAs(Admin::factory()->create(), 'admin');

        $content = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('Sửa khối')
            ->getContent();

        $this->assertSame(
            10,
            preg_match_all('/<button\b[^>]*\bdata-xd-edit-block\b[^>]*>/i', $content),
        );
    }
}
