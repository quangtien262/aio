<?php

namespace Tests\Feature;

use App\Models\SiteProfile;
use App\Models\WebsiteLocale;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeLanguageHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_public_locale_does_not_render_a_language_control(): void
    {
        SiteProfile::create(['site_name' => 'Single locale', 'website_type' => 'ecommerce', 'active_theme_key' => 'EC906']);
        app(WebsiteLocaleManager::class)->provisionWebsite('website-main');
        WebsiteLocale::withoutGlobalScopes()->where('website_key', 'website-main')->where('locale', '!=', 'vi')->update(['is_published' => false]);
        app(LocaleContext::class)->flush('website-main');
        $this->get('/vi')->assertOk()->assertDontSee('data-storefront-language-switcher', false);
    }

    public function test_language_controls_stay_in_the_header_on_every_theme(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Language audit', 'website_type' => 'ecommerce', 'active_theme_key' => 'EC906']);
        app(WebsiteLocaleManager::class)->provisionWebsite('website-main');
        WebsiteLocale::withoutGlobalScopes()->where('website_key', 'website-main')->where('locale', 'en')->update(['is_enabled_for_editing' => true, 'is_published' => true]);
        app(LocaleContext::class)->flush('website-main');
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            foreach (['home' => '/vi', 'search' => '/vi/tim-kiem'] as $mode => $url) {
                $response = $this->get($url)->assertOk();
                $dom = new \DOMDocument;
                @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
                $xpath = new \DOMXPath($dom);
                $controls = $xpath->query('//*[@data-storefront-language-switcher]');
                $this->assertGreaterThan(0, $controls->length, "$theme $mode: missing language control");
                $this->assertSame($controls->length, $xpath->query('//header//*[@data-storefront-language-switcher]')->length, "$theme $mode: language control outside header");
                $this->assertGreaterThan(0, $xpath->query('//header//*[@data-locale-code="en" and contains(@href, "/en")]')->length, "$theme $mode: missing English route");
                if ($folder = getenv('LANGUAGE_PREVIEW')) {
                    if (! is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }
                    file_put_contents($folder.'/'.$theme.'-'.$mode.'.html', $response->getContent());
                }
            }
        }
    }
}
