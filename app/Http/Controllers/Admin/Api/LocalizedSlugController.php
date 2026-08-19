<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\CmsPageTranslation;
use App\Models\ContentTranslation;
use App\Models\LandingPageData;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedSlugGenerator;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocalizedSlugController
{
    public function __construct(
        private readonly LocalizedSlugGenerator $slugs,
        private readonly LocaleContext $localeContext,
        private readonly SiteContext $siteContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $supportedTypes = array_keys((array) config('localized-content.resources', []));
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:1000'],
            'locale' => ['required', 'string', 'max:35'],
            'resource_type' => ['required', 'string', Rule::in([
                ...$supportedTypes,
                'cms_page',
                'landing_page',
            ])],
            'resource_id' => ['nullable', 'string', 'max:64'],
            'fallback_slug' => ['nullable', 'string', 'max:255'],
        ]);
        $websiteKey = $this->siteContext->websiteKey();
        $locale = $this->localeContext->resolveEditable($validated['locale'], $websiteKey);
        $resourceType = $validated['resource_type'];
        $resourceId = (string) ($validated['resource_id'] ?? '');
        $baseSlug = $this->slugs->normalize(
            $validated['value'],
            $locale,
            $validated['fallback_slug'] ?? null,
        );
        $slug = $this->slugs->unique(
            $baseSlug,
            fn (string $candidate): bool => $this->slugExists(
                $websiteKey,
                $locale,
                $resourceType,
                $resourceId,
                $candidate,
            ),
        );

        return response()->json(['data' => [
            'slug' => $slug,
            'base_slug' => $baseSlug,
            'locale' => $locale,
            'resource_type' => $resourceType,
        ]]);
    }

    private function slugExists(
        string $websiteKey,
        string $locale,
        string $resourceType,
        string $resourceId,
        string $slug,
    ): bool {
        if ($resourceType === 'cms_page') {
            return CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->when($resourceId !== '', fn ($query) => $query->where('cms_page_id', '!=', $resourceId))
                ->exists();
        }

        if ($resourceType === 'landing_page') {
            return LandingPageData::query()
                ->withoutGlobalScopes()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->whereHas('landingPage', fn ($query) => $query->where('website_key', $websiteKey))
                ->when($resourceId !== '', fn ($query) => $query->where('landing_page_id', '!=', $resourceId))
                ->exists();
        }

        return ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('resource_type', $resourceType)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($resourceId !== '', fn ($query) => $query->where('resource_id', '!=', $resourceId))
            ->exists();
    }
}
