<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeTranslationService;
use App\Enums\TranslationStatus;
use App\Events\TranslationPublished;
use App\Events\WebsiteLocalesChanged;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\LandingPage;
use App\Models\LandingPageData;
use App\Models\ThemeTranslation;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedRouteRegistry;
use App\Support\Localization\TranslationRevision;
use App\Support\Localization\TranslationWorkflowManager;
use App\Support\Localization\WebsiteLocaleManager;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_locale_api_changes_only_the_selected_website(): void
    {
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

    public function test_translation_workflow_tracks_revisions_and_only_publishes_after_review(): void
    {
        Event::fake([TranslationPublished::class]);
        $page = LandingPage::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'TH0001',
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
            $service->bladeText('TH0001', 'en-US', 'common.account'),
        );
        ThemeTranslation::query()->create([
            'theme_key' => 'TH0001',
            'locale' => 'en',
            'group' => 'static',
            'translation_key' => 'qa.website_isolation',
            'value' => 'Website A',
            'translation_status' => TranslationStatus::Published,
        ]);
        ThemeTranslation::query()->create([
            'theme_key' => 'TH0001',
            'locale' => 'en',
            'group' => 'static',
            'translation_key' => 'qa.draft_is_hidden',
            'value' => 'Draft value',
            'translation_status' => TranslationStatus::Draft,
        ]);
        $this->assertSame(
            'Website A',
            $service->bladeText('TH0001', 'en', 'qa.website_isolation'),
        );
        $this->assertSame(
            'Published fallback',
            $service->bladeText(
                'TH0001',
                'en',
                'qa.draft_is_hidden',
                'Published fallback',
            ),
        );

        $siteContext->set(null, 'website-b');
        ThemeTranslation::query()->create([
            'theme_key' => 'TH0001',
            'locale' => 'en',
            'group' => 'static',
            'translation_key' => 'qa.website_isolation',
            'value' => 'Website B',
            'translation_status' => TranslationStatus::Published,
        ]);
        $this->assertSame(
            'Website B',
            $service->bladeText('TH0001', 'en', 'qa.website_isolation'),
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
