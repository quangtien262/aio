<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Enums\TranslationStatus;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Support\FrontendLocalization;
use App\Support\Localization\CmsPageLocalization;
use App\Support\Localization\LocaleContext;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageIndexController
{
    public function __construct(
        private readonly CmsPageLocalization $localization,
        private readonly LocaleContext $localeContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $websiteKey = app(SiteContext::class)->websiteKey();
        $defaultLocale = $this->localeContext->defaultLocale($websiteKey);
        $activeLocale = filled($request->query('locale'))
            ? $this->localeContext->resolveEditable(
                (string) $request->query('locale'),
                $websiteKey,
            )
            : $defaultLocale;
        $pages = CmsPage::query()
            ->with(['featuredMedia', 'translations'])
            ->orderBy('title')
            ->get()
            ->map(function (CmsPage $page) use ($activeLocale, $defaultLocale): array {
                $translation = $page->translations->firstWhere('locale', $activeLocale);
                $localized = $translation
                    ? $this->localization->serializeTranslation($translation)
                    : [];

                return [
                    'id' => $page->id,
                    'title' => $localized['title'] ?? $page->title,
                    'slug' => $localized['slug'] ?? $page->slug,
                    'status' => $localized['translation_status'] ?? 'missing',
                    'translation_status' => $localized['translation_status'] ?? 'missing',
                    'excerpt' => $localized['excerpt'] ?? $page->excerpt,
                    'body' => $localized['body'] ?? $page->body,
                    'meta_title' => $localized['meta_title'] ?? $page->meta_title,
                    'meta_description' => $localized['meta_description'] ?? $page->meta_description,
                    'meta_keywords' => $localized['meta_keywords'] ?? $page->meta_keywords,
                    'template' => $page->template,
                    'featured_media_id' => $page->featured_media_id,
                    'featured_media_url' => $page->featuredMedia?->file_url,
                    'publish_at' => $page->publish_at?->toAtomString(),
                    'default_locale' => $defaultLocale,
                    'active_locale' => $activeLocale,
                    'public_url' => $localized['public_url'] ?? null,
                    'preview_url' => route('site.preview.pages', [
                        'locale' => $activeLocale,
                        'previewSegment' => FrontendLocalization::segment('preview', $activeLocale),
                        'pagesSegment' => FrontendLocalization::segment('pages', $activeLocale),
                        'page' => $page->id,
                    ]),
                    'translations' => $page->translations
                        ->map(fn (CmsPageTranslation $item): array => (
                            $this->localization->serializeTranslation($item)
                        ))
                        ->keyBy('locale')
                        ->all(),
                    'translation_summary' => $this->translationSummary($page),
                ];
            })
            ->values()
            ->all();

        $published = CmsPageTranslation::query()
            ->where('translation_status', TranslationStatus::Published->value)
            ->distinct('cms_page_id')
            ->count('cms_page_id');
        $draft = CmsPage::query()
            ->whereDoesntHave('translations', fn ($query) => (
                $query->where('translation_status', TranslationStatus::Published->value)
            ))
            ->count();

        return response()->json([
            'data' => [
                'items' => $pages,
                'total' => count($pages),
                'metrics' => [
                    'published' => $published,
                    'draft' => $draft,
                ],
                'locales' => collect($this->localeContext->options($websiteKey))
                    ->filter(fn (array $locale): bool => (bool) $locale['is_enabled_for_editing'])
                    ->values()
                    ->all(),
                'default_locale' => $defaultLocale,
                'active_locale' => $activeLocale,
                'workflow_statuses' => collect(TranslationStatus::cases())
                    ->map(fn (TranslationStatus $status): string => $status->value)
                    ->all(),
                'media' => CmsMedia::query()
                    ->latest()
                    ->get(['id', 'title', 'file_path', 'file_url'])
                    ->map(fn (CmsMedia $media): array => [
                        'id' => $media->id,
                        'title' => $media->title,
                        'file_url' => $media->file_url,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function translationSummary(CmsPage $page): array
    {
        return collect(TranslationStatus::cases())
            ->mapWithKeys(fn (TranslationStatus $status): array => [
                $status->value => $page->translations
                    ->filter(fn (CmsPageTranslation $translation): bool => (
                        $translation->translation_status === $status
                    ))
                    ->count(),
            ])
            ->all();
    }
}
