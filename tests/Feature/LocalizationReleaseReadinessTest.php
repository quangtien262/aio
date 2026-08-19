<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\CmsPost;
use App\Models\ContentTranslation;
use App\Models\LocalizedRoute;
use App\Models\WebsiteLocale;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizationReleaseReadiness;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\TranslationRevision;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalizationReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_orphan_source_translation_is_excluded_from_readiness_and_can_be_pruned_explicitly(): void
    {
        $websiteKey = 'release-orphan-test';
        CmsPost::query()->create([
            'website_key' => $websiteKey,
            'title' => 'Tin nguồn',
            'slug' => 'tin-nguon',
            'status' => 'published',
            'body' => '<p>Nội dung</p>',
            'publish_at' => now(),
        ]);
        $orphanPayload = ['title' => 'Mồ côi', 'slug' => 'mo-coi'];
        $revision = TranslationRevision::fingerprint($orphanPayload);
        ContentTranslation::query()->create([
            'website_key' => $websiteKey,
            'resource_type' => 'cms_post',
            'resource_id' => '999999',
            'locale' => 'vi',
            'payload' => $orphanPayload,
            'translation_status' => TranslationStatus::Published,
            'source_revision' => $revision,
            'translation_revision' => $revision,
            'translation_published_at' => now(),
        ]);
        $report = app(LocalizationReleaseReadiness::class)->report($websiteKey, ['en']);
        $this->assertSame(1, $report['en']['scopes']['content']['required']);
        $this->assertSame(1, $report['en']['pending']);

        $this->artisan('localization:prune-orphans', ['--website' => $websiteKey])
            ->expectsOutputToContain('1 orphan translation row(s) found')
            ->assertSuccessful();
        $this->assertDatabaseHas('content_translations', ['resource_id' => '999999']);

        $this->artisan('localization:prune-orphans', [
            '--website' => $websiteKey,
            '--force' => true,
        ])->expectsOutputToContain('1 orphan translation row(s) deleted')
            ->assertSuccessful();
        $this->assertDatabaseMissing('content_translations', ['resource_id' => '999999']);
    }

    public function test_target_locale_cannot_be_published_until_all_source_content_is_ready(): void
    {
        config()->set('localized-content.release.critical_resource_types', ['cms_post']);
        $websiteKey = 'release-gate-test';
        $post = CmsPost::query()->create([
            'website_key' => $websiteKey,
            'title' => 'Tin nguồn',
            'slug' => 'tin-nguon',
            'status' => 'published',
            'body' => '<p>Nội dung</p>',
            'publish_at' => now(),
        ]);
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale('en', 'English', 'English');
        $manager->provisionWebsite($websiteKey);

        try {
            $manager->updateLocale($websiteKey, 'en', ['is_published' => true]);
            $this->fail('Publishing an incomplete locale should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_published', $exception->errors());
        }

        $repository = app(LocalizedContentRepository::class);
        $translation = $repository->saveDraftPayload(
            $websiteKey,
            'cms_post',
            (string) $post->id,
            'en',
            [
                'title' => 'Source news',
                'slug' => 'source-news',
                'body' => '<p>English content</p>',
            ],
        );
        $repository->transition($translation, TranslationStatus::Ready);

        $locale = $manager->updateLocale($websiteKey, 'en', ['is_published' => true]);
        $this->assertTrue((bool) $locale->is_published);

        $repository->transition($translation->fresh(), TranslationStatus::Published);

        LocalizedRoute::query()->create([
            'website_key' => $websiteKey,
            'locale' => 'en',
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'path' => '/posts/source-news',
            'is_canonical' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $manager->updateLocale($websiteKey, 'en', ['is_published' => false]);

        $this->assertSame(TranslationStatus::Ready, $translation->fresh()->translation_status);
        $this->assertDatabaseHas('localized_routes', [
            'website_key' => $websiteKey,
            'locale' => 'en',
            'path' => '/posts/source-news',
            'is_published' => false,
        ]);
    }

    public function test_extended_content_does_not_block_locale_publish_when_critical_shell_is_ready(): void
    {
        config()->set('localized-content.release.critical_resource_types', []);
        $websiteKey = 'incremental-release-test';
        CmsPost::query()->create([
            'website_key' => $websiteKey,
            'title' => 'Bài viết chưa dịch',
            'slug' => 'bai-viet-chua-dich',
            'status' => 'published',
            'body' => '<p>Nội dung</p>',
            'publish_at' => now(),
        ]);
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale('en', 'English', 'English');
        $manager->provisionWebsite($websiteKey);

        $report = app(LocalizationReleaseReadiness::class)->report($websiteKey, ['en'])['en'];
        $this->assertTrue($report['publishable']);
        $this->assertFalse($report['strict_ready']);
        $this->assertSame(1, $report['extended']['pending']);

        $locale = $manager->updateLocale($websiteKey, 'en', ['is_published' => true]);
        $this->assertTrue((bool) $locale->is_published);
    }

    public function test_target_locale_cannot_be_made_default_until_all_source_content_is_ready(): void
    {
        $websiteKey = 'release-default-gate-test';
        CmsPost::query()->create([
            'website_key' => $websiteKey,
            'title' => 'Source news',
            'slug' => 'source-news',
            'status' => 'published',
            'body' => '<p>Source content</p>',
            'publish_at' => now(),
        ]);
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale('en', 'English', 'English');
        $manager->provisionWebsite($websiteKey);

        try {
            $manager->updateLocale($websiteKey, 'en', ['is_default' => true]);
            $this->fail('Making an incomplete target locale the default should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_default', $exception->errors());
        }

        $this->assertDatabaseHas('website_locales', [
            'website_key' => $websiteKey,
            'locale' => 'vi',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('website_locales', [
            'website_key' => $websiteKey,
            'locale' => 'en',
            'is_default' => false,
            'is_published' => false,
        ]);
    }

    public function test_public_generic_reader_rejects_a_published_target_with_a_stale_source_revision(): void
    {
        $websiteKey = 'stale-public-reader-test';
        $post = CmsPost::query()->create([
            'website_key' => $websiteKey,
            'title' => 'Current source title',
            'slug' => 'current-source',
            'status' => 'published',
            'body' => '<p>Current source content</p>',
            'publish_at' => now(),
        ]);
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale('en', 'English', 'English');
        $manager->provisionWebsite($websiteKey);
        WebsiteLocale::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', 'en')
            ->update(['is_published' => true]);
        app(LocaleContext::class)->flush($websiteKey);

        $source = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('resource_type', 'cms_post')
            ->where('resource_id', (string) $post->id)
            ->where('locale', 'vi')
            ->firstOrFail();
        $this->assertNotEmpty($source->translation_revision);

        ContentTranslation::query()->create([
            'website_key' => $websiteKey,
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'locale' => 'en',
            'slug' => 'stale-news',
            'payload' => [
                'title' => 'Stale translated title',
                'slug' => 'stale-news',
                'body' => '<p>Stale translated content</p>',
            ],
            'translation_status' => TranslationStatus::Published,
            'source_revision' => str_repeat('0', 64),
            'translation_revision' => str_repeat('1', 64),
            'translation_published_at' => now(),
        ]);
        LocalizedRoute::query()->create([
            'website_key' => $websiteKey,
            'locale' => 'en',
            'resource_type' => 'cms_post',
            'resource_id' => (string) $post->id,
            'path' => '/n/stale-news',
            'is_canonical' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $repository = app(LocalizedContentRepository::class);
        $localized = $repository->localize($post, 'cms_post', 'en', $websiteKey);

        $this->assertSame('Current source title', $localized->title);
        $this->assertSame('vi', $localized->resolved_locale);
        $this->assertNull($repository->findPublishedBySlug(
            'cms_post',
            $websiteKey,
            'en',
            'stale-news',
        ));
        $this->assertNull($repository->resolvePublishedBySlug(
            'cms_post',
            $websiteKey,
            'en',
            'stale-news',
        ));
        $this->assertNotContains(
            'en',
            $repository->publicTranslations('cms_post', $post->id, $websiteKey)->pluck('locale')->all(),
        );
    }
}
