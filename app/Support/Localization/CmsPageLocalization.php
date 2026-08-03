<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\LocalizedRoute;
use App\Support\FrontendRouteUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CmsPageLocalization
{
    public const ROUTE_RESOURCE_TYPE = 'cms_page';

    /**
     * @var list<string>
     */
    public const TRANSLATABLE_FIELDS = [
        'title',
        'slug',
        'excerpt',
        'body',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    public function __construct(
        private readonly LocaleContext $localeContext,
        private readonly LocalizedRouteRegistry $routeRegistry,
        private readonly TranslationWorkflowManager $workflow,
    ) {}

    /**
     * Compatibility bridge for legacy theme seeders and integrations.
     */
    public function syncLegacySource(CmsPage $page): ?CmsPageTranslation
    {
        if (! Schema::hasTable('cms_page_translations') || ! $page->exists) {
            return null;
        }

        $locale = $this->localeContext->sourceLocale();
        $payload = $this->payloadFromPage($page);
        $translation = CmsPageTranslation::query()
            ->withoutGlobalScope('current_website')
            ->firstOrNew([
                'cms_page_id' => $page->getKey(),
                'locale' => $locale,
            ]);

        $translation->forceFill([
            'website_key' => $page->website_key ?: 'website-main',
            ...$payload,
            'translation_status' => $page->status === 'published'
                ? TranslationStatus::Published
                : TranslationStatus::Draft,
            'source_revision' => TranslationRevision::fingerprint($payload),
            'translation_revision' => TranslationRevision::fingerprint($payload),
            'is_machine_translated' => false,
            'translated_at' => $page->updated_at ?? now(),
            'reviewed_at' => $page->status === 'published'
                ? ($translation->reviewed_at ?? $page->updated_at ?? now())
                : null,
            'translation_published_at' => $page->status === 'published'
                ? ($page->publish_at ?? $translation->translation_published_at ?? now())
                : null,
        ])->save();

        $translation->refresh();
        $this->syncRoutes($translation);

        return $translation;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveDraft(
        CmsPage $page,
        string $locale,
        array $payload,
        bool $machineTranslated = false,
    ): CmsPageTranslation {
        $websiteKey = (string) ($page->website_key ?: 'website-main');
        $locale = $this->localeContext->resolveEditable($locale, $websiteKey);
        $payload = $this->normalizedPayload($payload);
        $this->guardSlugUniqueness($page, $locale, $payload['slug']);

        $source = $this->sourceTranslation($page);
        $sourcePayload = $source
            ? $this->payloadFromTranslation($source)
            : $this->payloadFromPage($page);
        $isSourceLocale = $locale === $this->localeContext->sourceLocale();
        $previousSourceRevision = TranslationRevision::fingerprint($sourcePayload);
        $currentSourcePayload = $isSourceLocale ? $payload : $sourcePayload;

        $translation = CmsPageTranslation::query()
            ->withoutGlobalScope('current_website')
            ->firstOrNew([
                'cms_page_id' => $page->getKey(),
                'locale' => $locale,
            ]);
        $translation->website_key = $websiteKey;

        $translation = $this->workflow->saveDraft(
            $translation,
            $payload,
            TranslationRevision::fingerprint($currentSourcePayload),
            $machineTranslated,
            ['editor' => 'cms.pages'],
        );

        if ($isSourceLocale) {
            $this->writeLegacySource($page, $translation);

            if ($previousSourceRevision !== TranslationRevision::fingerprint($payload)) {
                $page->translations()
                    ->withoutGlobalScope('current_website')
                    ->where('locale', '!=', $locale)
                    ->get()
                    ->each(function (CmsPageTranslation $localized) use ($payload): void {
                        if ($this->workflow->markOutdatedWhenSourceChanges($localized, $payload)) {
                            $this->syncRoutes($localized->refresh());
                        }
                    });
            }
        }

        $this->syncRoutes($translation);
        $this->syncAggregateStatus($page);

        return $translation;
    }

    public function transition(
        CmsPage $page,
        string $locale,
        TranslationStatus $target,
    ): CmsPageTranslation {
        $websiteKey = (string) ($page->website_key ?: 'website-main');
        $locale = $this->localeContext->resolveEditable($locale, $websiteKey);
        $translation = $page->translations()
            ->withoutGlobalScope('current_website')
            ->where('locale', $locale)
            ->firstOrFail();

        if ($target === TranslationStatus::Published) {
            $this->guardPublishable($translation);
        }

        $translation = $this->workflow->transition($translation, $target);
        $this->syncRoutes($translation);
        $this->syncAggregateStatus($page);

        if ($locale === $this->localeContext->sourceLocale()) {
            $page->withoutEvents(function () use ($page, $translation): void {
                $page->forceFill([
                    'status' => $translation->isPublishedTranslation() ? 'published' : 'draft',
                    'publish_at' => $translation->translation_published_at,
                ])->save();
            });
        }

        return $translation;
    }

    public function resolvePublic(
        string $websiteKey,
        string $locale,
        string $slug,
    ): ?CmsPageResolution {
        $locale = $this->localeContext->resolvePublic($locale, $websiteKey);
        $path = $this->routePath($slug);
        $registered = $this->routeRegistry->resolvePublic($locale, $path, $websiteKey);

        if (
            $registered !== null
            && $registered->resource_type === self::ROUTE_RESOURCE_TYPE
        ) {
            $page = CmsPage::query()
                ->forWebsite($websiteKey)
                ->with(['featuredMedia', 'translations'])
                ->whereKey($registered->resource_id)
                ->where('status', 'published')
                ->first();

            if ($page !== null) {
                $translation = $page->translations
                    ->first(fn (CmsPageTranslation $item): bool => (
                        $item->locale === $locale && $item->isPublishedTranslation()
                    ));

                if ($translation !== null) {
                    return new CmsPageResolution(
                        $this->apply($page, $translation),
                        $translation,
                        $locale,
                        false,
                        $registered->redirect_to,
                    );
                }
            }
        }

        $exact = CmsPageTranslation::query()
            ->forWebsite($websiteKey)
            ->publishedTranslation()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with(['page.featuredMedia', 'page.translations'])
            ->first();

        if ($exact?->page?->status === 'published') {
            return new CmsPageResolution(
                $this->apply($exact->page, $exact),
                $exact,
                $locale,
                false,
            );
        }

        foreach ($this->localeContext->fallbackChain($locale, $websiteKey) as $fallbackLocale) {
            if ($fallbackLocale === $locale || ! $this->localeContext->isPublic($fallbackLocale, $websiteKey)) {
                continue;
            }

            $fallback = CmsPageTranslation::query()
                ->forWebsite($websiteKey)
                ->publishedTranslation()
                ->where('locale', $fallbackLocale)
                ->where('slug', $slug)
                ->with(['page.featuredMedia', 'page.translations'])
                ->first();

            if ($fallback?->page?->status !== 'published') {
                continue;
            }

            return new CmsPageResolution(
                $this->apply($fallback->page, $fallback),
                $fallback,
                $locale,
                true,
                $this->routePath($fallback->slug),
            );
        }

        return null;
    }

    public function resolvePreview(CmsPage $page, string $locale): CmsPageResolution
    {
        $websiteKey = (string) ($page->website_key ?: 'website-main');
        $locale = $this->localeContext->resolveEditable($locale, $websiteKey);
        $page->loadMissing(['featuredMedia', 'translations']);
        $translation = $page->translations->firstWhere('locale', $locale)
            ?? $this->fallbackTranslation($page, $locale, false)
            ?? $this->syncLegacySource($page);

        abort_if($translation === null, 404);

        return new CmsPageResolution(
            $this->apply($page, $translation),
            $translation,
            $locale,
            $translation->locale !== $locale,
        );
    }

    /**
     * @return array{canonical_url: string, alternates: array<string, string>, resolved_locale: string}
     */
    public function seo(CmsPage $page, CmsPageTranslation $current): array
    {
        $websiteKey = (string) ($page->website_key ?: 'website-main');
        $translations = $page->relationLoaded('translations')
            ? $page->translations
            : $page->translations()->get();
        $alternates = $translations
            ->filter(fn (CmsPageTranslation $translation): bool => (
                $translation->isPublishedTranslation()
                && $this->localeContext->isPublic($translation->locale, $websiteKey)
            ))
            ->mapWithKeys(fn (CmsPageTranslation $translation): array => [
                $translation->locale => FrontendRouteUrl::page(
                    $translation->slug,
                    $translation->locale,
                ),
            ])
            ->all();
        $defaultLocale = $this->localeContext->defaultLocale($websiteKey);

        if (isset($alternates[$defaultLocale])) {
            $alternates['x-default'] = $alternates[$defaultLocale];
        }

        return [
            'canonical_url' => FrontendRouteUrl::page($current->slug, $current->locale),
            'alternates' => $alternates,
            'resolved_locale' => $current->locale,
        ];
    }

    /**
     * @return Collection<int, CmsPageTranslation>
     */
    public function publicTranslations(string $websiteKey): Collection
    {
        $publicLocales = $this->localeContext->publicLocales($websiteKey);

        return CmsPageTranslation::query()
            ->forWebsite($websiteKey)
            ->publishedTranslation()
            ->whereIn('locale', $publicLocales)
            ->whereHas('page', fn ($query) => $query->where('status', 'published'))
            ->with('page')
            ->orderBy('locale')
            ->orderBy('slug')
            ->get();
    }

    public function apply(
        CmsPage $page,
        CmsPageTranslation $translation,
    ): CmsPage {
        $localized = clone $page;

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $localized->setAttribute($field, $translation->getAttribute($field));
        }

        $localized->setAttribute('translation_status', $translation->translation_status);
        $localized->setAttribute('resolved_locale', $translation->locale);
        $localized->setRelation('currentTranslation', $translation);

        return $localized;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTranslation(CmsPageTranslation $translation): array
    {
        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);

        return [
            'id' => $translation->id,
            'locale' => $translation->locale,
            ...$this->payloadFromTranslation($translation),
            'translation_status' => $status->value,
            'allowed_transitions' => collect($status->allowedTransitions())
                ->map(fn (TranslationStatus $target): string => $target->value)
                ->values()
                ->all(),
            'is_machine_translated' => (bool) $translation->is_machine_translated,
            'translated_at' => $translation->translated_at?->toAtomString(),
            'reviewed_at' => $translation->reviewed_at?->toAtomString(),
            'translation_published_at' => $translation->translation_published_at?->toAtomString(),
            'public_url' => $translation->isPublishedTranslation()
                ? FrontendRouteUrl::page($translation->slug, $translation->locale)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $slug = Str::slug((string) ($payload['slug'] ?? ''));

        if ($title === '' || $slug === '') {
            throw ValidationException::withMessages([
                'title' => $title === '' ? 'Tiêu đề theo ngôn ngữ là bắt buộc.' : null,
                'slug' => $slug === '' ? 'Slug theo ngôn ngữ là bắt buộc.' : null,
            ]);
        }

        return collect(self::TRANSLATABLE_FIELDS)
            ->mapWithKeys(fn (string $field): array => [
                $field => in_array($field, ['title', 'slug'], true)
                    ? ($field === 'title' ? $title : $slug)
                    : ($payload[$field] ?? null),
            ])
            ->all();
    }

    private function guardSlugUniqueness(CmsPage $page, string $locale, string $slug): void
    {
        $exists = CmsPageTranslation::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $page->website_key ?: 'website-main')
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->where('cms_page_id', '!=', $page->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => 'Slug này đã được dùng bởi một Page khác trong cùng ngôn ngữ.',
            ]);
        }
    }

    private function guardPublishable(CmsPageTranslation $translation): void
    {
        $errors = [];

        foreach (['title', 'slug'] as $field) {
            if (blank($translation->getAttribute($field))) {
                $errors[$field] = sprintf('%s là bắt buộc trước khi xuất bản.', $field);
            }
        }

        if (! $this->localeContext->isPublic($translation->locale, $translation->website_key)) {
            $errors['locale'] = 'Ngôn ngữ phải được bật công khai ở cấp website trước khi xuất bản Page.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Rebuild the canonical route contract for one Page translation.
     *
     * Public so operational repair jobs and additive data migrations can reuse
     * exactly the same rules as the Admin publish workflow.
     */
    public function syncRoutes(CmsPageTranslation $translation): void
    {
        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);
        $isPublished = $status === TranslationStatus::Published;
        $path = $this->routePath($translation->slug);
        $this->pruneOrphanedRouteConflicts($translation, $path);

        if (! $isPublished) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $translation->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', self::ROUTE_RESOURCE_TYPE)
                ->where('resource_id', (string) $translation->cms_page_id)
                ->update([
                    'is_published' => false,
                    'published_at' => null,
                ]);
        }

        $route = $this->routeRegistry->register(
            $translation->locale,
            self::ROUTE_RESOURCE_TYPE,
            $translation->cms_page_id,
            $path,
            [
                'route_name' => 'site.pages.show',
                'is_canonical' => true,
                'is_published' => $isPublished,
                'metadata' => ['slug' => $translation->slug],
                'published_at' => $translation->translation_published_at,
            ],
            $translation->website_key,
        );

        if ($isPublished) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $translation->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', self::ROUTE_RESOURCE_TYPE)
                ->where('resource_id', (string) $translation->cms_page_id)
                ->whereKeyNot($route->getKey())
                ->update([
                    'is_canonical' => false,
                    'is_published' => true,
                    'redirect_to' => $path,
                ]);
        }
    }

    private function pruneOrphanedRouteConflicts(
        CmsPageTranslation $translation,
        string $path,
    ): void {
        LocalizedRoute::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $translation->website_key)
            ->where('locale', $translation->locale)
            ->where('path', $path)
            ->where('resource_type', self::ROUTE_RESOURCE_TYPE)
            ->where('resource_id', '!=', (string) $translation->cms_page_id)
            ->get()
            ->each(function (LocalizedRoute $route): void {
                $exists = CmsPage::query()
                    ->withoutGlobalScopes()
                    ->whereKey($route->resource_id)
                    ->exists();

                if (! $exists) {
                    $route->delete();
                }
            });
    }

    private function syncAggregateStatus(CmsPage $page): void
    {
        $hasPublished = $page->translations()
            ->withoutGlobalScope('current_website')
            ->publishedTranslation()
            ->exists();

        $page->withoutEvents(function () use ($page, $hasPublished): void {
            $page->forceFill([
                'status' => $hasPublished ? 'published' : 'draft',
                'publish_at' => $hasPublished
                    ? ($page->publish_at ?? now())
                    : null,
            ])->save();
        });
    }

    private function writeLegacySource(
        CmsPage $page,
        CmsPageTranslation $translation,
    ): void {
        $page->withoutEvents(function () use ($page, $translation): void {
            $page->forceFill([
                ...$this->payloadFromTranslation($translation),
                'status' => $translation->isPublishedTranslation() ? 'published' : 'draft',
                'publish_at' => $translation->translation_published_at,
            ])->save();
        });
    }

    private function sourceTranslation(CmsPage $page): ?CmsPageTranslation
    {
        return $page->translations()
            ->withoutGlobalScope('current_website')
            ->where('locale', $this->localeContext->sourceLocale())
            ->first();
    }

    private function fallbackTranslation(
        CmsPage $page,
        string $locale,
        bool $publishedOnly,
    ): ?CmsPageTranslation {
        foreach ($this->localeContext->fallbackChain($locale, $page->website_key) as $candidate) {
            $translation = $page->translations->first(function (CmsPageTranslation $item) use ($candidate, $publishedOnly): bool {
                return $item->locale === $candidate
                    && (! $publishedOnly || $item->isPublishedTranslation());
            });

            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromPage(CmsPage $page): array
    {
        return collect(self::TRANSLATABLE_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $page->getAttribute($field)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromTranslation(CmsPageTranslation $translation): array
    {
        return collect(self::TRANSLATABLE_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $translation->getAttribute($field)])
            ->all();
    }

    private function routePath(string $slug): string
    {
        return '/p/'.trim($slug, '/');
    }
}
