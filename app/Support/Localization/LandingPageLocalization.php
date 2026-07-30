<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\LocalizedRoute;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LandingPageLocalization
{
    public const ROUTE_RESOURCE_TYPE = 'landing_page';

    public function __construct(
        private readonly LocaleContext $localeContext,
        private readonly LocalizedRouteRegistry $routeRegistry,
        private readonly TranslationWorkflowManager $workflow,
    ) {}

    public function syncLegacyPageTranslation(
        LandingPageData $translation,
    ): LandingPageData {
        $translation->loadMissing('landingPage');
        $page = $translation->landingPage;

        if ($page === null) {
            return $translation;
        }

        $payload = [
            'slug' => $translation->slug ?: ($page->is_home ? 'home' : $page->slug),
            'title' => $translation->title,
            'excerpt' => $translation->excerpt,
            'meta_title' => $translation->meta_title,
            'meta_description' => $translation->meta_description,
        ];
        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::tryFrom((string) $translation->translation_status);
        $status ??= $translation->locale === $this->localeContext->sourceLocale()
            && $page->status === 'published'
                ? TranslationStatus::Published
                : TranslationStatus::Draft;
        $revision = TranslationRevision::fingerprint($payload);

        LandingPageData::withoutEvents(function () use (
            $translation,
            $payload,
            $status,
            $revision,
            $page,
        ): void {
            $translation->forceFill([
                ...$payload,
                'translation_status' => $status,
                'source_revision' => $translation->source_revision ?: $revision,
                'translation_revision' => $translation->translation_revision ?: $revision,
                'translated_at' => $translation->translated_at ?: now(),
                'reviewed_at' => $status === TranslationStatus::Published
                    ? ($translation->reviewed_at ?: now())
                    : $translation->reviewed_at,
                'translation_published_at' => $status === TranslationStatus::Published
                    ? ($translation->translation_published_at ?: $page->published_at ?: now())
                    : null,
            ])->save();
        });

        $this->syncRoute($page, $translation);

        return $translation->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function savePageDraft(
        LandingPage $page,
        string $locale,
        array $payload,
        bool $machineTranslated = false,
    ): LandingPageData {
        $locale = $this->localeContext->resolveEditable($locale, $page->website_key);
        $normalized = [
            'slug' => $page->is_home
                ? 'home'
                : (Str::slug((string) ($payload['slug'] ?? $page->slug)) ?: 'landingpage'),
            'title' => trim((string) ($payload['title'] ?? '')),
            'excerpt' => $payload['excerpt'] ?? null,
            'meta_title' => $payload['meta_title'] ?? null,
            'meta_description' => $payload['meta_description'] ?? null,
        ];
        $this->guardPageSlug($page, $locale, $normalized['slug']);
        $source = $this->pageTranslation(
            $page,
            $this->localeContext->sourceLocale(),
            false,
        );
        $translation = $this->pageTranslation($page, $locale, false)
            ?? new LandingPageData([
                'landing_page_id' => $page->id,
                'locale' => $locale,
            ]);
        /** @var LandingPageData $translation */
        $translation = $this->workflow->saveDraft(
            $translation,
            $normalized,
            TranslationRevision::fingerprint($source ? $this->pagePayload($source) : $normalized),
            $machineTranslated,
            ['editor' => 'landing.pages', 'schema_version' => 1],
        );

        if ($locale === $this->localeContext->sourceLocale()) {
            $page->forceFill(['slug' => $normalized['slug']])->save();
            $this->markOtherPageTranslationsOutdated($page, $normalized);
        }

        $this->syncRoute($page, $translation);

        return $translation;
    }

    public function transitionPage(
        LandingPage $page,
        string $locale,
        TranslationStatus $target,
    ): LandingPageData {
        $translation = $this->pageTranslation(
            $page,
            $this->localeContext->resolveEditable($locale, $page->website_key),
            false,
        );

        abort_if($translation === null, 404);

        if ($target === TranslationStatus::Published) {
            $this->guardPagePublishable($page, $translation);
        }

        /** @var LandingPageData $translation */
        $translation = $this->workflow->transition($translation, $target);
        $this->syncRoute($page, $translation);
        $this->syncAggregateStatus($page);

        return $translation;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveBlockDraft(
        LandingPageBlock $block,
        string $locale,
        array $payload,
        bool $machineTranslated = false,
    ): LandingPageBlockData {
        $block->loadMissing('landingPage');
        $websiteKey = (string) $block->landingPage->website_key;
        $locale = $this->localeContext->resolveEditable($locale, $websiteKey);
        $normalized = [
            'schema_version' => max(1, (int) ($payload['schema_version'] ?? $block->schema_version ?? 1)),
            'title' => $payload['title'] ?? null,
            'subtitle' => $payload['subtitle'] ?? null,
            'description' => $payload['description'] ?? null,
            'button_label' => $payload['button_label'] ?? null,
            'content' => json_encode(
                (array) ($payload['content'] ?? []),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        ];
        $source = $this->blockTranslation(
            $block,
            $this->localeContext->sourceLocale(),
            false,
        );
        $translation = $this->blockTranslation($block, $locale, false)
            ?? new LandingPageBlockData([
                'landing_page_block_id' => $block->id,
                'locale' => $locale,
            ]);
        /** @var LandingPageBlockData $translation */
        $translation = $this->workflow->saveDraft(
            $translation,
            $normalized,
            TranslationRevision::fingerprint($source ? $this->blockPayload($source) : $normalized),
            $machineTranslated,
            [
                'editor' => 'landing.blocks',
                'schema_version' => $normalized['schema_version'],
            ],
        );

        if ($locale === $this->localeContext->sourceLocale()) {
            $this->markOtherBlockTranslationsOutdated($block, $normalized);
        }

        return $translation;
    }

    public function transitionBlock(
        LandingPageBlock $block,
        string $locale,
        TranslationStatus $target,
    ): LandingPageBlockData {
        $block->loadMissing('landingPage');
        $translation = $this->blockTranslation(
            $block,
            $this->localeContext->resolveEditable(
                $locale,
                (string) $block->landingPage->website_key,
            ),
            false,
        );

        abort_if($translation === null, 404);

        if (
            $target === TranslationStatus::Published
            && ! $this->localeContext->isPublic(
                $translation->locale,
                (string) $block->landingPage->website_key,
            )
        ) {
            throw ValidationException::withMessages([
                'locale' => 'Ngôn ngữ phải được bật công khai trước khi xuất bản block.',
            ]);
        }

        /** @var LandingPageBlockData $translation */
        $translation = $this->workflow->transition($translation, $target);

        return $translation;
    }

    /**
     * @return array{page: LandingPage, translation: LandingPageData, resolved_locale: string, used_fallback: bool, redirect_to: ?string}|null
     */
    public function resolvePublic(
        string $websiteKey,
        string $themeKey,
        string $locale,
        string $slug,
    ): ?array {
        $locale = $this->localeContext->resolvePublic($locale, $websiteKey);
        $path = '/land/'.trim($slug, '/');
        $registered = $this->routeRegistry->resolvePublic($locale, $path, $websiteKey);

        if (
            $registered !== null
            && $registered->resource_type === self::ROUTE_RESOURCE_TYPE
        ) {
            $page = LandingPage::query()
                ->with(['data', 'blocks.data'])
                ->where('website_key', $websiteKey)
                ->where('theme_key', strtoupper($themeKey))
                ->whereKey($registered->resource_id)
                ->where('status', 'published')
                ->first();
            $translation = $page?->data
                ->first(fn (LandingPageData $item): bool => (
                    $item->locale === $locale && $item->isPublishedTranslation()
                ));

            if ($page !== null && $translation !== null) {
                return [
                    'page' => $page,
                    'translation' => $translation,
                    'resolved_locale' => $locale,
                    'used_fallback' => false,
                    'redirect_to' => $registered->redirect_to,
                ];
            }
        }

        foreach ($this->localeContext->fallbackChain($locale, $websiteKey) as $candidate) {
            if (! $this->localeContext->isPublic($candidate, $websiteKey)) {
                continue;
            }

            $translation = LandingPageData::query()
                ->publishedTranslation()
                ->where('locale', $candidate)
                ->where('slug', $slug)
                ->whereHas('landingPage', fn ($query) => $query
                    ->where('website_key', $websiteKey)
                    ->where('theme_key', strtoupper($themeKey))
                    ->where('status', 'published'))
                ->with(['landingPage.data', 'landingPage.blocks.data'])
                ->first();

            if ($translation?->landingPage === null) {
                continue;
            }

            return [
                'page' => $translation->landingPage,
                'translation' => $translation,
                'resolved_locale' => $candidate,
                'used_fallback' => $candidate !== $locale,
                'redirect_to' => null,
            ];
        }

        return null;
    }

    /**
     * @return array{canonical_url: string, alternates: array<string, string>, resolved_locale: string}
     */
    public function seo(LandingPage $page, LandingPageData $current): array
    {
        $page->loadMissing('data');
        $alternates = $page->data
            ->filter(fn (LandingPageData $translation): bool => (
                $translation->isPublishedTranslation()
                && filled($translation->slug)
                && $this->localeContext->isPublic($translation->locale, $page->website_key)
            ))
            ->mapWithKeys(fn (LandingPageData $translation): array => [
                $translation->locale => $page->is_home
                    ? route('site.home', ['locale' => $translation->locale])
                    : route('site.landing.show', [
                        'locale' => $translation->locale,
                        'slug' => $translation->slug,
                    ]),
            ])
            ->all();
        $defaultLocale = $this->localeContext->defaultLocale($page->website_key);

        if (isset($alternates[$defaultLocale])) {
            $alternates['x-default'] = $alternates[$defaultLocale];
        }

        return [
            'canonical_url' => $page->is_home
                ? route('site.home', ['locale' => $current->locale])
                : route('site.landing.show', [
                    'locale' => $current->locale,
                    'slug' => $current->slug,
                ]),
            'alternates' => $alternates,
            'resolved_locale' => $current->locale,
        ];
    }

    /**
     * @return array{complete: bool, missing_block_ids: list<int>, published_blocks: int, visible_blocks: int}
     */
    public function completeness(LandingPage $page, string $locale): array
    {
        $page->load(['data', 'blocks.data']);
        $visibleBlocks = $page->blocks
            ->filter(fn (LandingPageBlock $block): bool => (
                $block->is_visible && $block->block_type !== 'footer_contact'
            ));
        $missing = $visibleBlocks
            ->reject(fn (LandingPageBlock $block): bool => $block->data
                ->contains(fn (LandingPageBlockData $data): bool => (
                    $data->locale === $locale && $data->isPublishedTranslation()
                )))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'complete' => $missing === [],
            'missing_block_ids' => $missing,
            'published_blocks' => $visibleBlocks->count() - count($missing),
            'visible_blocks' => $visibleBlocks->count(),
        ];
    }

    private function guardPagePublishable(
        LandingPage $page,
        LandingPageData $translation,
    ): void {
        if (blank($translation->title) || (! $page->is_home && blank($translation->slug))) {
            throw ValidationException::withMessages([
                'title' => 'Tiêu đề và slug là bắt buộc trước khi xuất bản landing page.',
            ]);
        }

        if (! $this->localeContext->isPublic($translation->locale, $page->website_key)) {
            throw ValidationException::withMessages([
                'locale' => 'Ngôn ngữ phải được bật công khai trước khi xuất bản landing page.',
            ]);
        }

        $completeness = $this->completeness($page, $translation->locale);

        if (! $completeness['complete']) {
            throw ValidationException::withMessages([
                'blocks' => 'Còn block hiển thị chưa được xuất bản cho ngôn ngữ này.',
            ]);
        }
    }

    private function guardPageSlug(
        LandingPage $page,
        string $locale,
        string $slug,
    ): void {
        $exists = LandingPageData::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->where('landing_page_id', '!=', $page->id)
            ->whereHas('landingPage', fn ($query) => $query
                ->where('website_key', $page->website_key)
                ->where('theme_key', $page->theme_key))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => 'Slug landing page đã được dùng trong cùng ngôn ngữ.',
            ]);
        }
    }

    private function syncRoute(
        LandingPage $page,
        LandingPageData $translation,
    ): void {
        if ($page->is_home) {
            return;
        }

        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);
        $isPublished = $status === TranslationStatus::Published;
        $path = '/land/'.$translation->slug;

        if (! $isPublished) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $page->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', self::ROUTE_RESOURCE_TYPE)
                ->where('resource_id', (string) $page->id)
                ->update(['is_published' => false, 'published_at' => null]);
        }

        $route = $this->routeRegistry->register(
            $translation->locale,
            self::ROUTE_RESOURCE_TYPE,
            $page->id,
            $path,
            [
                'route_name' => 'site.landing.show',
                'is_canonical' => true,
                'is_published' => $isPublished,
                'metadata' => ['slug' => $translation->slug],
                'published_at' => $translation->translation_published_at,
            ],
            $page->website_key,
        );

        if ($isPublished) {
            LocalizedRoute::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $page->website_key)
                ->where('locale', $translation->locale)
                ->where('resource_type', self::ROUTE_RESOURCE_TYPE)
                ->where('resource_id', (string) $page->id)
                ->whereKeyNot($route->getKey())
                ->update([
                    'is_canonical' => false,
                    'is_published' => true,
                    'redirect_to' => $path,
                ]);
        }
    }

    private function syncAggregateStatus(LandingPage $page): void
    {
        $published = $page->data()->publishedTranslation()->exists();

        $page->forceFill([
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? ($page->published_at ?? now()) : null,
        ])->save();
    }

    private function markOtherPageTranslationsOutdated(
        LandingPage $page,
        array $sourcePayload,
    ): void {
        $page->data()
            ->where('locale', '!=', $this->localeContext->sourceLocale())
            ->get()
            ->each(fn (LandingPageData $translation) => $this->workflow
                ->markOutdatedWhenSourceChanges($translation, $sourcePayload));
    }

    private function markOtherBlockTranslationsOutdated(
        LandingPageBlock $block,
        array $sourcePayload,
    ): void {
        $block->data()
            ->where('locale', '!=', $this->localeContext->sourceLocale())
            ->get()
            ->each(fn (LandingPageBlockData $translation) => $this->workflow
                ->markOutdatedWhenSourceChanges($translation, $sourcePayload));
    }

    private function pageTranslation(
        LandingPage $page,
        string $locale,
        bool $publishedOnly,
    ): ?LandingPageData {
        return $page->data()
            ->where('locale', $locale)
            ->when($publishedOnly, fn ($query) => $query->publishedTranslation())
            ->first();
    }

    private function blockTranslation(
        LandingPageBlock $block,
        string $locale,
        bool $publishedOnly,
    ): ?LandingPageBlockData {
        return $block->data()
            ->where('locale', $locale)
            ->when($publishedOnly, fn ($query) => $query->publishedTranslation())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(LandingPageData $translation): array
    {
        return $translation->only([
            'slug',
            'title',
            'excerpt',
            'meta_title',
            'meta_description',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(LandingPageBlockData $translation): array
    {
        return $translation->only([
            'schema_version',
            'title',
            'subtitle',
            'description',
            'button_label',
            'content',
        ]);
    }
}
