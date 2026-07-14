<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\LandingPage;
use App\Models\LandingPageData;
use App\Models\SiteProfile;
use App\Support\FrontendLocalization;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LandingPageController
{
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
                'locales' => FrontendLocalization::localeOptions(),
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
                'status' => $validated['status'] ?? 'draft',
                'template' => 'landing',
                'is_home' => false,
                'sort_order' => (int) ($validated['sort_order'] ?? ($maxSort + 10)),
                'settings' => $validated['settings'] ?? [],
                'media' => $validated['media'] ?? [],
                'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
            ]);

            $this->syncPageData($page, $validated['data_by_locale']);

            return $page;
        });

        return response()->json([
            'message' => 'Đã tạo landingpage.',
            'data' => $this->serialize($page->load(['data', 'blocks'])),
        ], 201);
    }

    public function update(Request $request, int $landingPage): JsonResponse
    {
        $page = LandingPage::query()->with(['data', 'blocks'])->findOrFail($landingPage);

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
            'media' => ['nullable', 'array'],
            'data_by_locale' => ['nullable', 'array'],
            'data_by_locale.*.title' => ['nullable', 'string', 'max:255'],
            'data_by_locale.*.excerpt' => ['nullable', 'string'],
            'data_by_locale.*.meta_title' => ['nullable', 'string', 'max:255'],
            'data_by_locale.*.meta_description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($page, $validated): void {
            $payload = collect($validated)->only(['status', 'sort_order', 'settings', 'media'])->all();

            if ($page->is_home) {
                unset($payload['status']);
            }

            if (! $page->is_home && array_key_exists('slug', $validated)) {
                $payload['slug'] = $this->uniqueSlug($page->website_key, (string) $validated['slug'], $page->id);
            }

            if (array_key_exists('status', $payload)) {
                $payload['published_at'] = $payload['status'] === 'published'
                    ? ($page->published_at ?? now())
                    : null;
            }

            if ($payload !== []) {
                $page->update($payload);
            }

            if (array_key_exists('data_by_locale', $validated)) {
                $this->syncPageData($page, $validated['data_by_locale'] ?? []);
            }
        });

        return response()->json([
            'message' => 'Đã cập nhật landingpage.',
            'data' => $this->serialize($page->fresh(['data', 'blocks'])),
        ]);
    }

    public function destroy(int $landingPage): JsonResponse
    {
        $page = LandingPage::query()->findOrFail($landingPage);

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
        $branding = $siteProfile?->branding ?? [];
        $websiteKey = (string) ($branding['website_key'] ?? 'website-main');
        $themeKey = strtoupper((string) ($siteProfile?->active_theme_key ?? 'XD0301'));

        if ($themeRegistry->all()->firstWhere('key', $themeKey) === null) {
            $themeKey = 'XD0301';
        }

        return [$websiteKey, $themeKey];
    }

    /**
     * @param array<string, array<string, mixed>> $dataByLocale
     */
    private function syncPageData(LandingPage $page, array $dataByLocale): void
    {
        foreach (FrontendLocalization::supportedLocales() as $locale) {
            $data = $dataByLocale[$locale] ?? [];
            $title = trim((string) ($data['title'] ?? ''));

            LandingPageData::query()->updateOrCreate(
                ['landing_page_id' => $page->id, 'locale' => $locale],
                [
                    'title' => $title !== '' ? $title : ($page->is_home ? 'Trang chủ' : 'Landingpage'),
                    'excerpt' => $data['excerpt'] ?? null,
                    'meta_title' => $data['meta_title'] ?? null,
                    'meta_description' => $data['meta_description'] ?? null,
                ],
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $dataByLocale
     */
    private function firstLocaleTitle(array $dataByLocale): string
    {
        foreach (FrontendLocalization::supportedLocales() as $locale) {
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
        $defaultLocale = FrontendLocalization::defaultLocale();
        $fallbackData = $page->data->firstWhere('locale', $defaultLocale) ?? $page->data->first();
        $publicUrl = $page->is_home
            ? route('site.home', ['locale' => $defaultLocale])
            : route('site.landing.show', ['locale' => $defaultLocale, 'slug' => $page->slug]);

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
            'data_by_locale' => collect(FrontendLocalization::supportedLocales())
                ->mapWithKeys(function (string $locale) use ($page, $fallbackData): array {
                    $data = $page->data->firstWhere('locale', $locale);

                    return [
                        $locale => [
                            'title' => $data?->title ?? $fallbackData?->title,
                            'excerpt' => $data?->excerpt ?? $fallbackData?->excerpt,
                            'meta_title' => $data?->meta_title ?? $fallbackData?->meta_title,
                            'meta_description' => $data?->meta_description ?? $fallbackData?->meta_description,
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
}
