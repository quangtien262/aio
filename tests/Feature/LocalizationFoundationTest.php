<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Core\Themes\ThemeTranslationService;
use App\Enums\TranslationStatus;
use App\Events\TranslationPublished;
use App\Events\WebsiteLocalesChanged;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\LocalizedRoute;
use App\Models\Site;
use App\Models\ThemeTranslation;
use App\Models\WebsiteLocale;
use App\Support\FrontendRouteUrl;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedRouteRegistry;
use App\Support\Localization\TranslationRevision;
use App\Support\Localization\TranslationWorkflowManager;
use App\Support\Localization\WebsiteLocaleManager;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locales_are_configured_and_cached_independently_per_website(): void
    {
        Event::fake([WebsiteLocalesChanged::class]);

        $manager = app(WebsiteLocaleManager::class);
        $context = app(LocaleContext::class);
        $manager->ensureSystemLocale('fr', 'French', 'Français');
        $manager->provisionWebsite('website-a');
        $manager->provisionWebsite('website-b');
        $manager->addLocale('website-a', 'fr', ['is_published' => true]);

        $this->assertContains('fr', $context->editableLocales('website-a'));
        $this->assertContains('fr', $context->publicLocales('website-a'));
        $this->assertNotContains('fr', $context->editableLocales('website-b'));
        $this->assertNotContains('fr', $context->publicLocales('website-b'));
        Event::assertDispatched(
            WebsiteLocalesChanged::class,
            fn (WebsiteLocalesChanged $event): bool => $event->websiteKey === 'website-a',
        );
    }

    public function test_every_backend_locale_option_exposes_a_storefront_image_icon(): void
    {
        $websiteKey = 'locale-icon-test';
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale('zh-Hant-HK', 'Traditional Chinese (Hong Kong)', '繁體中文');
        $manager->ensureSystemLocale('eo', 'Esperanto', 'Esperanto');
        $manager->provisionWebsite($websiteKey);
        $manager->addLocale($websiteKey, 'zh-Hant-HK', ['is_published' => true]);
        $manager->addLocale($websiteKey, 'eo', ['is_published' => true]);

        $options = collect(app(LocaleContext::class)->options($websiteKey))->keyBy('code');

        $this->assertSame('HK', data_get($options->get('zh-Hant-HK'), 'country_code'));
        $this->assertNull(data_get($options->get('eo'), 'country_code'));

        foreach ($options as $option) {
            $this->assertStringStartsWith(
                'data:image/svg+xml;base64,',
                (string) ($option['icon_url'] ?? ''),
            );
            $this->assertNotSame('', (string) ($option['icon_alt'] ?? ''));
        }
    }

    public function test_fallback_chain_follows_each_locale_configuration_recursively(): void
    {
        $websiteKey = 'recursive-fallback-test';
        $manager = app(WebsiteLocaleManager::class);

        foreach ([
            ['fr', 'French', 'Français'],
            ['fr-CA', 'Canadian French', 'Français canadien'],
        ] as [$code, $name, $nativeName]) {
            $manager->ensureSystemLocale($code, $name, $nativeName);
        }

        $manager->provisionWebsite($websiteKey);

        foreach (['fr', 'fr-CA'] as $code) {
            if (! WebsiteLocale::query()->forWebsite($websiteKey)->where('locale', $code)->exists()) {
                $manager->addLocale($websiteKey, $code, ['is_published' => false]);
            }
        }

        $manager->updateLocale($websiteKey, 'fr-CA', ['fallback_locale' => 'fr']);
        $manager->updateLocale($websiteKey, 'fr', ['fallback_locale' => 'en']);
        $manager->updateLocale($websiteKey, 'en', ['fallback_locale' => 'vi']);

        $this->assertSame(
            ['fr-CA', 'fr', 'en', 'vi'],
            app(LocaleContext::class)->fallbackChain('fr-CA', $websiteKey),
        );
    }

    public function test_admin_locale_api_changes_only_the_selected_website(): void
    {
        $this->enableCmsModule();
        Site::query()->create([
            'name' => 'Website A',
            'website_key' => 'website-a',
            'domain' => 'website-a.test',
            'theme_key' => 'SHOP601',
            'status' => 'active',
        ]);
        Site::query()->create([
            'name' => 'Website B',
            'website_key' => 'website-b',
            'domain' => 'website-b.test',
            'theme_key' => 'SHOP601',
            'status' => 'active',
        ]);
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');

        $this->withHeader('X-Website-Key', 'website-a')
            ->postJson('/admin/api/themes/locales', [
                'code' => 'fr-CA',
                'name' => 'Canadian French',
                'native_name' => 'Français canadien',
            ])
            ->assertCreated()
            ->assertJsonPath('data.website_key', 'website-a')
            ->assertJsonPath('data.locale.code', 'fr-CA')
            ->assertJsonPath('data.locale.is_enabled_for_editing', true)
            ->assertJsonPath('data.locale.is_published', false);

        $this->withHeader('X-Website-Key', 'website-a')
            ->getJson('/admin/api/themes/locales/fr-CA/preflight?theme_key=SHOP601')
            ->assertOk()
            ->assertJsonPath('data.website_key', 'website-a')
            ->assertJsonPath('data.locale.code', 'fr-CA')
            ->assertJsonStructure([
                'data' => [
                    'checked_at',
                    'locale' => [
                        'release_readiness' => [
                            'publishable',
                            'strict_ready',
                            'critical' => ['required', 'translated', 'pending', 'coverage', 'scopes'],
                            'extended' => ['required', 'translated', 'pending', 'coverage', 'scopes'],
                        ],
                    ],
                ],
            ]);

        $websiteB = $this->withHeader('X-Website-Key', 'website-b')
            ->getJson('/admin/api/themes/locales')
            ->assertOk()
            ->assertJsonPath('data.website_key', 'website-b');

        $this->assertNotContains(
            'fr-CA',
            collect($websiteB->json('data.locales'))->pluck('code')->all(),
        );
    }

    public function test_unpublished_locale_is_editable_but_not_routable_until_published(): void
    {
        $this->enableCmsModule();
        ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', 'website-main')
            ->delete();
        CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', 'website-main')
            ->delete();
        LandingPageData::query()
            ->withoutGlobalScopes()
            ->whereHas('landingPage', fn ($query) => $query->where('website_key', 'website-main'))
            ->delete();
        LandingPageBlockData::query()
            ->withoutGlobalScopes()
            ->whereHas(
                'landingPageBlock.landingPage',
                fn ($query) => $query->where('website_key', 'website-main'),
            )
            ->delete();
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale('fr', 'French', 'Français');
        $manager->addLocale('website-main', 'fr', ['is_published' => false]);

        $this->assertContains('fr', app(LocaleContext::class)->editableLocales('website-main'));
        $this->assertNotContains('fr', app(LocaleContext::class)->publicLocales('website-main'));
        $this->get('/fr')->assertNotFound();

        $manager->updateLocale('website-main', 'fr', ['is_published' => true]);

        $this->assertContains('fr', app(LocaleContext::class)->publicLocales('website-main'));
        $this->assertNotSame(404, $this->get('/fr')->getStatusCode());
    }

    public function test_localized_route_registry_enforces_website_locale_path_uniqueness(): void
    {
        $registry = app(LocalizedRouteRegistry::class);

        $route = $registry->register(
            'vi',
            'cms_page',
            101,
            '/gioi-thieu/',
            ['is_published' => true],
        );

        $this->assertSame('/gioi-thieu', $route->path);
        $this->assertSame(
            101,
            (int) $registry->resolvePublic('vi', '/gioi-thieu')?->resource_id,
        );
        $this->assertSame(
            '/gioi-thieu',
            $registry->canonicalPath('cms_page', 101, 'vi'),
        );

        $this->expectException(ValidationException::class);
        $registry->register(
            'vi',
            'cms_post',
            202,
            '/gioi-thieu',
            ['is_published' => true],
        );
    }

    public function test_language_switcher_uses_the_exact_published_slug_for_each_locale(): void
    {
        $registry = app(LocalizedRouteRegistry::class);
        $registry->register('vi', 'cms_page', 501, '/p/gioi-thieu', ['is_published' => true]);
        $registry->register('en', 'cms_page', 501, '/p/about-us', ['is_published' => true]);

        $request = Request::create('/vi/p/gioi-thieu?source=header');
        $route = (new Route('GET', '/{locale}/p/{slug}', fn () => null))->bind($request);
        $request->setRouteResolver(fn (): Route => $route);
        $this->app->instance('request', $request);

        $this->assertSame(
            '/en/p/about-us?source=header',
            FrontendRouteUrl::switchLocale('en', false),
        );
    }

    public function test_language_switcher_falls_back_to_locale_home_when_the_page_is_not_translated(): void
    {
        $registry = app(LocalizedRouteRegistry::class);
        $registry->register('vi', 'cms_page', 502, '/p/chinh-sach', ['is_published' => true]);

        $request = Request::create('/vi/p/chinh-sach');
        $route = (new Route('GET', '/{locale}/p/{slug}', fn () => null))->bind($request);
        $request->setRouteResolver(fn (): Route => $route);
        $this->app->instance('request', $request);

        $this->assertSame('/en', FrontendRouteUrl::switchLocale('en', false));
    }

    public function test_cms_page_language_switcher_recovers_when_current_locale_route_is_missing(): void
    {
        $page = CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Giới thiệu',
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'body' => 'Nội dung tiếng Việt',
        ]);
        CmsPageTranslation::query()->create([
            'cms_page_id' => $page->id,
            'website_key' => 'website-main',
            'locale' => 'en',
            'title' => 'About us',
            'slug' => 'about',
            'body' => 'English content',
            'translation_status' => TranslationStatus::Published,
            'translation_published_at' => now(),
        ]);

        app(LocalizedRouteRegistry::class)->register(
            'en',
            'cms_page',
            $page->id,
            '/p/about',
            ['is_published' => true],
        );
        LocalizedRoute::query()
            ->where('locale', 'vi')
            ->where('resource_type', 'cms_page')
            ->where('resource_id', (string) $page->id)
            ->delete();

        $request = Request::create('/vi/p/gioi-thieu?source=header');
        $route = (new Route('GET', '/{locale}/p/{slug}', fn () => null))
            ->name('site.pages.show')
            ->bind($request);
        $request->setRouteResolver(fn (): Route => $route);
        $this->app->instance('request', $request);

        $this->assertSame(
            '/en/p/about?source=header',
            FrontendRouteUrl::switchLocale('en', false),
        );
    }

    public function test_translation_workflow_tracks_revisions_and_only_publishes_after_review(): void
    {
        Event::fake([TranslationPublished::class]);
        $page = LandingPage::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'SHOP601',
            'page_type' => 'landing',
            'slug' => 'workflow-test',
            'status' => 'draft',
            'template' => 'landing',
            'is_home' => false,
        ]);
        $translation = LandingPageData::query()->create([
            'landing_page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Initial title',
        ]);
        LandingPageData::query()->create([
            'landing_page_id' => $page->id,
            'locale' => 'vi',
            'title' => 'Tiêu đề nguồn',
            'translation_status' => TranslationStatus::Published,
        ]);
        $workflow = app(TranslationWorkflowManager::class);
        $sourceRevision = TranslationRevision::fingerprint(['title' => 'Tiêu đề nguồn']);

        $translation = $workflow->saveDraft(
            $translation,
            ['title' => 'Translated title'],
            $sourceRevision,
        );

        $this->assertSame(TranslationStatus::Draft, $translation->translation_status);
        $this->assertSame($sourceRevision, $translation->source_revision);
        $this->assertNotNull($translation->translation_revision);
        $this->assertSame(
            'Tiêu đề nguồn',
            data_get(
                app(LandingPageBuilder::class)->viewData(
                    $page->fresh(['data', 'blocks.data']),
                    'en',
                    'vi',
                ),
                'landingPage.title',
            ),
        );

        $translation = $workflow->transition($translation, TranslationStatus::Ready);
        $translation = $workflow->transition($translation, TranslationStatus::Published);

        $this->assertTrue($translation->isPublishedTranslation());
        $this->assertNotNull($translation->reviewed_at);
        $this->assertNotNull($translation->translation_published_at);
        $this->assertSame(
            'Translated title',
            data_get(
                app(LandingPageBuilder::class)->viewData(
                    $page->fresh(['data', 'blocks.data']),
                    'en',
                    'vi',
                ),
                'landingPage.title',
            ),
        );
        Event::assertDispatched(TranslationPublished::class);

        $this->assertTrue($workflow->markOutdatedWhenSourceChanges(
            $translation,
            ['title' => 'Nguồn đã thay đổi'],
        ));
        $this->assertSame(
            TranslationStatus::Outdated,
            $translation->refresh()->translation_status,
        );
    }

    public function test_published_block_translation_is_authoritative_even_when_its_optional_content_is_empty(): void
    {
        $page = app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $block = $page->blocks()->with('data')->firstOrFail();
        $source = $block->data->firstWhere('locale', 'vi');
        $translation = $block->data->firstWhere('locale', 'en');

        $source->forceFill([
            'title' => 'Nội dung nguồn không được rò rỉ',
            'subtitle' => 'Nhãn phụ nguồn',
            'content' => json_encode(['items' => [['title' => 'Mục tiếng Việt']]], JSON_UNESCAPED_UNICODE),
            'translation_status' => TranslationStatus::Published,
            'translation_published_at' => now(),
        ])->save();
        $translation->forceFill([
            'title' => 'Authoritative English title',
            'subtitle' => null,
            'content' => json_encode(['items' => []], JSON_UNESCAPED_UNICODE),
            'translation_status' => TranslationStatus::Published,
            'reviewed_at' => now(),
            'translation_published_at' => now(),
        ])->save();

        $serialized = app(LandingPageBuilder::class)->serializeBlock(
            $block->fresh('data'),
            'en',
            'vi',
        );

        $this->assertSame('Authoritative English title', data_get($serialized, 'data.title'));
        $this->assertNull(data_get($serialized, 'data.subtitle'));
        $this->assertSame([], data_get($serialized, 'data.content.items'));
        $this->assertSame([], data_get($serialized, 'data_by_locale.en.content.items'));
    }

    public function test_theme_translation_runtime_cache_is_isolated_by_website(): void
    {
        $manager = app(WebsiteLocaleManager::class);
        $manager->provisionWebsite('website-a');
        $manager->provisionWebsite('website-b');
        $manager->ensureSystemLocale('en-US', 'American English', 'English (US)');
        $manager->addLocale('website-a', 'en-US', ['is_published' => true]);
        $siteContext = app(SiteContext::class);
        $service = app(ThemeTranslationService::class);

        $siteContext->set(null, 'website-a');
        $this->assertSame(
            'Account',
            $service->bladeText('SHOP601', 'en-US', 'SHOP601.header.account'),
        );
        $service->saveOverrides('SHOP601', 'en-US', [[
            'key' => 'qa.website_isolation',
            'value' => 'Website A',
        ]]);
        ThemeTranslation::query()->create([
            'theme_key' => 'SHOP601',
            'locale' => 'en',
            'group' => 'static',
            'translation_key' => 'qa.draft_is_hidden',
            'value' => 'Draft value',
            'translation_status' => TranslationStatus::Draft,
        ]);
        $this->assertSame(
            'Website A',
            $service->bladeText('SHOP601', 'en-US', 'qa.website_isolation'),
        );
        $this->assertSame(
            'Published fallback',
            $service->bladeText(
                'SHOP601',
                'en-US',
                'qa.draft_is_hidden',
                'Published fallback',
            ),
        );

        $siteContext->set(null, 'website-b');
        $service->saveOverrides('SHOP601', 'en-US', [[
            'key' => 'qa.website_isolation',
            'value' => 'Website B',
        ]]);
        $this->assertSame(
            'Website B',
            $service->bladeText('SHOP601', 'en-US', 'qa.website_isolation'),
        );
    }

    public function test_foundation_schema_is_available_for_landing_pages_and_themes(): void
    {
        $this->assertTrue(Schema::hasTable('website_locales'));
        $this->assertTrue(Schema::hasTable('localized_routes'));

        foreach (['theme_translations', 'landing_page_data', 'landing_page_block_data'] as $table) {
            $this->assertTrue(Schema::hasColumns($table, [
                'translation_status',
                'source_revision',
                'translation_revision',
                'translation_published_at',
            ]));
        }
    }

    private function enableCmsModule(): void
    {
        $manager = app(ModuleManager::class);
        $manager->install('cms');
        $manager->enable('cms');
    }

    public function test_release_readiness_gate_fails_for_a_public_locale_with_missing_content(): void
    {
        app(WebsiteLocaleManager::class)->provisionWebsite('website-main');

        CmsPage::query()->create([
            'website_key' => 'website-main',
            'title' => 'Trang nguồn',
            'slug' => 'trang-nguon',
            'status' => 'published',
            'body' => '<p>Nội dung nguồn</p>',
            'publish_at' => now(),
        ]);

        $this->artisan('localization:audit', [
            '--website' => 'website-main',
            '--strict' => true,
        ])->assertExitCode(0);

        $this->artisan('localization:audit', [
            '--website' => 'website-main',
            '--require-ready' => true,
            '--json' => true,
        ])
            ->expectsOutputToContain('locale_not_release_ready')
            ->assertExitCode(1);
    }
}
