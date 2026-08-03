<?php

namespace App\Console\Commands;

use App\Core\Cms\CmsMenuLinkRegistry;
use App\Core\Cms\CmsMenuLocalization;
use App\Enums\TranslationStatus;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\LocalizedRoute;
use App\Models\ThemeTranslation;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizationReleaseReadiness;
use App\Support\Localization\TranslationRevision;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LocalizationAuditCommand extends Command
{
    protected $signature = 'localization:audit
        {--website= : Chỉ kiểm tra một website_key}
        {--json : Xuất báo cáo JSON}
        {--strict : Trả exit code lỗi khi phát hiện dữ liệu không nhất quán}
        {--require-ready : Trả exit code lỗi nếu locale công khai chưa đủ nội dung để phát hành}';

    protected $description = 'Đối chiếu dữ liệu đa ngôn ngữ mới, dữ liệu cũ, URL và điều kiện xuất bản.';

    public function handle(
        LocaleContext $localeContext,
        CmsMenuLocalization $menuLocalization,
        CmsMenuLinkRegistry $menuLinkRegistry,
        LocalizationReleaseReadiness $releaseReadiness,
    ): int {
        $website = trim((string) $this->option('website'));
        $website = $website !== '' ? $website : null;
        $issues = [];
        $modules = [];

        foreach ((array) config('localized-content.resources', []) as $resourceType => $definition) {
            $modelClass = $definition['model'] ?? null;

            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            /** @var Model $model */
            $model = new $modelClass;

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            $sourceQuery = $modelClass::query()->withoutGlobalScopes();

            if ($website !== null && Schema::hasColumn($model->getTable(), 'website_key')) {
                $sourceQuery->where('website_key', $website);
            }

            $sourceIds = $sourceQuery->pluck($model->getKeyName())->map(fn ($id): string => (string) $id);
            $translationQuery = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('resource_type', $resourceType)
                ->where('locale', $localeContext->sourceLocale());

            if ($website !== null) {
                $translationQuery->where('website_key', $website);
            }

            $translatedIds = $translationQuery->pluck('resource_id')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values();
            $validTranslatedIds = $translatedIds->intersect($sourceIds)->values();
            $orphanIds = $translatedIds->diff($sourceIds)->values();
            $missingIds = $sourceIds->diff($validTranslatedIds)->values();
            $modules[$resourceType] = [
                'source_records' => $sourceIds->count(),
                'source_translations' => $validTranslatedIds->count(),
                'orphan_source_translations' => $orphanIds->count(),
                'missing_source_translations' => $missingIds->count(),
            ];

            if ($missingIds->isNotEmpty()) {
                $issues[] = [
                    'type' => 'missing_source_translation',
                    'resource_type' => $resourceType,
                    'resource_ids' => $missingIds->take(25)->all(),
                ];
            }

            if ($orphanIds->isNotEmpty()) {
                $issues[] = [
                    'type' => 'orphan_source_translation',
                    'resource_type' => $resourceType,
                    'resource_ids' => $orphanIds->take(25)->all(),
                ];
            }
        }

        $this->auditPageTranslations($website, $localeContext, $issues);
        $this->auditMenus(
            $website,
            $localeContext,
            $menuLocalization,
            $menuLinkRegistry,
            $issues,
        );
        $this->auditPublishedLocales($website, $localeContext, $issues);
        $this->auditDuplicateSlugs($website, $issues);
        $this->auditLegacyOverrides($website, $issues);
        $this->auditLandingPages($website, $localeContext, $issues);
        $this->auditCanonicalRoutes($website, $issues);
        $readiness = $releaseReadiness->report($website ?: 'website-main');

        if ($this->option('require-ready')) {
            foreach ($readiness as $locale => $item) {
                if ($item['ready']) {
                    continue;
                }

                $issues[] = [
                    'type' => 'locale_not_release_ready',
                    'website_key' => $website,
                    'locale' => $locale,
                    'pending' => $item['pending'],
                    'scopes' => $item['scopes'],
                ];
            }
        }

        $report = [
            'generated_at' => now()->toAtomString(),
            'website_key' => $website,
            'reader' => config('localized-content.rollout.reader'),
            'dual_write' => (bool) config('localized-content.rollout.dual_write'),
            'legacy_fallback' => (bool) config('localized-content.rollout.legacy_fallback'),
            'modules' => $modules,
            'release_readiness' => $readiness,
            'issue_count' => count($issues),
            'issues' => $issues,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->info('Localization audit');
            $this->table(
                ['Module', 'Source', 'Translations', 'Missing'],
                collect($modules)->map(fn (array $item, string $type): array => [
                    $type,
                    $item['source_records'],
                    $item['source_translations'],
                    $item['missing_source_translations'],
                ])->values()->all(),
            );

            if ($readiness !== []) {
                $this->newLine();
                $this->info('Release readiness');
                $this->table(
                    ['Locale', 'Scope', 'Required', 'Ready', 'Pending', 'Coverage'],
                    collect($readiness)->flatMap(
                        fn (array $item, string $locale) => collect($item['scopes'])
                            ->map(fn (array $scope, string $name): array => [
                                $locale,
                                $name,
                                $scope['required'],
                                $scope['ready'],
                                $scope['pending'],
                                $scope['coverage'].'%',
                            ]),
                    )->values()->all(),
                );
            }

            $this->line('Issues: '.count($issues));

            foreach ($issues as $issue) {
                $this->warn((string) json_encode(
                    $issue,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ));
            }
        }

        return ($this->option('strict') || $this->option('require-ready')) && $issues !== []
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array<string, array{
     *     ready: bool,
     *     required: int,
     *     translated: int,
     *     pending: int,
     *     coverage: float,
     *     scopes: array<string, array{required:int,ready:int,pending:int,coverage:float}>
     * }>
     */
    private function releaseReadiness(
        ?string $website,
        LocaleContext $localeContext,
    ): array {
        $websiteKey = $website ?: 'website-main';
        $sourceLocale = $localeContext->sourceLocale();
        $locales = collect($localeContext->publicLocales($websiteKey))
            ->reject(fn (string $locale): bool => $locale === $sourceLocale)
            ->values();

        if ($locales->isEmpty()) {
            return [];
        }

        $sourceContent = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('website_key', $websiteKey)
            ->where('locale', $sourceLocale)
            ->get(['resource_type', 'resource_id', 'translation_revision', 'source_revision'])
            ->mapWithKeys(fn (ContentTranslation $translation): array => [
                $translation->resource_type.'|'.$translation->resource_id => (string) (
                    $translation->translation_revision
                    ?: $translation->source_revision
                ),
            ]);
        $sourcePages = CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('website_key', $websiteKey)
            ->where('locale', $sourceLocale)
            ->get(['cms_page_id', 'translation_revision', 'source_revision'])
            ->mapWithKeys(fn (CmsPageTranslation $translation): array => [
                (string) $translation->cms_page_id => (string) (
                    $translation->translation_revision
                    ?: $translation->source_revision
                ),
            ]);
        $sourceLandingPages = LandingPageData::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('locale', $sourceLocale)
            ->whereHas('landingPage', fn ($query) => $query
                ->where('website_key', $websiteKey))
            ->get(['landing_page_id', 'translation_revision', 'source_revision'])
            ->mapWithKeys(fn (LandingPageData $translation): array => [
                (string) $translation->landing_page_id => (string) (
                    $translation->translation_revision
                    ?: $translation->source_revision
                ),
            ]);
        $sourceLandingBlocks = LandingPageBlockData::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('locale', $sourceLocale)
            ->whereHas('landingPageBlock', fn ($query) => $query
                ->where('is_visible', true)
                ->where('block_type', '!=', 'footer_contact')
                ->whereHas('landingPage', fn ($pageQuery) => $pageQuery
                    ->where('website_key', $websiteKey)))
            ->get([
                'landing_page_block_id',
                'translation_revision',
                'source_revision',
            ])
            ->mapWithKeys(fn (LandingPageBlockData $translation): array => [
                (string) $translation->landing_page_block_id => (string) (
                    $translation->translation_revision
                    ?: $translation->source_revision
                ),
            ]);

        return $locales->mapWithKeys(function (string $locale) use (
            $sourceContent,
            $sourcePages,
            $sourceLandingPages,
            $sourceLandingBlocks,
            $websiteKey,
        ): array {
            $targetContent = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->publishedTranslation()
                ->where('website_key', $websiteKey)
                ->where('locale', $locale)
                ->get(['resource_type', 'resource_id', 'source_revision'])
                ->mapWithKeys(fn (ContentTranslation $translation): array => [
                    $translation->resource_type.'|'.$translation->resource_id => (string) $translation->source_revision,
                ]);
            $targetPages = CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->publishedTranslation()
                ->where('website_key', $websiteKey)
                ->where('locale', $locale)
                ->get(['cms_page_id', 'source_revision'])
                ->mapWithKeys(fn (CmsPageTranslation $translation): array => [
                    (string) $translation->cms_page_id => (string) $translation->source_revision,
                ]);
            $targetLandingPages = LandingPageData::query()
                ->withoutGlobalScopes()
                ->publishedTranslation()
                ->where('locale', $locale)
                ->whereHas('landingPage', fn ($query) => $query
                    ->where('website_key', $websiteKey))
                ->get(['landing_page_id', 'source_revision'])
                ->mapWithKeys(fn (LandingPageData $translation): array => [
                    (string) $translation->landing_page_id => (string) $translation->source_revision,
                ]);
            $targetLandingBlocks = LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->publishedTranslation()
                ->where('locale', $locale)
                ->whereHas('landingPageBlock.landingPage', fn ($query) => $query
                    ->where('website_key', $websiteKey))
                ->get(['landing_page_block_id', 'source_revision'])
                ->mapWithKeys(fn (LandingPageBlockData $translation): array => [
                    (string) $translation->landing_page_block_id => (string) $translation->source_revision,
                ]);

            $scopes = [
                'content' => $this->readinessScope($sourceContent, $targetContent),
                'cms_pages' => $this->readinessScope($sourcePages, $targetPages),
                'landing_pages' => $this->readinessScope(
                    $sourceLandingPages,
                    $targetLandingPages,
                ),
                'landing_blocks' => $this->readinessScope(
                    $sourceLandingBlocks,
                    $targetLandingBlocks,
                ),
            ];
            $required = (int) collect($scopes)->sum('required');
            $translated = (int) collect($scopes)->sum('ready');
            $pending = $required - $translated;

            return [
                $locale => [
                    'ready' => $pending === 0,
                    'required' => $required,
                    'translated' => $translated,
                    'pending' => $pending,
                    'coverage' => $this->coverage($translated, $required),
                    'scopes' => $scopes,
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<string, string>  $source
     * @param  Collection<string, string>  $target
     * @return array{required:int,ready:int,pending:int,coverage:float}
     */
    private function readinessScope(Collection $source, Collection $target): array
    {
        $ready = $source->filter(
            fn (string $revision, string $key): bool => (
                $revision !== ''
                && (string) $target->get($key) === $revision
            ),
        )->count();
        $required = $source->count();

        return [
            'required' => $required,
            'ready' => $ready,
            'pending' => $required - $ready,
            'coverage' => $this->coverage($ready, $required),
        ];
    }

    private function coverage(int $ready, int $required): float
    {
        return $required === 0
            ? 100.0
            : round(($ready / $required) * 100, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditPageTranslations(
        ?string $website,
        LocaleContext $localeContext,
        array &$issues,
    ): void {
        if (! Schema::hasTable('cms_page_translations')) {
            $issues[] = ['type' => 'missing_table', 'table' => 'cms_page_translations'];

            return;
        }

        $pages = CmsPage::query()
            ->withoutGlobalScopes()
            ->when($website, fn ($query) => $query->where('website_key', $website))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id);
        $translated = CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->where('locale', $localeContext->sourceLocale())
            ->when($website, fn ($query) => $query->where('website_key', $website))
            ->pluck('cms_page_id')
            ->map(fn ($id): string => (string) $id);
        $missing = $pages->diff($translated)->values();

        if ($missing->isNotEmpty()) {
            $issues[] = [
                'type' => 'missing_page_source_translation',
                'resource_ids' => $missing->take(25)->all(),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditMenus(
        ?string $website,
        LocaleContext $localeContext,
        CmsMenuLocalization $menuLocalization,
        CmsMenuLinkRegistry $menuLinkRegistry,
        array &$issues,
    ): void {
        if (! Schema::hasTable('cms_menus')) {
            return;
        }

        $sourceLocale = $localeContext->sourceLocale();
        $menus = CmsMenu::query()
            ->withoutGlobalScopes()
            ->when($website, fn ($query) => $query->where('website_key', $website))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
        $currentMenuIds = $menus
            ->unique(fn (CmsMenu $menu): string => (
                strtolower((string) $menu->website_key)
                .'|'.(string) $menu->location
            ))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($menus as $menu) {
            $websiteKey = strtolower((string) (
                $menu->website_key
                ?: 'website-main'
            ));
            $sourceItems = is_array($menu->items) ? $menu->items : [];
            $sourcePayload = ['items' => $sourceItems];
            $sourceRevision = TranslationRevision::fingerprint($sourcePayload);
            $this->auditMenuLinkIdentities(
                $sourceItems,
                $menu,
                $menuLinkRegistry,
                $issues,
            );
            $source = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->where('resource_type', 'cms_menu')
                ->where('resource_id', (string) $menu->id)
                ->where('locale', $sourceLocale)
                ->first();

            if ($source !== null) {
                if (
                    $source->translation_status !== TranslationStatus::Published
                    || (array) $source->payload !== $sourcePayload
                    || (string) $source->source_revision !== $sourceRevision
                    || (string) $source->translation_revision !== $sourceRevision
                ) {
                    $issues[] = [
                        'type' => 'stale_menu_source_translation',
                        'website_key' => $websiteKey,
                        'menu_id' => $menu->id,
                        'locale' => $sourceLocale,
                    ];
                }
            }

            $validKeys = array_keys($this->menuLabelsByKey($sourceItems));
            $targets = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->where('resource_type', 'cms_menu')
                ->where('resource_id', (string) $menu->id)
                ->where('locale', '!=', $sourceLocale)
                ->get();

            foreach ($targets as $target) {
                $payload = (array) $target->payload;
                $isV2 = (int) data_get(
                    $payload,
                    'items.schema_version',
                ) === CmsMenuLocalization::SCHEMA_VERSION
                    && is_array(data_get($payload, 'items.by_key'));

                if (! $isV2) {
                    $issues[] = [
                        'type' => 'legacy_menu_translation_payload',
                        'website_key' => $websiteKey,
                        'menu_id' => $menu->id,
                        'translation_id' => $target->id,
                        'locale' => $target->locale,
                    ];

                    continue;
                }

                $translatedKeys = array_keys((array) data_get(
                    $payload,
                    'items.by_key',
                    [],
                ));
                $unknownKeys = array_values(array_diff(
                    $translatedKeys,
                    $validKeys,
                ));

                if ($unknownKeys !== []) {
                    $issues[] = [
                        'type' => 'orphaned_menu_translation_keys',
                        'website_key' => $websiteKey,
                        'menu_id' => $menu->id,
                        'translation_id' => $target->id,
                        'locale' => $target->locale,
                        'item_keys' => array_slice($unknownKeys, 0, 25),
                    ];
                }

                if (
                    $target->translation_status === TranslationStatus::Published
                    && (string) $target->source_revision !== $sourceRevision
                ) {
                    $issues[] = [
                        'type' => 'menu_translation_source_revision_mismatch',
                        'website_key' => $websiteKey,
                        'menu_id' => $menu->id,
                        'translation_id' => $target->id,
                        'locale' => $target->locale,
                    ];
                }

                if ($target->translation_status !== TranslationStatus::Published) {
                    continue;
                }

                $progress = $menuLocalization->progress(
                    $sourceItems,
                    $payload,
                );

                if (! $progress['complete']) {
                    $issues[] = [
                        'type' => 'published_menu_translation_incomplete',
                        'website_key' => $websiteKey,
                        'menu_id' => $menu->id,
                        'translation_id' => $target->id,
                        'locale' => $target->locale,
                        'translated' => $progress['translated'],
                        'total' => $progress['total'],
                    ];
                }

                if ($this->menuTranslationEqualsSource(
                    $sourceItems,
                    $payload,
                )) {
                    $issues[] = [
                        'type' => 'published_menu_translation_equals_source',
                        'website_key' => $websiteKey,
                        'menu_id' => $menu->id,
                        'translation_id' => $target->id,
                        'locale' => $target->locale,
                    ];
                }
            }

            if (
                in_array((string) $menu->id, $currentMenuIds, true)
                && Schema::hasTable('theme_translations')
            ) {
                $legacyLocales = ThemeTranslation::query()
                    ->withoutGlobalScopes()
                    ->where('website_key', $websiteKey)
                    ->where('theme_key', 'site-content:'.$websiteKey)
                    ->where('group', 'content')
                    ->where('locale', '!=', $sourceLocale)
                    ->where('translation_key', 'like', 'cms_menu.%')
                    ->get(['locale', 'translation_key'])
                    ->filter(fn (ThemeTranslation $translation): bool => (
                        str_starts_with(
                            (string) $translation->translation_key,
                            'cms_menu.'.(string) $menu->location.'.',
                        )
                        || str_starts_with(
                            (string) $translation->translation_key,
                            'cms_menu.'.(string) $menu->id.'.items.',
                        )
                    ))
                    ->pluck('locale')
                    ->unique();

                foreach ($legacyLocales as $legacyLocale) {
                    $migrated = $targets->firstWhere(
                        'locale',
                        (string) $legacyLocale,
                    );

                    if (
                        $migrated === null
                        || (int) data_get(
                            $migrated->payload,
                            'items.schema_version',
                        ) !== CmsMenuLocalization::SCHEMA_VERSION
                    ) {
                        $issues[] = [
                            'type' => 'unmigrated_legacy_menu_override',
                            'website_key' => $websiteKey,
                            'menu_id' => $menu->id,
                            'locale' => $legacyLocale,
                        ];
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, mixed>  $payload
     */
    private function menuTranslationEqualsSource(
        array $sourceItems,
        array $payload,
    ): bool {
        $sourceLabels = $this->menuLabelsByKey($sourceItems);

        return $sourceLabels !== []
            && collect($sourceLabels)->every(
                fn (string $sourceLabel, string $itemKey): bool => (
                    trim($sourceLabel) === trim((string) data_get(
                        $payload,
                        "items.by_key.{$itemKey}.label",
                        '',
                    ))
                ),
            );
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, string>
     */
    private function menuLabelsByKey(array $items): array
    {
        $labels = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemKey = trim((string) ($item['item_key'] ?? ''));

            if ($itemKey !== '') {
                $labels[$itemKey] = trim((string) ($item['label'] ?? ''));
            }

            if (is_array($item['children'] ?? null)) {
                $labels = array_replace(
                    $labels,
                    $this->menuLabelsByKey($item['children']),
                );
            }
        }

        return $labels;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditMenuLinkIdentities(
        array $items,
        CmsMenu $menu,
        CmsMenuLinkRegistry $links,
        array &$issues,
        string $path = 'items',
    ): void {
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemPath = "{$path}.{$index}";
            $linkType = trim((string) ($item['link_type'] ?? ''));
            $expectedResourceType = $links->resourceType($linkType);
            $identity = $links->identity($item);

            if ($expectedResourceType !== null && $identity === null) {
                $issues[] = [
                    'type' => 'menu_resource_identity_missing',
                    'website_key' => $menu->website_key,
                    'menu_id' => $menu->id,
                    'item_path' => $itemPath,
                    'link_type' => $linkType,
                ];
            } elseif (
                $identity !== null
                && (
                    $identity['resource_type'] !== $expectedResourceType
                    || (string) ($item['link_value'] ?? '') !== $identity['resource_id']
                )
            ) {
                $issues[] = [
                    'type' => 'menu_resource_identity_mismatch',
                    'website_key' => $menu->website_key,
                    'menu_id' => $menu->id,
                    'item_path' => $itemPath,
                    'link_type' => $linkType,
                    'resource_type' => $identity['resource_type'],
                    'resource_id' => $identity['resource_id'],
                ];
            }

            if (is_array($item['children'] ?? null)) {
                $this->auditMenuLinkIdentities(
                    $item['children'],
                    $menu,
                    $links,
                    $issues,
                    $itemPath.'.children',
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditPublishedLocales(
        ?string $website,
        LocaleContext $localeContext,
        array &$issues,
    ): void {
        $query = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->when($website, fn ($builder) => $builder->where('website_key', $website));

        foreach ($query->get(['id', 'website_key', 'locale', 'resource_type']) as $translation) {
            if (! $localeContext->isPublic($translation->locale, $translation->website_key)) {
                $issues[] = [
                    'type' => 'published_content_in_private_locale',
                    'translation_id' => $translation->id,
                    'resource_type' => $translation->resource_type,
                    'website_key' => $translation->website_key,
                    'locale' => $translation->locale,
                ];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditDuplicateSlugs(?string $website, array &$issues): void
    {
        $duplicates = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->whereNotNull('slug')
            ->when($website, fn ($query) => $query->where('website_key', $website))
            ->selectRaw('website_key, resource_type, locale, slug, COUNT(*) as aggregate')
            ->groupBy('website_key', 'resource_type', 'locale', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $issues[] = [
                'type' => 'duplicate_localized_slug',
                'website_key' => $duplicate->website_key,
                'resource_type' => $duplicate->resource_type,
                'locale' => $duplicate->locale,
                'slug' => $duplicate->slug,
                'count' => (int) $duplicate->aggregate,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditLegacyOverrides(?string $website, array &$issues): void
    {
        if (! Schema::hasTable('theme_translations')) {
            return;
        }

        $legacy = ThemeTranslation::query()
            ->withoutGlobalScopes()
            ->where('group', 'content')
            ->where('theme_key', 'like', 'site-content:%')
            ->get();

        foreach ($legacy as $translation) {
            $websiteKey = substr($translation->theme_key, strlen('site-content:')) ?: 'website-main';

            if ($website !== null && $websiteKey !== $website) {
                continue;
            }

            if (! preg_match('/^([a-z_]+)\.(\d+)\.(.+)$/', $translation->translation_key, $matches)) {
                continue;
            }

            $new = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->where('resource_type', $matches[1])
                ->where('resource_id', $matches[2])
                ->where('locale', $translation->locale)
                ->first();

            if ($new === null || (string) data_get($new->payload, $matches[3]) !== (string) $translation->value) {
                $issues[] = [
                    'type' => 'legacy_override_mismatch',
                    'legacy_id' => $translation->id,
                    'translation_key' => $translation->translation_key,
                    'locale' => $translation->locale,
                ];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditLandingPages(
        ?string $website,
        LocaleContext $localeContext,
        array &$issues,
    ): void {
        $pages = LandingPage::query()
            ->with(['data', 'blocks.data'])
            ->when($website, fn ($query) => $query->where('website_key', $website))
            ->get();

        foreach ($pages as $page) {
            $source = $page->data->firstWhere('locale', $localeContext->sourceLocale());

            if ($source === null) {
                $issues[] = ['type' => 'missing_landing_source', 'landing_page_id' => $page->id];
            }

            foreach ($page->blocks->where('is_visible', true) as $block) {
                if ($block->data->firstWhere('locale', $localeContext->sourceLocale()) === null) {
                    $issues[] = [
                        'type' => 'missing_landing_block_source',
                        'landing_page_id' => $page->id,
                        'block_id' => $block->id,
                    ];
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditCanonicalRoutes(?string $website, array &$issues): void
    {
        $published = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->whereNotNull('slug')
            ->when($website, fn ($query) => $query->where('website_key', $website))
            ->get();

        foreach ($published as $translation) {
            $hasRoute = LocalizedRoute::query()
                ->withoutGlobalScopes()
                ->where('website_key', $translation->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', $translation->resource_type)
                ->where('resource_id', $translation->resource_id)
                ->where('is_canonical', true)
                ->where('is_published', true)
                ->exists();

            if (! $hasRoute && in_array($translation->resource_type, [
                'cms_post',
                'cms_service',
                'cms_project',
                'catalog_product',
                'catalog_category',
                'cms_category',
                'cms_service_category',
                'cms_project_category',
            ], true)) {
                $issues[] = [
                    'type' => 'missing_canonical_route',
                    'translation_id' => $translation->id,
                    'resource_type' => $translation->resource_type,
                    'locale' => $translation->locale,
                ];
            }
        }

        $pageTranslations = CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->when($website, fn ($query) => $query
                ->where('website_key', $website))
            ->get();

        foreach ($pageTranslations as $translation) {
            $this->auditExpectedCanonicalRoute(
                (string) $translation->website_key,
                (string) $translation->locale,
                'cms_page',
                (string) $translation->cms_page_id,
                '/p/'.trim((string) $translation->slug, '/'),
                (int) $translation->id,
                $issues,
            );
        }

        $landingTranslations = LandingPageData::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->whereHas('landingPage', fn ($query) => $query
                ->when($website, fn ($builder) => $builder
                    ->where('website_key', $website))
                ->where('is_home', false))
            ->with('landingPage:id,website_key')
            ->get();

        foreach ($landingTranslations as $translation) {
            $page = $translation->landingPage;

            if ($page === null) {
                continue;
            }

            $this->auditExpectedCanonicalRoute(
                (string) $page->website_key,
                (string) $translation->locale,
                'landing_page',
                (string) $translation->landing_page_id,
                '/land/'.trim((string) $translation->slug, '/'),
                (int) $translation->id,
                $issues,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function auditExpectedCanonicalRoute(
        string $websiteKey,
        string $locale,
        string $resourceType,
        string $resourceId,
        string $expectedPath,
        int $translationId,
        array &$issues,
    ): void {
        $routes = LocalizedRoute::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $locale)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('is_canonical', true)
            ->get();
        $publishedCanonical = $routes->where('is_published', true);

        if ($publishedCanonical->isEmpty()) {
            $issues[] = [
                'type' => 'missing_canonical_route',
                'translation_id' => $translationId,
                'website_key' => $websiteKey,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'locale' => $locale,
                'expected_path' => $expectedPath,
            ];

            return;
        }

        if ($publishedCanonical->count() > 1) {
            $issues[] = [
                'type' => 'duplicate_canonical_routes',
                'translation_id' => $translationId,
                'website_key' => $websiteKey,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'locale' => $locale,
                'route_ids' => $publishedCanonical->pluck('id')->all(),
            ];
        }

        if (! $publishedCanonical->contains(
            fn (LocalizedRoute $route): bool => $route->path === $expectedPath,
        )) {
            $issues[] = [
                'type' => 'canonical_route_path_mismatch',
                'translation_id' => $translationId,
                'website_key' => $websiteKey,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'locale' => $locale,
                'expected_path' => $expectedPath,
                'actual_paths' => $publishedCanonical->pluck('path')->all(),
            ];
        }
    }
}
