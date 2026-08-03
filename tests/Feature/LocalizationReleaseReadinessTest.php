<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\CmsPost;
use App\Models\ContentTranslation;
use App\Models\LocalizedRoute;
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
}
