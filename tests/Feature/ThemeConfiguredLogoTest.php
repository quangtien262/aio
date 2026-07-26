<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeConfiguredLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_storefront_themes_preserve_and_render_the_website_logo(): void
    {
        $themes = ['CA0050', 'SHOP603', 'SHOP604', 'SHOP605', 'NT503'];

        foreach ($themes as $themeKey) {
            $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme($themeKey);
            $this->assertNotNull($provider, "Missing demo provider for {$themeKey}");

            $provider->generate($provider->defaultPreset());

            $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
            $logoUrl = "/storage/branding/{$themeKey}-custom-logo.svg";
            $profile->update([
                'branding' => array_merge((array) $profile->branding, ['logo_url' => $logoUrl]),
            ]);

            // Recreating demo content must not wipe a logo uploaded in Website Settings.
            $provider->generate($provider->defaultPreset());

            $this->assertSame(
                $logoUrl,
                data_get(SiteProfile::query()->where('website_key', 'website-main')->firstOrFail()->branding, 'logo_url'),
                "{$themeKey} demo provider replaced the configured logo.",
            );

            $this->get(route('site.home', ['locale' => 'vi']))
                ->assertOk()
                ->assertSee('src="'.$logoUrl.'"', false);
        }
    }
}
