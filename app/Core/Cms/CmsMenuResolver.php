<?php

namespace App\Core\Cms;

use App\Models\CmsMenu;
use App\Models\ThemeTranslation;
use App\Support\FrontendLocalization;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizationRollout;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\LocalizedRouteRegistry;
use App\Support\SiteContext;
use Illuminate\Support\Facades\Schema;

final class CmsMenuResolver
{
    /**
     * @var array<string, array<string, array<int, mixed>>>
     */
    private array $resolved = [];

    public function __construct(
        private readonly LocalizedContentRepository $localizedContent,
        private readonly LocaleContext $localeContext,
        private readonly LocalizedRouteRegistry $routeRegistry,
        private readonly LocalizationRollout $rollout,
        private readonly SiteContext $siteContext,
        private readonly CmsMenuLinkRegistry $linkRegistry,
        private readonly CmsMenuLinkTargetResolver $linkTargets,
    ) {}

    /**
     * Resolve all menu locations for the public website.
     *
     * The canonical tree, targets and ordering always come from CmsMenu. Only
     * labels may come from translation; internal URLs are derived for locale.
     *
     * @return array<string, array<int, mixed>>
     */
    public function all(
        ?string $websiteKey = null,
        ?string $locale = null,
        ?string $themeKey = null,
    ): array {
        $websiteKey = $this->siteContext->normalizeWebsiteKey(
            $websiteKey ?? $this->siteContext->websiteKey(),
        );
        $locale = $this->localeContext->resolvePublic(
            $locale ?? app()->getLocale(),
            $websiteKey,
        );
        $themeKey = strtoupper(trim((string) (
            $themeKey
            ?? (
                $websiteKey === $this->siteContext->websiteKey()
                    ? $this->siteContext->themeKey()
                    : null
            )
        )));
        $usesNewReader = $this->rollout->usesNewReader(
            'cms_menu',
            $websiteKey,
            $themeKey !== '' ? $themeKey : null,
        );
        $cacheKey = implode('|', [
            $websiteKey,
            $locale,
            $themeKey,
            $usesNewReader ? 'new' : 'legacy',
            $this->rollout->legacyFallbackEnabled() ? 'fallback' : 'strict',
        ]);

        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }

        if (! Schema::hasTable('cms_menus')) {
            return $this->resolved[$cacheKey] = [];
        }

        $legacyLabels = $this->legacyLabels(
            $websiteKey,
            $locale,
            $usesNewReader,
        );

        return $this->resolved[$cacheKey] = CmsMenu::query()
            ->forWebsite($websiteKey)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('location')
            ->mapWithKeys(function (CmsMenu $menu) use (
                $websiteKey,
                $locale,
                $themeKey,
                $legacyLabels,
            ): array {
                /** @var CmsMenu $localized */
                $localized = $this->localizedContent->localize(
                    $menu,
                    'cms_menu',
                    $locale,
                    $websiteKey,
                    true,
                    $themeKey !== '' ? $themeKey : null,
                );
                $items = is_array($localized->items) ? $localized->items : [];
                $resolvedLocale = (string) ($localized->getAttribute('resolved_locale') ?? '');

                // A published v2 translation is authoritative. Positional
                // values are only a rollout fallback when v2 is unavailable.
                if ($resolvedLocale !== $locale && $legacyLabels !== []) {
                    $items = $this->applyLegacyLabels(
                        $items,
                        'cms_menu.'.(string) $menu->location,
                        $legacyLabels,
                    );
                }

                $items = $this->localizeUrls($items, $websiteKey, $locale);

                return [(string) $menu->location => $items];
            })
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    public function items(
        string $location,
        ?string $websiteKey = null,
        ?string $locale = null,
        ?string $themeKey = null,
    ): array {
        return $this->all(
            $websiteKey,
            $locale,
            $themeKey,
        )[$location] ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function legacyLabels(
        string $websiteKey,
        string $locale,
        bool $usesNewReader,
    ): array {
        if (
            (
                $usesNewReader
                && ! $this->rollout->legacyFallbackEnabled()
            )
            || $locale === $this->localeContext->sourceLocale()
            || ! Schema::hasTable('theme_translations')
        ) {
            return [];
        }

        return ThemeTranslation::query()
            ->withoutGlobalScope('current_website')
            ->where('theme_key', 'site-content:'.strtolower($websiteKey))
            ->where('locale', $locale)
            ->where('group', 'content')
            ->where('translation_key', 'like', 'cms_menu.%')
            ->publishedTranslation()
            ->pluck('value', 'translation_key')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, string>  $legacyLabels
     * @return array<int, mixed>
     */
    private function applyLegacyLabels(
        array $items,
        string $baseKey,
        array $legacyLabels,
    ): array {
        return collect($items)
            ->values()
            ->map(function (mixed $item, int $index) use ($baseKey, $legacyLabels): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $itemKey = $baseKey.'.'.$index;
                $legacyLabel = trim((string) ($legacyLabels[$itemKey.'.label'] ?? ''));

                if ($legacyLabel !== '') {
                    $item['label'] = $legacyLabel;
                }

                if (is_array($item['children'] ?? null)) {
                    $item['children'] = $this->applyLegacyLabels(
                        $item['children'],
                        $itemKey.'.children',
                        $legacyLabels,
                    );
                }

                return $item;
            })
            ->all();
    }

    /**
     * Localize internal menu targets at read time while keeping the source
     * menu structure immutable. Anchors and external links remain untouched.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function localizeUrls(
        array $items,
        string $websiteKey,
        string $locale,
    ): array {
        return collect($items)
            ->values()
            ->map(function (mixed $item) use ($websiteKey, $locale): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                if (array_key_exists('url', $item)) {
                    $item['url'] = $this->localizedUrl(
                        $item,
                        $websiteKey,
                        $locale,
                    );
                }

                if (is_array($item['children'] ?? null)) {
                    $item['children'] = $this->localizeUrls(
                        $item['children'],
                        $websiteKey,
                        $locale,
                    );
                }

                return $item;
            })
            ->all();
    }

    private function localizedUrl(
        array $item,
        string $websiteKey,
        string $locale,
    ): string {
        $url = (string) ($item['url'] ?? '');
        $url = trim($url);

        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return '#';
        }

        if (
            $url === ''
            || $url === '#'
            || str_starts_with($url, '#')
            || preg_match('/^(mailto|tel):/i', $url)
        ) {
            return $url !== '' ? $url : '#';
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return $url;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $sameOriginHosts = collect([
            strtolower((string) request()->getHost()),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
        ])->filter()->unique();

        if ($host !== '' && ! $sameOriginHosts->contains($host)) {
            return $url;
        }

        $absolute = $host !== '';
        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');
        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?'.$parts['query']
            : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== ''
            ? '#'.$parts['fragment']
            : '';
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn (string $segment): bool => $segment !== '',
        ));
        $sourceLocale = in_array(
            $segments[0] ?? '',
            FrontendLocalization::knownLocaleCodes(),
            true,
        ) ? (string) array_shift($segments) : $this->localeContext->sourceLocale();
        $resourcePath = '/'.implode('/', $segments);
        $resourcePath = $resourcePath === '/' ? '/' : rtrim($resourcePath, '/');
        $specialType = in_array(
            (string) ($item['link_type'] ?? ''),
            $this->linkRegistry->specialLinkTypes(),
            true,
        )
            ? (string) $item['link_type']
            : $this->linkTargets->specialLinkType($resourcePath);

        if ($specialType !== null) {
            return $this->specialUrl(
                $specialType,
                $locale,
                $absolute,
            ).$query.$fragment;
        }

        $identity = $this->linkTargets->identity(
            $item,
            $websiteKey,
            $sourceLocale,
            $resourcePath,
        );

        if ($identity !== null) {
            return $this->canonicalUrlOrResourceIndex(
                $identity['resource_type'],
                $identity['resource_id'],
                $websiteKey,
                $locale,
                $absolute,
                $query,
                $fragment,
            );
        }

        $sourceRoute = $this->routeRegistry->resolvePublic(
            $sourceLocale,
            $resourcePath,
            $websiteKey,
        );

        if ($sourceRoute !== null) {
            $canonical = $this->routeRegistry->canonicalPaths(
                (string) $sourceRoute->resource_type,
                (string) $sourceRoute->resource_id,
                [$locale],
                $websiteKey,
            )[$locale] ?? null;

            if (is_string($canonical) && $canonical !== '') {
                return FrontendRouteUrl::localized(
                    $canonical,
                    $locale,
                    $absolute,
                ).$query.$fragment;
            }

            return FrontendRouteUrl::home($locale, $absolute).$query.$fragment;
        }

        if ($this->looksLikeLocalizedResourcePath($resourcePath)) {
            return FrontendRouteUrl::home($locale, $absolute).$query.$fragment;
        }

        return FrontendRouteUrl::localized(
            $path,
            $locale,
            $absolute,
        ).$query.$fragment;
    }

    private function canonicalUrlOrResourceIndex(
        string $resourceType,
        string $resourceId,
        string $websiteKey,
        string $locale,
        bool $absolute,
        string $query,
        string $fragment,
    ): string {
        $canonical = $this->routeRegistry->canonicalPaths(
            $resourceType,
            $resourceId,
            [$locale],
            $websiteKey,
        )[$locale] ?? null;

        if (is_string($canonical) && $canonical !== '') {
            return FrontendRouteUrl::localized(
                $canonical,
                $locale,
                $absolute,
            ).$query.$fragment;
        }

        $fallbackLinkType = match ($resourceType) {
            'catalog_category', 'catalog_product' => 'catalog-index',
            'cms_category', 'cms_post' => 'post-index',
            'cms_service_category', 'cms_service' => 'service-index',
            'cms_project_category', 'cms_project' => 'project-index',
            default => 'home',
        };

        return $this->specialUrl(
            $fallbackLinkType,
            $locale,
            $absolute,
        ).$query.$fragment;
    }

    private function specialUrl(
        string $linkType,
        string $locale,
        bool $absolute,
    ): string {
        return match ($linkType) {
            'home' => FrontendRouteUrl::home($locale, $absolute),
            'contact' => FrontendRouteUrl::contact($locale, $absolute),
            'catalog-index' => FrontendRouteUrl::localized(
                FrontendRouteUrl::catalogSearchPath(),
                $locale,
                $absolute,
            ),
            'post-index' => FrontendRouteUrl::localized(
                FrontendRouteUrl::blogPath(),
                $locale,
                $absolute,
            ),
            'service-index' => FrontendRouteUrl::localized(
                FrontendRouteUrl::servicesPath(),
                $locale,
                $absolute,
            ),
            'project-index' => FrontendRouteUrl::localized(
                FrontendRouteUrl::projectsPath(),
                $locale,
                $absolute,
            ),
            'real-estate-index' => FrontendRouteUrl::localized(
                FrontendRouteUrl::realEstatePath(),
                $locale,
                $absolute,
            ),
            default => FrontendRouteUrl::home($locale, $absolute),
        };
    }

    private function looksLikeLocalizedResourcePath(string $path): bool
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn (string $segment): bool => $segment !== '',
        ));
        $first = $segments[0] ?? '';

        if (
            count($segments) >= 2
            && in_array($first, [
                'p',
                'land',
                'c',
                'n',
                's',
                'ser',
                'pj',
                'prj',
                'bds',
            ], true)
        ) {
            return true;
        }

        return count($segments) >= 2
            && (
                in_array(
                    $first,
                    FrontendLocalization::segmentValues('category'),
                    true,
                )
                || in_array(
                    $first,
                    FrontendLocalization::segmentValues('product'),
                    true,
                )
            );
    }
}
