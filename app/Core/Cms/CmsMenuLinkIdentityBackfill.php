<?php

namespace App\Core\Cms;

use App\Enums\TranslationStatus;
use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Support\FrontendLocalization;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\TranslationRevision;
use Illuminate\Support\Facades\Schema;

final class CmsMenuLinkIdentityBackfill
{
    public function __construct(
        private readonly CmsMenuLinkRegistry $links,
        private readonly CmsMenuLinkTargetResolver $targets,
        private readonly LocaleContext $localeContext,
    ) {}

    /**
     * @return array{menus_scanned:int,menus_updated:int,items_identified:int}
     */
    public function run(
        ?string $websiteKey = null,
        bool $dryRun = false,
    ): array {
        $report = [
            'menus_scanned' => 0,
            'menus_updated' => 0,
            'items_identified' => 0,
        ];

        if (! Schema::hasTable('cms_menus')) {
            return $report;
        }

        CmsMenu::query()
            ->withoutGlobalScopes()
            ->when($websiteKey, fn ($query) => $query
                ->where('website_key', $websiteKey))
            ->orderBy('id')
            ->get()
            ->each(function (CmsMenu $menu) use (&$report, $dryRun): void {
                $report['menus_scanned']++;
                $sourceItems = is_array($menu->items) ? $menu->items : [];
                $targetStates = $this->targetTranslationStates($menu);
                $identified = 0;
                $normalized = $this->normalizeItems(
                    $sourceItems,
                    (string) ($menu->website_key ?: 'website-main'),
                    $this->localeContext->sourceLocale(),
                    $identified,
                );
                $normalized = $this->links->normalize($normalized);
                $report['items_identified'] += $identified;

                if ($normalized === $sourceItems) {
                    if (! $dryRun) {
                        $this->synchronizeTranslationRevisions(
                            $menu,
                            $targetStates,
                        );
                    }

                    return;
                }

                $report['menus_updated']++;

                if ($dryRun) {
                    return;
                }

                $menu->forceFill(['items' => $normalized])->save();
                $this->synchronizeTranslationRevisions(
                    $menu->fresh(),
                    $targetStates,
                );
            });

        return $report;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function normalizeItems(
        array $items,
        string $websiteKey,
        string $sourceLocale,
        int &$identified,
    ): array {
        return collect($items)
            ->values()
            ->map(function (mixed $item) use (
                $websiteKey,
                $sourceLocale,
                &$identified,
            ): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $url = trim((string) ($item['url'] ?? ''));
                $resourcePath = $this->resourcePath($url, $sourceLocale);
                $hadIdentity = $this->links->identity($item) !== null;

                if ($resourcePath !== null) {
                    $specialType = $this->targets->specialLinkType($resourcePath);

                    if ($specialType !== null) {
                        $item['link_type'] = $specialType;
                        $item['link_value'] = null;
                        $item['resource_type'] = null;
                        $item['resource_id'] = null;
                    } else {
                        $identity = $this->targets->identity(
                            $item,
                            $websiteKey,
                            $sourceLocale,
                            $resourcePath,
                        );

                        if ($identity !== null) {
                            $item['resource_type'] = $identity['resource_type'];
                            $item['resource_id'] = $identity['resource_id'];
                            $item['link_type'] = $this->links->linkType(
                                $identity['resource_type'],
                            );
                            $item['link_value'] = $identity['resource_id'];

                            if (! $hadIdentity) {
                                $identified++;
                            }
                        }
                    }
                }

                if (is_array($item['children'] ?? null)) {
                    $item['children'] = $this->normalizeItems(
                        $item['children'],
                        $websiteKey,
                        $sourceLocale,
                        $identified,
                    );
                }

                return $item;
            })
            ->all();
    }

    private function resourcePath(string $url, string $sourceLocale): ?string
    {
        if (
            $url === ''
            || $url === '#'
            || str_starts_with($url, '#')
            || preg_match('/^(mailto|tel|javascript):/i', $url)
        ) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host !== '' && $host !== $appHost) {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', trim((string) ($parts['path'] ?? '/'), '/')),
            fn (string $segment): bool => $segment !== '',
        ));

        if (in_array($segments[0] ?? '', FrontendLocalization::knownLocaleCodes(), true)) {
            array_shift($segments);
        }

        $path = '/'.implode('/', $segments);

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function targetTranslationStates(CmsMenu $menu): array
    {
        if (! Schema::hasTable('content_translations')) {
            return [];
        }

        return ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $menu->website_key ?: 'website-main')
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', '!=', $this->localeContext->sourceLocale())
            ->get()
            ->mapWithKeys(fn (ContentTranslation $translation): array => [
                $translation->locale => [
                    'translation_status' => $translation->translation_status,
                    'source_revision' => $translation->source_revision,
                    'translation_meta' => (array) $translation->translation_meta,
                    'translated_at' => $translation->translated_at,
                    'reviewed_at' => $translation->reviewed_at,
                    'translation_published_at' => $translation->translation_published_at,
                ],
            ])
            ->all();
    }

    /**
     * Identity metadata does not change any translatable label. Preserve the
     * workflow state that existed before the structural Menu write.
     *
     * @param  array<string, array<string, mixed>>  $targetStates
     */
    private function synchronizeTranslationRevisions(
        CmsMenu $menu,
        array $targetStates,
    ): void {
        if (! Schema::hasTable('content_translations')) {
            return;
        }

        $websiteKey = (string) ($menu->website_key ?: 'website-main');
        $sourceLocale = $this->localeContext->sourceLocale();
        $sourcePayload = ['items' => is_array($menu->items) ? $menu->items : []];
        $sourceRevision = TranslationRevision::fingerprint($sourcePayload);
        $source = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'website_key' => $websiteKey,
                'resource_type' => 'cms_menu',
                'resource_id' => (string) $menu->id,
                'locale' => $sourceLocale,
            ]);
        $source->forceFill([
            'slug' => null,
            'payload' => $sourcePayload,
            'translation_status' => TranslationStatus::Published,
            'source_revision' => $sourceRevision,
            'translation_revision' => $sourceRevision,
            'is_machine_translated' => false,
            'translation_meta' => array_replace(
                (array) $source->translation_meta,
                ['menu_link_identity_version' => 1],
            ),
            'translated_at' => $source->translated_at ?? $menu->updated_at ?? now(),
            'reviewed_at' => $source->reviewed_at ?? now(),
            'translation_published_at' => $source->translation_published_at ?? now(),
        ])->save();

        $targets = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', (string) $menu->id)
            ->where('locale', '!=', $sourceLocale)
            ->get();

        foreach ($targets as $target) {
            $state = $targetStates[$target->locale] ?? null;
            $status = $state['translation_status'] ?? $target->translation_status;
            $metadata = array_replace(
                (array) ($state['translation_meta'] ?? $target->translation_meta),
                ['menu_link_identity_version' => 1],
            );
            $stateStatus = $state['translation_status'] ?? $target->translation_status;
            $stateSourceRevision = (string) (
                $state['source_revision']
                ?? $target->source_revision
            );
            $statePublishedAt = $state['translation_published_at']
                ?? $target->translation_published_at;
            $stateMetadata = (array) (
                $state['translation_meta']
                ?? $target->translation_meta
            );
            $wasAccidentallyOutdated = (
                $stateStatus === TranslationStatus::Outdated
                || $stateStatus === TranslationStatus::Outdated->value
            )
                && $stateSourceRevision === $sourceRevision
                && $statePublishedAt !== null
                && ! array_key_exists(
                    'menu_link_identity_version',
                    $stateMetadata,
                );

            if ($wasAccidentallyOutdated) {
                $status = TranslationStatus::Published;
            }

            $isOutdated = $status === TranslationStatus::Outdated
                || $status === TranslationStatus::Outdated->value;
            $target->forceFill([
                'translation_status' => $status,
                'source_revision' => $isOutdated
                    ? ($state['source_revision'] ?? $target->source_revision)
                    : $sourceRevision,
                'translation_meta' => $metadata,
                'translated_at' => $state['translated_at'] ?? $target->translated_at,
                'reviewed_at' => $state['reviewed_at'] ?? $target->reviewed_at,
                'translation_published_at' => $state['translation_published_at']
                    ?? $target->translation_published_at,
            ])->save();
        }
    }
}
