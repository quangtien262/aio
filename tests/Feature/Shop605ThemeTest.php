<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Shop605ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop605_has_the_reference_blocks_and_demo_content(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SHOP605');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertSame([
            'hero_slider', 'shop605_benefits', 'shop605_sale', 'shop605_new', 'shop605_best',
            'shop605_editorial', 'shop605_collections', 'shop605_story', 'latest_posts', 'shop605_footer',
        ], collect($builder->availableBlocks('SHOP605'))->pluck('block_type')->all());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP605');
        $this->assertNotNull($provider);
        $result = $provider->generate('shop605-oh-under');
        $this->assertSame(12, data_get($result, 'counts.products'));

        $response = $this->get(route('site.home', ['locale' => 'vi']));
        $response->assertOk()
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('data-xd-auth-open="register"', false)
            ->assertSee('data-block-type="shop605_sale"', false)
            ->assertSee('data-block-type="shop605_collections"', false)
            ->assertSee('data-block-type="shop605_footer"', false)
            ->assertSee('OH!Under');

        $landing = LandingPage::query()->where('theme_key', 'SHOP605')->where('is_home', true)->firstOrFail();
        $this->assertCount(10, $landing->blocks);
    }
}
