<?php

namespace Tests\Feature;

use App\Core\Cms\CmsMenuLocalization;
use App\Core\Themes\ThemeRegistry;
use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Models\SiteProfile;
use App\Models\WebsiteLocale;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeMenuLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_theme_renders_the_published_menu_translation_on_english_homepage(): void
    {
        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Menu localization contract',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DN202',
            'branding' => [],
        ]);
        app(WebsiteLocaleManager::class)->provisionWebsite('website-main');
        WebsiteLocale::query()
            ->withoutGlobalScopes()
            ->where('website_key', 'website-main')
            ->where('locale', 'en')
            ->update(['is_enabled_for_editing' => true, 'is_published' => true]);
        app(LocaleContext::class)->flush('website-main');

        $menu = CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Primary navigation',
            'location' => 'primary-navigation',
            'items' => [[
                'label' => 'SOURCE MENU LABEL MUST NOT LEAK',
                'link_type' => 'home',
                'target' => '_self',
            ]],
        ])->fresh();
        $translatedItems = $menu->items;
        $translatedItems[0]['label'] = 'PUBLISHED ENGLISH MENU SENTINEL';
        ContentTranslation::query()->create([
            'website_key' => 'website-main',
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
            'payload' => app(CmsMenuLocalization::class)->storagePayload(
                $menu->items,
                ['items' => $translatedItems],
            ),
            'translation_status' => 'published',
            'translation_published_at' => now(),
        ]);

        $failures = [];

        foreach (app(ThemeRegistry::class)->all() as $theme) {
            $themeKey = (string) $theme['key'];
            $profile->forceFill(['active_theme_key' => $themeKey])->save();

            $response = $this->get('/en');

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$themeKey}: HTTP {$response->getStatusCode()}";

                continue;
            }

            $html = $response->getContent();

            if (! str_contains($html, 'PUBLISHED ENGLISH MENU SENTINEL')) {
                $failures[] = "{$themeKey}: published English menu label is missing";
            }

            if (str_contains($html, 'SOURCE MENU LABEL MUST NOT LEAK')) {
                $failures[] = "{$themeKey}: source menu label leaked";
            }
        }

        $this->assertSame([], $failures, "Theme menu localization failures:\n".implode("\n", $failures));
    }
}
