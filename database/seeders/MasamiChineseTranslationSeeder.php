<?php

namespace Database\Seeders;

use App\Enums\TranslationStatus;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\WebsiteLocale;
use App\Support\FrontendLocalization;
use App\Support\Localization\CmsPageLocalization;
use App\Support\Localization\LandingPageLocalization;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MasamiChineseTranslationSeeder extends Seeder
{
    private const WEBSITE_KEY = 'website-main';

    private const SOURCE_LOCALE = 'vi';

    private const TARGET_LOCALE = 'zh';

    /** @var array{created:int,updated:int,preserved:int} */
    private array $stats = [
        'created' => 0,
        'updated' => 0,
        'preserved' => 0,
    ];

    public function run(): void
    {
        $snapshot = $this->snapshot();
        $this->assertSnapshotMetadata($snapshot);
        $this->prepareTargetLocale();

        DB::transaction(function () use ($snapshot): void {
            $this->seedGenericContent((array) ($snapshot['content'] ?? []));
            $this->seedCmsPages((array) ($snapshot['cms_pages'] ?? []));
            $this->seedLandingPages((array) ($snapshot['landing_pages'] ?? []));
            $this->seedLandingBlocks((array) ($snapshot['landing_blocks'] ?? []));
        });

        FrontendLocalization::flushCache();

        $this->command?->info(sprintf(
            'Masami Chinese seed complete: %d created, %d machine drafts updated, %d human translations preserved.',
            $this->stats['created'],
            $this->stats['updated'],
            $this->stats['preserved'],
        ));
        $this->command?->warn(
            'Chinese remains private. Machine translations are stored as machine_draft and must be reviewed before Ready/Publish.',
        );
    }

    /** @param list<array<string, mixed>> $entries */
    private function seedGenericContent(array $entries): void
    {
        $repository = app(LocalizedContentRepository::class);

        foreach ($entries as $entry) {
            $resourceType = (string) ($entry['resource_type'] ?? '');
            $resourceId = (string) ($entry['resource_id'] ?? '');
            $source = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->where('locale', self::SOURCE_LOCALE)
                ->firstOrFail();

            $this->assertSourceRevision(
                "{$resourceType}#{$resourceId}",
                (string) ($entry['source_revision'] ?? ''),
                (string) $source->translation_revision,
            );

            $existing = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->where('locale', self::TARGET_LOCALE)
                ->first();

            if ($this->preserveHumanTranslation($existing)) {
                continue;
            }

            $wasExisting = $existing !== null;
            $repository->saveDraftPayload(
                self::WEBSITE_KEY,
                $resourceType,
                $resourceId,
                self::TARGET_LOCALE,
                (array) ($entry['payload'] ?? []),
                true,
                true,
            );
            $this->increment($wasExisting);
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function seedCmsPages(array $entries): void
    {
        $localization = app(CmsPageLocalization::class);

        foreach ($entries as $entry) {
            $pageId = (string) ($entry['cms_page_id'] ?? '');
            $page = CmsPage::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->findOrFail($pageId);
            $source = CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('cms_page_id', $pageId)
                ->where('locale', self::SOURCE_LOCALE)
                ->firstOrFail();

            $this->assertSourceRevision(
                "cms_page#{$pageId}",
                (string) ($entry['source_revision'] ?? ''),
                (string) $source->translation_revision,
            );

            $existing = CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('cms_page_id', $pageId)
                ->where('locale', self::TARGET_LOCALE)
                ->first();

            if ($this->preserveHumanTranslation($existing)) {
                continue;
            }

            $wasExisting = $existing !== null;
            $payload = (array) ($entry['payload'] ?? []);
            $payload['slug'] = '';
            $localization->saveDraft($page, self::TARGET_LOCALE, $payload, true);
            $this->increment($wasExisting);
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function seedLandingPages(array $entries): void
    {
        $localization = app(LandingPageLocalization::class);

        foreach ($entries as $entry) {
            $pageId = (string) ($entry['landing_page_id'] ?? '');
            $page = LandingPage::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->findOrFail($pageId);
            $source = LandingPageData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_id', $pageId)
                ->where('locale', self::SOURCE_LOCALE)
                ->firstOrFail();

            $this->assertSourceRevision(
                "landing_page#{$pageId}",
                (string) ($entry['source_revision'] ?? ''),
                (string) $source->translation_revision,
            );

            $existing = LandingPageData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_id', $pageId)
                ->where('locale', self::TARGET_LOCALE)
                ->first();

            if ($this->preserveHumanTranslation($existing)) {
                continue;
            }

            $wasExisting = $existing !== null;
            $payload = (array) ($entry['payload'] ?? []);
            $payload['slug'] = $page->is_home ? 'home' : '';
            $localization->savePageDraft($page, self::TARGET_LOCALE, $payload, true);
            $this->increment($wasExisting);
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function seedLandingBlocks(array $entries): void
    {
        $localization = app(LandingPageLocalization::class);

        foreach ($entries as $entry) {
            $blockId = (string) ($entry['landing_page_block_id'] ?? '');
            $block = LandingPageBlock::query()
                ->withoutGlobalScopes()
                ->whereHas('landingPage', fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where('website_key', self::WEBSITE_KEY))
                ->findOrFail($blockId);
            $source = LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_block_id', $blockId)
                ->where('locale', self::SOURCE_LOCALE)
                ->firstOrFail();

            $this->assertSourceRevision(
                "landing_block#{$blockId}",
                (string) ($entry['source_revision'] ?? ''),
                (string) $source->translation_revision,
            );

            $existing = LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_block_id', $blockId)
                ->where('locale', self::TARGET_LOCALE)
                ->first();

            if ($this->preserveHumanTranslation($existing)) {
                continue;
            }

            $wasExisting = $existing !== null;
            $localization->saveBlockDraft(
                $block,
                self::TARGET_LOCALE,
                (array) ($entry['payload'] ?? []),
                true,
            );
            $this->increment($wasExisting);
        }
    }

    private function prepareTargetLocale(): void
    {
        $manager = app(WebsiteLocaleManager::class);
        $manager->ensureSystemLocale(self::TARGET_LOCALE, 'Chinese', '中文');
        $manager->provisionWebsite(self::WEBSITE_KEY);

        $locale = WebsiteLocale::query()
            ->withoutGlobalScopes()
            ->where('website_key', self::WEBSITE_KEY)
            ->where('locale', self::TARGET_LOCALE)
            ->first();

        if ($locale === null) {
            $manager->addLocale(self::WEBSITE_KEY, self::TARGET_LOCALE, [
                'is_enabled_for_editing' => true,
                'is_published' => false,
            ]);

            return;
        }

        if ($locale->is_published) {
            throw new RuntimeException(
                'Refusing to replace Chinese content while the locale is public. Unpublish zh before running this seeder.',
            );
        }

        if (! $locale->is_enabled_for_editing) {
            $manager->updateLocale(self::WEBSITE_KEY, self::TARGET_LOCALE, [
                'is_enabled_for_editing' => true,
            ]);
        }
    }

    private function preserveHumanTranslation(?object $translation): bool
    {
        if ($translation === null || (bool) ($translation->is_machine_translated ?? false)) {
            return false;
        }

        $status = $translation->translation_status;
        $status = $status instanceof TranslationStatus
            ? $status
            : TranslationStatus::tryFrom((string) $status);

        if (in_array($status, [TranslationStatus::Missing, TranslationStatus::NeedsTranslation], true)) {
            return false;
        }

        $this->stats['preserved']++;

        return true;
    }

    private function assertSourceRevision(string $identity, string $expected, string $actual): void
    {
        if ($expected !== '' && hash_equals($expected, $actual)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Masami Chinese snapshot is stale for %s. Regenerate it from the current Vietnamese source before seeding.',
            $identity,
        ));
    }

    private function increment(bool $wasExisting): void
    {
        $this->stats[$wasExisting ? 'updated' : 'created']++;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $path = database_path('seeders/data/masami-zh.json');

        if (! is_file($path)) {
            throw new RuntimeException('Missing Masami Chinese seed snapshot: '.$path);
        }

        return json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function assertSnapshotMetadata(array $snapshot): void
    {
        $metadata = (array) ($snapshot['metadata'] ?? []);

        if (
            ($metadata['website_key'] ?? null) !== self::WEBSITE_KEY
            || ($metadata['source_locale'] ?? null) !== self::SOURCE_LOCALE
            || ($metadata['target_locale'] ?? null) !== self::TARGET_LOCALE
        ) {
            throw new RuntimeException('Masami Chinese seed snapshot metadata is invalid.');
        }
    }
}
