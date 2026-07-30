<?php

namespace App\Support\Localization;

use App\Models\LocalizedRoute;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocalizedRouteRegistry
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly LocaleContext $localeContext,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function register(
        string $locale,
        string $resourceType,
        string|int $resourceId,
        string $path,
        array $attributes = [],
        ?string $websiteKey = null,
    ): LocalizedRoute {
        $websiteKey = $this->siteContext->normalizeWebsiteKey(
            $websiteKey ?? $this->siteContext->websiteKey(),
        );
        $locale = LocaleCode::normalize($locale);

        if (! $this->localeContext->isEditable($locale, $websiteKey)) {
            throw ValidationException::withMessages([
                'locale' => 'Ngôn ngữ chưa được bật để biên tập trên website này.',
            ]);
        }

        $path = $this->normalizePath($path);

        return DB::transaction(function () use (
            $websiteKey,
            $locale,
            $resourceType,
            $resourceId,
            $path,
            $attributes,
        ): LocalizedRoute {
            $conflict = LocalizedRoute::query()
                ->forWebsite($websiteKey)
                ->where('locale', $locale)
                ->where('path', $path)
                ->where(function ($query) use ($resourceType, $resourceId): void {
                    $query
                        ->where('resource_type', '!=', $resourceType)
                        ->orWhere('resource_id', '!=', (string) $resourceId);
                })
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'path' => 'Đường dẫn này đã được sử dụng trong cùng ngôn ngữ.',
                ]);
            }

            if (($attributes['is_canonical'] ?? true) === true) {
                LocalizedRoute::query()
                    ->forWebsite($websiteKey)
                    ->where('locale', $locale)
                    ->where('resource_type', $resourceType)
                    ->where('resource_id', (string) $resourceId)
                    ->update(['is_canonical' => false]);
            }

            return LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->updateOrCreate(
                    [
                        'website_key' => $websiteKey,
                        'locale' => $locale,
                        'resource_type' => $resourceType,
                        'resource_id' => (string) $resourceId,
                        'path' => $path,
                    ],
                    [
                        'route_name' => $attributes['route_name'] ?? null,
                        'is_canonical' => (bool) ($attributes['is_canonical'] ?? true),
                        'is_published' => (bool) ($attributes['is_published'] ?? false),
                        'redirect_to' => isset($attributes['redirect_to'])
                            ? $this->normalizePath((string) $attributes['redirect_to'])
                            : null,
                        'metadata' => $attributes['metadata'] ?? null,
                        'published_at' => ($attributes['is_published'] ?? false)
                            ? ($attributes['published_at'] ?? now())
                            : null,
                    ],
                );
        });
    }

    public function resolvePublic(
        string $locale,
        string $path,
        ?string $websiteKey = null,
    ): ?LocalizedRoute {
        $websiteKey = $this->siteContext->normalizeWebsiteKey(
            $websiteKey ?? $this->siteContext->websiteKey(),
        );
        $locale = LocaleCode::tryNormalize($locale);

        if ($locale === null || ! $this->localeContext->isPublic($locale, $websiteKey)) {
            return null;
        }

        return LocalizedRoute::query()
            ->forWebsite($websiteKey)
            ->where('locale', $locale)
            ->where('path', $this->normalizePath($path))
            ->where('is_published', true)
            ->first();
    }

    public function canonicalPath(
        Model|string $resource,
        string|int|null $resourceId,
        string $locale,
        ?string $websiteKey = null,
    ): ?string {
        $resourceType = $resource instanceof Model ? $resource->getMorphClass() : $resource;
        $resourceId ??= $resource instanceof Model ? $resource->getKey() : null;

        if ($resourceId === null) {
            return null;
        }

        $websiteKey = $this->siteContext->normalizeWebsiteKey(
            $websiteKey ?? $this->siteContext->websiteKey(),
        );

        foreach ($this->localeContext->fallbackChain($locale, $websiteKey) as $candidateLocale) {
            if (! $this->localeContext->isPublic($candidateLocale, $websiteKey)) {
                continue;
            }

            $route = LocalizedRoute::query()
                ->forWebsite($websiteKey)
                ->where('locale', $candidateLocale)
                ->where('resource_type', $resourceType)
                ->where('resource_id', (string) $resourceId)
                ->where('is_canonical', true)
                ->where('is_published', true)
                ->first();

            if ($route !== null) {
                return $route->path;
            }
        }

        return null;
    }

    /**
     * Resolve canonical paths only for the requested public locales.
     *
     * Unlike canonicalPath(), this method never falls back to another locale.
     * It is intended for language switchers and hreflang navigation, where
     * linking a locale label to content from a different language is unsafe.
     *
     * @param  iterable<int, string>  $locales
     * @return array<string, string>
     */
    public function canonicalPaths(
        Model|string $resource,
        string|int|null $resourceId,
        iterable $locales,
        ?string $websiteKey = null,
    ): array {
        $resourceType = $resource instanceof Model ? $resource->getMorphClass() : $resource;
        $resourceId ??= $resource instanceof Model ? $resource->getKey() : null;

        if ($resourceId === null) {
            return [];
        }

        $websiteKey = $this->siteContext->normalizeWebsiteKey(
            $websiteKey ?? $this->siteContext->websiteKey(),
        );
        $publicLocales = collect($locales)
            ->map(fn (string $locale): ?string => LocaleCode::tryNormalize($locale))
            ->filter(fn (?string $locale): bool => (
                $locale !== null && $this->localeContext->isPublic($locale, $websiteKey)
            ))
            ->unique()
            ->values()
            ->all();

        if ($publicLocales === []) {
            return [];
        }

        return LocalizedRoute::query()
            ->forWebsite($websiteKey)
            ->whereIn('locale', $publicLocales)
            ->where('resource_type', $resourceType)
            ->where('resource_id', (string) $resourceId)
            ->where('is_canonical', true)
            ->where('is_published', true)
            ->get(['locale', 'path'])
            ->mapWithKeys(fn (LocalizedRoute $route): array => [
                $route->locale => $route->path,
            ])
            ->all();
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }
}
