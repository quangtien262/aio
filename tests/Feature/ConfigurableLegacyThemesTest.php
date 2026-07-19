<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfigurableLegacyThemesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0:string}> */
    public static function configurableThemes(): array
    {
        return collect(['SER0100', 'SER0101', 'SER102', 'TH0002', 'TH0003', 'TH0020', 'TH0201'])
            ->mapWithKeys(fn (string $theme): array => [$theme => [$theme]])
            ->all();
    }

    public function test_th0201_has_a_unique_theme_registry_key(): void
    {
        $keys = app(ThemeRegistry::class)->all()->pluck('key');

        $this->assertSame(1, $keys->filter(fn (string $key): bool => $key === 'TH0201')->count());
    }

    public function test_legacy_theme_avatar_uses_the_first_landing_block_preview_and_skips_th_themes(): void
    {
        $themes = app(ThemeRegistry::class)->all()->keyBy('key');

        $this->assertStringEndsWith(
            '/theme-previews/XD0303/hero-slider.png',
            $themes->get('XD0303')['avatar_url'],
        );
        $this->assertNull($themes->get('TH0201')['avatar_url']);
    }

    #[DataProvider('configurableThemes')]
    public function test_theme_home_and_landing_page_use_the_configurable_builder(string $themeKey): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme($themeKey));
        $this->assertNotEmpty($builder->availableBlocks($themeKey));

        $this->postJson("/admin/api/themes/{$themeKey}/activate")->assertOk();
        $homeResponse = $this->get('/vi')->assertOk();
        $homeResponse->assertSee('data-block-type="hero_slider"', false);
        $this->assertDatabaseHas('landing_pages', [
            'website_key' => 'website-main',
            'theme_key' => $themeKey,
            'slug' => 'home',
            'is_home' => true,
        ]);

        $slug = strtolower($themeKey).'-campaign';
        $pageResponse = $this->postJson('/admin/api/landing/pages', [
            'slug' => $slug,
            'status' => 'published',
            'data_by_locale' => [
                'vi' => ['title' => "Landing {$themeKey}"],
                'en' => ['title' => "{$themeKey} landing"],
            ],
        ])->assertCreated();
        $pageId = (int) $pageResponse->json('data.id');

        $this->postJson("/admin/api/landing/pages/{$pageId}/blocks", [
            'block_type' => 'hero_slider',
        ])->assertCreated();

        $this->get(route('site.landing.show', ['locale' => 'vi', 'slug' => $slug]))
            ->assertOk()
            ->assertSee("Landing {$themeKey}")
            ->assertSee('data-block-type="hero_slider"', false);

        $this->assertNotNull(LandingPage::query()->where('theme_key', $themeKey)->where('slug', $slug)->first());
    }
}
