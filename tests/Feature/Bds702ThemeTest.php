<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Bds702ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bds702_is_registered_with_the_expected_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'BDS702');

        $this->assertNotNull($theme);
        $this->assertSame('real_estate', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/BDS702/preview-bds702.svg'));
        $this->assertFileExists(public_path('theme-previews/BDS702/cover-bds702.svg'));
        $this->assertTrue(app(LandingPageBuilder::class)->supportsTheme('BDS702'));
        $this->assertSame([
            'hero_slider',
            'bds702_intro',
            'bds702_featured_projects',
            'bds702_investment_activities',
            'bds702_recommended_projects',
            'bds702_consultation',
            'bds702_partners',
        ], collect(app(LandingPageBuilder::class)->availableBlocks('BDS702'))->pluck('block_type')->all());
        $this->assertStringContainsString('translateY(-34px)', file_get_contents(base_path('themes/BDS702/views/partials/styles.blade.php')));
    }

    public function test_bds702_renders_database_branding_projects_and_contact_form(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');
        $provider->generate($provider->defaultPreset());

        $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
        $profile->update([
            'active_theme_key' => 'BDS702',
            'branding' => array_merge((array) $profile->branding, [
                'logo_url' => '/storage/branding/aurelia-estates.svg',
                'support_hotline' => '0886 702 702',
                'support_email' => 'hello@aurelia.test',
                'support_location' => '82 Đại lộ Bình Minh, Đà Nẵng',
                'copyright_text' => 'Bản quyền thuộc về Aurelia Estates.',
            ]),
        ]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'BDS702', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/aurelia-estates.svg', false)
            ->assertSee('0886 702 702')
            ->assertSee('hello@aurelia.test')
            ->assertSee('82 Đại lộ Bình Minh, Đà Nẵng')
            ->assertSee('Bản quyền thuộc về Aurelia Estates.')
            ->assertSee('data-block-type="bds702_featured_projects"', false)
            ->assertSee('action="'.route('site.contact.submit').'"', false)
            ->assertDontSee('196 Nguyễn Đình Chiểu')
            ->assertDontSee('admin@demo031040.web30s.vn')
            ->assertDontSee('(028) 625 63737');
    }
}
