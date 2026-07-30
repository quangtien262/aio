<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ec910ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec910_is_registered_with_ten_ordered_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC910');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC910'));
        $this->assertSame([
            'hero_slider',
            'ec910_benefits',
            'ec910_promotions',
            'ec910_product_tabs',
            'ec910_about',
            'ec910_mens_watches',
            'ec910_orient_banner',
            'ec910_experience',
            'ec910_brands',
            'ec910_footer',
        ], collect($builder->availableBlocks('EC910'))->pluck('block_type')->all());
    }

    public function test_ec910_demo_content_and_homepage_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC910');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec910-dola-watch');

        $this->assertSame(4, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(5, data_get($result, 'counts.posts'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('DOLA')
            ->assertSee('Khuyến mãi hấp dẫn')
            ->assertSee('Đồng hồ nam')
            ->assertSee('Thương hiệu nổi bật')
            ->assertSee('data-block-type="ec910_promotions"', false)
            ->assertSee('data-block-type="ec910_footer"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()->where('theme_key', 'EC910')->where('is_home', true)->firstOrFail();
        $this->assertCount(10, $page->blocks);
    }

    public function test_ec910_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC910')?->generate('ec910-dola-watch');

        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
