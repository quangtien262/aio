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
use App\Support\Localization\LocalizationReleaseReadiness;
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

    /** @var array{created:int,updated:int,preserved:int,published:int} */
    private array $stats = [
        'created' => 0,
        'updated' => 0,
        'preserved' => 0,
        'published' => 0,
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
            $this->synchronizeSourceRevisions($snapshot);
            $this->markTranslationsReady($snapshot);
            $this->publishTargetLocale();
            $this->publishTranslations($snapshot);
        });

        FrontendLocalization::flushCache();

        $this->command?->info(sprintf(
            'Masami Chinese seed complete: %d created, %d machine translations updated, %d human translations preserved, %d translations published.',
            $this->stats['created'],
            $this->stats['updated'],
            $this->stats['preserved'],
            $this->stats['published'],
        ));
        $this->command?->info('Chinese is public on the storefront. Internal /vi/ links were localized to /zh/.');
    }

    /** @param array<string, mixed> $snapshot */
    private function synchronizeSourceRevisions(array $snapshot): void
    {
        foreach ((array) ($snapshot['content'] ?? []) as $entry) {
            $this->contentTranslation($entry)->forceFill([
                'source_revision' => (string) ($entry['source_revision'] ?? ''),
            ])->save();
        }

        foreach ((array) ($snapshot['cms_pages'] ?? []) as $entry) {
            CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('cms_page_id', (string) ($entry['cms_page_id'] ?? ''))
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail()
                ->forceFill([
                    'source_revision' => (string) ($entry['source_revision'] ?? ''),
                ])->save();
        }

        foreach ((array) ($snapshot['landing_pages'] ?? []) as $entry) {
            LandingPageData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_id', (string) ($entry['landing_page_id'] ?? ''))
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail()
                ->forceFill([
                    'source_revision' => (string) ($entry['source_revision'] ?? ''),
                ])->save();
        }

        foreach ((array) ($snapshot['landing_blocks'] ?? []) as $entry) {
            LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_block_id', (string) ($entry['landing_page_block_id'] ?? ''))
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail()
                ->forceFill([
                    'source_revision' => (string) ($entry['source_revision'] ?? ''),
                ])->save();
        }
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

        if (! $locale->is_enabled_for_editing) {
            $manager->updateLocale(self::WEBSITE_KEY, self::TARGET_LOCALE, [
                'is_enabled_for_editing' => true,
            ]);
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function markTranslationsReady(array $snapshot): void
    {
        $repository = app(LocalizedContentRepository::class);
        $pageLocalization = app(CmsPageLocalization::class);
        $landingLocalization = app(LandingPageLocalization::class);

        foreach ((array) ($snapshot['content'] ?? []) as $entry) {
            $translation = $this->contentTranslation($entry);
            $this->transitionToReady(
                $translation,
                fn () => $repository->transition($translation, TranslationStatus::Ready),
            );
        }

        foreach ((array) ($snapshot['cms_pages'] ?? []) as $entry) {
            $page = CmsPage::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->findOrFail((string) ($entry['cms_page_id'] ?? ''));
            $translation = CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('cms_page_id', $page->id)
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail();
            $this->transitionToReady(
                $translation,
                fn () => $pageLocalization->transition($page, self::TARGET_LOCALE, TranslationStatus::Ready),
            );
        }

        foreach ((array) ($snapshot['landing_blocks'] ?? []) as $entry) {
            $block = $this->landingBlock($entry);
            $translation = LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_block_id', $block->id)
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail();
            $this->transitionToReady(
                $translation,
                fn () => $landingLocalization->transitionBlock(
                    $block,
                    self::TARGET_LOCALE,
                    TranslationStatus::Ready,
                ),
            );
        }

        foreach ((array) ($snapshot['landing_pages'] ?? []) as $entry) {
            $page = $this->landingPage($entry);
            $translation = LandingPageData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_id', $page->id)
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail();
            $this->transitionToReady(
                $translation,
                fn () => $landingLocalization->transitionPage(
                    $page,
                    self::TARGET_LOCALE,
                    TranslationStatus::Ready,
                ),
            );
        }
    }

    private function publishTargetLocale(): void
    {
        $locale = WebsiteLocale::query()
            ->withoutGlobalScopes()
            ->where('website_key', self::WEBSITE_KEY)
            ->where('locale', self::TARGET_LOCALE)
            ->firstOrFail();

        if (! $locale->is_published) {
            $readiness = app(LocalizationReleaseReadiness::class)
                ->report(self::WEBSITE_KEY, [self::TARGET_LOCALE])[self::TARGET_LOCALE] ?? [];
            $this->command?->line('Chinese release readiness: '.json_encode([
                'critical' => $readiness['critical'] ?? null,
                'extended' => $readiness['extended'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            app(WebsiteLocaleManager::class)->updateLocale(
                self::WEBSITE_KEY,
                self::TARGET_LOCALE,
                ['is_published' => true],
            );
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function publishTranslations(array $snapshot): void
    {
        $repository = app(LocalizedContentRepository::class);
        $pageLocalization = app(CmsPageLocalization::class);
        $landingLocalization = app(LandingPageLocalization::class);

        foreach ((array) ($snapshot['content'] ?? []) as $entry) {
            $translation = $this->contentTranslation($entry);
            $this->transitionToPublished(
                $translation,
                fn () => $repository->transition($translation, TranslationStatus::Published),
            );
        }

        foreach ((array) ($snapshot['cms_pages'] ?? []) as $entry) {
            $page = CmsPage::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->findOrFail((string) ($entry['cms_page_id'] ?? ''));
            $translation = CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY)
                ->where('cms_page_id', $page->id)
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail();
            $this->transitionToPublished(
                $translation,
                fn () => $pageLocalization->transition(
                    $page,
                    self::TARGET_LOCALE,
                    TranslationStatus::Published,
                ),
            );
        }

        // Landing pages require every visible block to be public first.
        foreach ((array) ($snapshot['landing_blocks'] ?? []) as $entry) {
            $block = $this->landingBlock($entry);
            $translation = LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_block_id', $block->id)
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail();
            $this->transitionToPublished(
                $translation,
                fn () => $landingLocalization->transitionBlock(
                    $block,
                    self::TARGET_LOCALE,
                    TranslationStatus::Published,
                ),
            );
        }

        foreach ((array) ($snapshot['landing_pages'] ?? []) as $entry) {
            $page = $this->landingPage($entry);
            $translation = LandingPageData::query()
                ->withoutGlobalScopes()
                ->where('landing_page_id', $page->id)
                ->where('locale', self::TARGET_LOCALE)
                ->firstOrFail();
            $this->transitionToPublished(
                $translation,
                fn () => $landingLocalization->transitionPage(
                    $page,
                    self::TARGET_LOCALE,
                    TranslationStatus::Published,
                ),
            );
        }
    }

    /** @param array<string, mixed> $entry */
    private function contentTranslation(array $entry): ContentTranslation
    {
        return ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', self::WEBSITE_KEY)
            ->where('resource_type', (string) ($entry['resource_type'] ?? ''))
            ->where('resource_id', (string) ($entry['resource_id'] ?? ''))
            ->where('locale', self::TARGET_LOCALE)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $entry */
    private function landingPage(array $entry): LandingPage
    {
        return LandingPage::query()
            ->withoutGlobalScopes()
            ->where('website_key', self::WEBSITE_KEY)
            ->findOrFail((string) ($entry['landing_page_id'] ?? ''));
    }

    /** @param array<string, mixed> $entry */
    private function landingBlock(array $entry): LandingPageBlock
    {
        return LandingPageBlock::query()
            ->withoutGlobalScopes()
            ->whereHas('landingPage', fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('website_key', self::WEBSITE_KEY))
            ->findOrFail((string) ($entry['landing_page_block_id'] ?? ''));
    }

    private function transitionToReady(object $translation, callable $transition): void
    {
        if (in_array($this->statusOf($translation), [
            TranslationStatus::Ready,
            TranslationStatus::Published,
        ], true)) {
            return;
        }

        $transition();
    }

    private function transitionToPublished(object $translation, callable $transition): void
    {
        if ($this->statusOf($translation) === TranslationStatus::Published) {
            return;
        }

        $transition();
        $this->stats['published']++;
    }

    private function statusOf(object $translation): TranslationStatus
    {
        $status = $translation->translation_status;

        return $status instanceof TranslationStatus
            ? $status
            : TranslationStatus::from((string) $status);
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
