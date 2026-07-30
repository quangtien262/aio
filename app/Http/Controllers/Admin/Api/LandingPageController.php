<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Enums\TranslationStatus;
use App\Models\LandingPage;
use App\Models\LandingPageData;
use App\Models\SiteProfile;
use App\Support\FrontendLocalization;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LandingPageLocalization;
use App\Support\Localization\LocaleContext;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LandingPageController
{
    public function __construct(
        private readonly LandingPageLocalization $localization,
        private readonly LocaleContext $localeContext,
        private readonly SiteContext $currentSite,
    ) {}

    public function index(LandingPageBuilder $builder, ThemeRegistry $themeRegistry): JsonResponse
    {
        [$websiteKey, $themeKey] = $this->siteContext($themeRegistry);

        if ($builder->supportsTheme($themeKey)) {
            $builder->resolveHome($websiteKey, $themeKey);
        }

        $pages = LandingPage::query()
            ->with(['data', 'blocks'])
            ->where('website_key', $websiteKey)
            ->where('theme_key', $themeKey)
            ->orderByDesc('is_home')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LandingPage $page): array => $this->serialize($page))
            ->values();

        return response()->json([
            'data' => [
                'items' => $pages,
                'total' => $pages->count(),
                'metrics' => [
                    'published' => $pages->where('status', 'published')->count(),
                    'draft' => $pages->where('status', 'draft')->count(),
                ],
                'available_blocks' => $builder->availableBlocks($themeKey),
                'locales' => collect($this->localeContext->options($websiteKey))
                    ->filter(fn (array $locale): bool => (bool) $locale['is_enabled_for_editing'])
                    ->values(),
                'default_locale' => $this->localeContext->defaultLocale($websiteKey),
                'source_locale' => $this->localeContext->sourceLocale(),
                'theme_key' => $themeKey,
                'website_key' => $websiteKey,
            ],
        ]);
    }

    public function store(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        [$websiteKey, $themeKey] = $this->siteContext($themeRegistry);

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
            'media' => ['nullable', 'array'],
            'data_by_locale' => ['required', 'array'],
            'data_by_locale.*.title' => ['nullable', 'string', 'max:255'],
            'data_by_locale.*.slug' => ['nullable', 'string', 'max:160'],
            'data_by_locale.*.excerpt' => ['nullable', 'string'],
            'data_by_locale.*.meta_title' => ['nullable', 'string', 'max:255'],
            'data_by_locale.*.meta_description' => ['nullable', 'string'],
        ]);

        $title = $this->firstLocaleTitle($validated['data_by_locale']);
        $slug = $this->uniqueSlug($websiteKey, (string) ($validated['slug'] ?? $title));
        $maxSort = (int) LandingPage::query()
            ->where('website_key', $websiteKey)
            ->where('theme_key', $themeKey)
            ->max('sort_order');

        $page = DB::transaction(function () use ($validated, $websiteKey, $themeKey, $slug, $maxSort): LandingPage {
            $page = LandingPage::query()->create([
                'website_key' => $websiteKey,
                'theme_key' => $themeKey,
                'page_type' => 'landing',
                'slug' => $slug,
                'status' => 'draft',
                'template' => 'landing',
                'is_home' => false,
                'sort_order' => (int) ($validated['sort_order'] ?? ($maxSort + 10)),
                'settings' => $validated['settings'] ?? [],
                'media' => $validated['media'] ?? [],
                'published_at' => null,
            ]);

            $this->syncPageData($page, $validated['data_by_locale'], $slug);

            if (($validated['status'] ?? 'draft') === 'published') {
                $this->publishPageLocale($page, $this->localeContext->sourceLocale());
            }

            return $page;
        });

        return response()->json([
            'message' => 'Đã tạo landingpage.',
            'data' => $this->serialize($page->load(['data', 'blocks'])),
        ], 201);
    }

    public function update(
        Request $request,
        int $landingPage,
        ThemeRegistry $themeRegistry,
    ): JsonResponse
    {
        [$websiteKey, $themeKey] = $this->siteContext($themeRegistry);
        $page = LandingPage::query()
            ->with(['data', 'blocks'])
            ->where('website_key', $websiteKey)
            ->where('theme_key', $themeKey)
            ->findOrFail($landingPage);

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
            'media' => ['nullable', 'array'],
            'data_by_locale' => ['nullable', 'array'],
            'data_by_locale.*.title' => ['nullable', 'string', 'max:255'],
            'data_by_locale.*.slug' => ['nullable', 'string', 'max:160'],
            'data_by_locale.*.excerpt' => ['nullable', 'string'],
            'data_by_locale.*.meta_title' => ['nullable', 'string', 'max:255'],
            'data_by_locale.*.meta_description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($page, $validated): void {
            $payload = collect($validated)->only(['sort_order', 'settings', 'media'])->all();

            if (! $page->is_home && array_key_exists('slug', $validated)) {
                $payload['slug'] = $this->uniqueSlug($page->website_key, (string) $validated['slug'], $page->id);
            }

            if ($payload !== []) {
                $page->update($payload);
            }

            if (array_key_exists('data_by_locale', $validated)) {
                $this->syncPageData(
                    $page,
                    $validated['data_by_locale'] ?? [],
                    (string) ($validated['slug'] ?? $page->slug),
                );
            }

            if (! $page->is_home && array_key_exists('status', $validated)) {
                $sourceLocale = $this->localeContext->sourceLocale();
                $translation = $page->data()->where('locale', $sourceLocale)->first();

                if ($validated['status'] === 'published') {
                    $this->publishPageLocale($page, $sourceLocale);
                } elseif ($translation?->isPublishedTranslation()) {
                    $this->localization->transitionPage(
                        $page,
                        $sourceLocale,
                        TranslationStatus::Draft,
                    );
                }
            }
        });

        return response()->json([
            'message' => 'Đã cập nhật landingpage.',
            'data' => $this->serialize($page->fresh(['data', 'blocks'])),
        ]);
    }

    public function destroy(int $landingPage, ThemeRegistry $themeRegistry): JsonResponse
    {
        [$websiteKey, $themeKey] = $this->siteContext($themeRegistry);
        $page = LandingPage::query()
            ->where('website_key', $websiteKey)
            ->where('theme_key', $themeKey)
            ->findOrFail($landingPage);

        abort_if($page->is_home, 422, 'Không thể xóa trang chủ.');

        $page->delete();

        return response()->json([
            'message' => 'Đã xóa landingpage.',
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function siteContext(ThemeRegistry $themeRegistry): array
    {
        $siteProfile = SiteProfile::query()->first();
        $websiteKey = $this->currentSite->websiteKey();
        $themeKey = strtoupper((string) ($siteProfile?->active_theme_key ?? 'XD0301'));

        if ($themeRegistry->all()->firstWhere('key', $themeKey) === null) {
            $themeKey = 'XD0301';
        }

        return [$websiteKey, $themeKey];
    }

    /**
     * @param  array<string, array<string, mixed>>  $dataByLocale
     */
    private function syncPageData(
        LandingPage $page,
        array $dataByLocale,
        string $sourceSlug,
    ): void {
        foreach ($this->localeContext->editableLocales($page->website_key) as $locale) {
            $data = $dataByLocale[$locale] ?? [];
            $title = trim((string) ($data['title'] ?? ''));

            if ($title === '' && $locale !== $this->localeContext->sourceLocale()) {
                continue;
            }

            $this->localization->savePageDraft($page, $locale, [
                'slug' => $data['slug'] ?? $sourceSlug,
                'title' => $title !== '' ? $title : ($page->is_home ? 'Trang chủ' : 'Landingpage'),
                'excerpt' => $data['excerpt'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);
        }
    }

    public function transition(
        Request $request,
        LandingPage $landingPage,
        string $locale,
        ThemeRegistry $themeRegistry,
    ): JsonResponse {
        [$websiteKey, $themeKey] = $this->siteContext($themeRegistry);
        abort_unless(
            $landingPage->website_key === $websiteKey
            && strtoupper((string) $landingPage->theme_key) === $themeKey,
            404,
        );

        $validated = $request->validate([
            'translation_status' => [
                'required',
                'string',
                Rule::enum(TranslationStatus::class),
            ],
        ]);
        $translation = $this->localization->transitionPage(
            $landingPage,
            $locale,
            TranslationStatus::from($validated['translation_status']),
        );

        return response()->json([
            'message' => 'Đã chuyển trạng thái bản dịch landing page.',
            'data' => [
                'translation' => $this->serializeTranslation($landingPage, $translation),
                'completeness' => $this->localization->completeness($landingPage, $locale),
            ],
        ]);
    }

    private function publishPageLocale(LandingPage $page, string $locale): void
    {
        $translation = $page->data()->where('locale', $locale)->firstOrFail();
        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);

        if ($status === TranslationStatus::Published) {
            return;
        }

        if ($status !== TranslationStatus::Ready) {
            $this->localization->transitionPage($page, $locale, TranslationStatus::Ready);
        }

        $this->localization->transitionPage($page, $locale, TranslationStatus::Published);
    }

    /**
     * @param  array<string, array<string, mixed>>  $dataByLocale
     */
    private function firstLocaleTitle(array $dataByLocale): string
    {
        foreach (FrontendLocalization::editableLocales() as $locale) {
            $title = trim((string) ($dataByLocale[$locale]['title'] ?? ''));

            if ($title !== '') {
                return $title;
            }
        }

        return 'Landingpage';
    }

    private function uniqueSlug(string $websiteKey, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'landingpage';
        $slug = $base;
        $counter = 2;

        while (LandingPage::query()
            ->where('website_key', $websiteKey)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LandingPage $page): array
    {
        $defaultLocale = $this->localeContext->defaultLocale($page->website_key);
        $fallbackData = $page->data->firstWhere('locale', $defaultLocale) ?? $page->data->first();
        $publicUrl = $page->is_home
            ? route('site.home', ['locale' => $defaultLocale])
            : route('site.landing.show', [
                'locale' => $defaultLocale,
                'slug' => $fallbackData?->slug ?? $page->slug,
            ]);

        return [
            'id' => $page->id,
            'website_key' => $page->website_key,
            'theme_key' => $page->theme_key,
            'page_type' => $page->page_type,
            'slug' => $page->slug,
            'status' => $page->status,
            'template' => $page->template,
            'is_home' => (bool) $page->is_home,
            'sort_order' => (int) $page->sort_order,
            'settings' => $page->settings ?? [],
            'media' => $page->media ?? [],
            'published_at' => $page->published_at?->toDateTimeString(),
            'title' => $fallbackData?->title,
            'excerpt' => $fallbackData?->excerpt,
            'meta_title' => $fallbackData?->meta_title,
            'meta_description' => $fallbackData?->meta_description,
            'data_by_locale' => collect($this->localeContext->editableLocales($page->website_key))
                ->mapWithKeys(function (string $locale) use ($page, $fallbackData): array {
                    $data = $page->data->firstWhere('locale', $locale);

                    return [
                        $locale => [
                            'slug' => $data?->slug ?? ($locale === $this->localeContext->sourceLocale() ? $page->slug : null),
                            'title' => $data?->title
                                ?? ($locale === $this->localeContext->sourceLocale() ? $fallbackData?->title : null),
                            'excerpt' => $data?->excerpt
                                ?? ($locale === $this->localeContext->sourceLocale() ? $fallbackData?->excerpt : null),
                            'meta_title' => $data?->meta_title
                                ?? ($locale === $this->localeContext->sourceLocale() ? $fallbackData?->meta_title : null),
                            'meta_description' => $data?->meta_description
                                ?? ($locale === $this->localeContext->sourceLocale() ? $fallbackData?->meta_description : null),
                            'translation_status' => $data?->translation_status?->value
                                ?? TranslationStatus::Missing->value,
                            'allowed_transitions' => $data?->translation_status
                                ? collect($data->translation_status->allowedTransitions())
                                    ->map(fn (TranslationStatus $status): string => $status->value)
                                    ->all()
                                : [],
                            'completeness' => $this->localization->completeness($page, $locale),
                        ],
                    ];
                })
                ->all(),
            'block_count' => $page->blocks->where('block_type', '!=', 'footer_contact')->count(),
            'visible_block_count' => $page->blocks->where('block_type', '!=', 'footer_contact')->where('is_visible', true)->count(),
            'path' => $page->is_home ? '/' : '/land/'.$page->slug,
            'public_url' => $publicUrl,
            'admin_url' => $publicUrl.'?mod=admin',
            'updated_at' => $page->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTranslation(
        LandingPage $page,
        LandingPageData $translation,
    ): array {
        $status = $translation->translation_status instanceof TranslationStatus
            ? $translation->translation_status
            : TranslationStatus::from((string) $translation->translation_status);

        return [
            'id' => $translation->id,
            'landing_page_id' => $page->id,
            'locale' => $translation->locale,
            'slug' => $translation->slug,
            'title' => $translation->title,
            'excerpt' => $translation->excerpt,
            'meta_title' => $translation->meta_title,
            'meta_description' => $translation->meta_description,
            'translation_status' => $status->value,
            'allowed_transitions' => collect($status->allowedTransitions())
                ->map(fn (TranslationStatus $target): string => $target->value)
                ->all(),
        ];
    }
}
