<?php

namespace Tests\Feature;

use App\Core\Cms\CmsMenuLocalization;
use App\Core\Cms\CmsMenuResolver;
use App\Enums\TranslationStatus;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\ContentTranslation;
use App\Models\LocalizedRoute;
use App\Models\ThemeTranslation;
use App\Support\Localization\LocalizedRouteRegistry;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsMenuPublicResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_resolver_localizes_nested_labels_and_preserves_canonical_structure(): void
    {
        $this->publishEnglishFor('website-main');
        $menu = $this->menu('website-main');
        $sourceItems = $menu->items;
        $translatedItems = $sourceItems;
        $translatedItems[0]['label'] = 'About us';
        $translatedItems[0]['url'] = '/tampered';
        $translatedItems[0]['children'][0]['label'] = 'Our team';
        $translatedItems[0]['children'][0]['url'] = '/tampered-child';
        $this->translation($menu, 'en', $translatedItems);
        app(LocalizedRouteRegistry::class)->register(
            'vi',
            'cms_page',
            101,
            '/p/gioi-thieu',
            ['is_published' => true],
            'website-main',
        );
        app(LocalizedRouteRegistry::class)->register(
            'en',
            'cms_page',
            101,
            '/p/about-us',
            ['is_published' => true],
            'website-main',
        );

        $resolved = app(CmsMenuResolver::class)->items(
            'primary-navigation',
            'website-main',
            'en',
        );

        $this->assertSame('About us', $resolved[0]['label']);
        $this->assertSame('/en/p/about-us', $resolved[0]['url']);
        $this->assertSame('Our team', $resolved[0]['children'][0]['label']);
        $this->assertSame('/en', $resolved[0]['children'][0]['url']);
        $this->assertSame('https://example.com/pricing', $resolved[1]['url']);
        $this->assertSame('#contact', $resolved[2]['url']);
        $this->assertSame(
            $sourceItems[0]['item_key'],
            $resolved[0]['item_key'],
        );
    }

    public function test_public_resolver_uses_published_legacy_labels_only_when_v2_is_missing(): void
    {
        $this->publishEnglishFor('website-main');
        $menu = $this->menu('website-main');
        ThemeTranslation::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'cms_menu.primary-navigation.0.label',
            'value' => 'Legacy about',
            'translation_status' => TranslationStatus::Published,
        ]);

        $legacyResolved = app(CmsMenuResolver::class)->items(
            'primary-navigation',
            'website-main',
            'en',
        );
        $this->assertSame('Legacy about', $legacyResolved[0]['label']);

        $translatedItems = $menu->items;
        $translatedItems[0]['label'] = 'Current about';
        $translatedItems[0]['children'][0]['label'] = 'Current team';
        $this->translation($menu, 'en', $translatedItems);

        $currentResolved = app(CmsMenuResolver::class)->items(
            'primary-navigation',
            'website-main',
            'en',
        );
        $this->assertSame('Current about', $currentResolved[0]['label']);
        $this->assertSame('Current team', $currentResolved[0]['children'][0]['label']);
    }

    public function test_public_resolver_falls_back_to_source_for_draft_or_blank_labels(): void
    {
        $this->publishEnglishFor('website-main');
        $menu = $this->menu('website-main');
        $translatedItems = $menu->items;
        $translatedItems[0]['label'] = '';
        $translatedItems[0]['children'][0]['label'] = '';
        $this->translation(
            $menu,
            'en',
            $translatedItems,
            TranslationStatus::Published,
        );

        $published = app(CmsMenuResolver::class)->items(
            'primary-navigation',
            'website-main',
            'en',
        );
        $this->assertSame('Giới thiệu', $published[0]['label']);
        $this->assertSame('Đội ngũ', $published[0]['children'][0]['label']);

        ContentTranslation::query()
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', 'en')
            ->update(['translation_status' => TranslationStatus::Draft->value]);

        $draft = app(CmsMenuResolver::class)->items(
            'primary-navigation',
            'website-main',
            'en',
        );
        $this->assertSame('Giới thiệu', $draft[0]['label']);
    }

    public function test_public_resolver_never_leaks_menu_between_websites(): void
    {
        $this->publishEnglishFor('website-one');
        $this->publishEnglishFor('website-two');
        $first = $this->menu('website-one', 'Menu one');
        $second = $this->menu('website-two', 'Menu two');
        $firstItems = $first->items;
        $secondItems = $second->items;
        $firstItems[0]['label'] = 'Website one';
        $firstItems[0]['children'][0]['label'] = 'Team one';
        $secondItems[0]['label'] = 'Website two';
        $secondItems[0]['children'][0]['label'] = 'Team two';
        $this->translation($first, 'en', $firstItems);
        $this->translation($second, 'en', $secondItems);

        $resolver = app(CmsMenuResolver::class);

        $this->assertSame(
            'Website one',
            $resolver->items('primary-navigation', 'website-one', 'en')[0]['label'],
        );
        $this->assertSame(
            'Website two',
            $resolver->items('primary-navigation', 'website-two', 'en')[0]['label'],
        );
    }

    public function test_public_resolver_applies_canary_stage_per_theme_without_cache_leakage(): void
    {
        config()->set('localized-content.rollout.stages.cms_menu', 'canary');
        config()->set('localized-content.rollout.canaries.cms_menu', [
            'websites' => [],
            'themes' => ['BOOK920'],
        ]);
        config()->set('localized-content.rollout.overrides.cms_menu', [
            'websites' => [],
            'themes' => [],
        ]);
        config()->set('localized-content.rollout.websites', []);
        config()->set('localized-content.rollout.themes', []);
        config()->set('localized-content.rollout.legacy_fallback', false);

        $this->publishEnglishFor('website-main');
        $menu = $this->menu('website-main');
        $sourceLabel = $menu->items[0]['label'];
        $translatedItems = $menu->items;
        $translatedItems[0]['label'] = 'Canary about';
        $translatedItems[0]['children'][0]['label'] = 'Canary team';
        $this->translation($menu, 'en', $translatedItems);

        $resolver = app(CmsMenuResolver::class);
        $canary = $resolver->items(
            'primary-navigation',
            'website-main',
            'en',
            'BOOK920',
        );
        $legacy = $resolver->items(
            'primary-navigation',
            'website-main',
            'en',
            'XD0301',
        );

        $this->assertSame('Canary about', $canary[0]['label']);
        $this->assertSame($sourceLabel, $legacy[0]['label']);
    }

    public function test_legacy_reader_uses_legacy_menu_as_primary_even_when_new_reader_fallback_is_disabled(): void
    {
        config()->set('localized-content.rollout.reader', 'legacy');
        config()->set('localized-content.rollout.legacy_fallback', false);

        $this->publishEnglishFor('website-main');
        $this->menu('website-main');
        ThemeTranslation::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'site-content:website-main',
            'locale' => 'en',
            'group' => 'content',
            'translation_key' => 'cms_menu.primary-navigation.0.label',
            'value' => 'Legacy primary label',
            'translation_status' => TranslationStatus::Published,
        ]);

        $resolved = app(CmsMenuResolver::class)->items(
            'primary-navigation',
            'website-main',
            'en',
            'BOOK920',
        );

        $this->assertSame('Legacy primary label', $resolved[0]['label']);
    }

    public function test_resource_identity_resolves_target_canonical_route_when_source_route_is_missing(): void
    {
        $this->publishEnglishFor('website-main');
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giá»›i thiá»‡u',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => 'Ná»™i dung',
        ]);
        LocalizedRoute::query()
            ->where('resource_type', 'cms_page')
            ->where('resource_id', (string) $page->id)
            ->where('locale', 'vi')
            ->delete();
        app(LocalizedRouteRegistry::class)->register(
            'en',
            'cms_page',
            $page->id,
            '/p/about',
            ['is_published' => true],
            'website-main',
        );
        CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Primary',
            'location' => 'primary',
            'items' => [[
                'label' => 'Giá»›i thiá»‡u',
                'url' => '/p/gioi-thieu',
                'link_type' => 'page',
                'link_value' => (string) $page->id,
                'resource_type' => 'cms_page',
                'resource_id' => (string) $page->id,
            ]],
        ]);

        $resolved = app(CmsMenuResolver::class)->items(
            'primary',
            'website-main',
            'en',
        );

        $this->assertSame('/en/p/about', $resolved[0]['url']);
    }

    public function test_legacy_page_url_is_recovered_by_identity_without_cross_locale_redirect(): void
    {
        $this->publishEnglishFor('website-main');
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giá»›i thiá»‡u',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => 'Ná»™i dung',
        ]);
        LocalizedRoute::query()
            ->where('resource_type', 'cms_page')
            ->where('resource_id', (string) $page->id)
            ->where('locale', 'vi')
            ->delete();
        app(LocalizedRouteRegistry::class)->register(
            'en',
            'cms_page',
            $page->id,
            '/p/about',
            ['is_published' => true],
            'website-main',
        );
        CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Legacy primary',
            'location' => 'primary',
            'items' => [[
                'label' => 'Giá»›i thiá»‡u',
                'url' => '/p/gioi-thieu',
            ]],
        ]);

        $resolved = app(CmsMenuResolver::class)->items(
            'primary',
            'website-main',
            'en',
        );

        $this->assertSame('/en/p/about', $resolved[0]['url']);
    }

    public function test_known_internal_resource_without_target_translation_stays_in_requested_locale(): void
    {
        $this->publishEnglishFor('website-main');
        $menu = CmsMenu::query()->create([
            'website_key' => 'website-main',
            'name' => 'Primary',
            'location' => 'primary',
            'items' => [[
                'label' => 'Giá»›i thiá»‡u',
                'url' => '/p/gioi-thieu',
                'link_type' => 'page',
                'link_value' => '404',
                'resource_type' => 'cms_page',
                'resource_id' => '404',
            ]],
        ]);

        $resolved = app(CmsMenuResolver::class)->items(
            'primary',
            'website-main',
            'en',
        );

        $this->assertSame('/en', $resolved[0]['url']);
        $this->assertSame('404', $menu->items[0]['resource_id']);
    }

    private function publishEnglishFor(string $websiteKey): void
    {
        app(WebsiteLocaleManager::class)->updateLocale(
            $websiteKey,
            'en',
            ['is_published' => true],
        );
    }

    private function menu(
        string $websiteKey,
        string $name = 'Primary navigation',
    ): CmsMenu {
        return CmsMenu::query()->create([
            'website_key' => $websiteKey,
            'name' => $name,
            'location' => 'primary-navigation',
            'items' => [
                [
                    'label' => 'Giới thiệu',
                    'url' => '/p/gioi-thieu',
                    'children' => [
                        [
                            'label' => 'Đội ngũ',
                            'url' => '/p/doi-ngu',
                        ],
                    ],
                ],
                [
                    'label' => 'External',
                    'url' => 'https://example.com/pricing',
                ],
                [
                    'label' => 'Contact',
                    'url' => '#contact',
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function translation(
        CmsMenu $menu,
        string $locale,
        array $items,
        TranslationStatus $status = TranslationStatus::Published,
    ): ContentTranslation {
        return ContentTranslation::query()->create([
            'website_key' => $menu->website_key,
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => $locale,
            'payload' => app(CmsMenuLocalization::class)->storagePayload(
                $menu->items,
                ['items' => $items],
            ),
            'translation_status' => $status,
            'translation_published_at' => $status === TranslationStatus::Published
                ? now()
                : null,
        ]);
    }
}
