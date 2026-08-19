<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Enums\TranslationStatus;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Support\FrontendLocalization;
use App\Support\Localization\CmsPageLocalization;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedSlugGenerator;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PageManagementController
{
    public function __construct(
        private readonly CmsPageLocalization $localization,
        private readonly LocaleContext $localeContext,
        private readonly LocalizedSlugGenerator $slugs,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $websiteKey = app(SiteContext::class)->websiteKey();
        $locale = $this->localeContext->resolveEditable(
            (string) ($validated['locale'] ?? ''),
            $websiteKey,
        );
        $validated['slug'] = $this->slugs->unique(
            $this->slugs->normalize(
                $validated['slug'] ?? $validated['title'],
                $locale,
            ),
            fn (string $candidate): bool => CmsPage::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->where('slug', $candidate)
                ->exists(),
        );

        $page = DB::transaction(function () use ($validated, $websiteKey, $locale): CmsPage {
            $page = CmsPage::withoutEvents(fn (): CmsPage => CmsPage::query()->create([
                'website_key' => $websiteKey,
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'status' => 'draft',
                'excerpt' => $validated['excerpt'] ?? null,
                'body' => $validated['body'] ?? null,
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'meta_keywords' => $validated['meta_keywords'] ?? null,
                'template' => $validated['template'] ?? null,
                'featured_media_id' => $validated['featured_media_id'] ?? null,
                'publish_at' => null,
            ]));

            $translation = $this->localization->saveDraft(
                $page,
                $locale,
                $this->translationPayload($validated),
                (bool) ($validated['is_machine_translated'] ?? false),
            );
            $this->applyRequestedStatus(
                $page,
                $translation,
                $validated['translation_status'] ?? $validated['status'] ?? null,
            );

            return $page->fresh(['featuredMedia', 'translations']);
        });

        return response()->json([
            'message' => 'Đã tạo Page đa ngôn ngữ.',
            'data' => $this->serializePage($page),
        ], 201);
    }

    public function update(Request $request, int $page): JsonResponse
    {
        $record = CmsPage::query()->with('translations')->findOrFail($page);
        $validated = $this->validatePayload($request, $record);
        $locale = $this->localeContext->resolveEditable(
            (string) ($validated['locale'] ?? ''),
            $record->website_key,
        );

        DB::transaction(function () use ($record, $validated, $locale): void {
            CmsPage::withoutEvents(fn (): bool => $record->update([
                'template' => $validated['template'] ?? $record->template,
                'featured_media_id' => array_key_exists('featured_media_id', $validated)
                    ? $validated['featured_media_id']
                    : $record->featured_media_id,
            ]));

            $translation = $this->localization->saveDraft(
                $record,
                $locale,
                $this->translationPayload($validated),
                (bool) ($validated['is_machine_translated'] ?? false),
            );
            $this->applyRequestedStatus(
                $record,
                $translation,
                $validated['translation_status'] ?? $validated['status'] ?? null,
            );
        });

        return response()->json([
            'message' => 'Đã cập nhật bản dịch Page.',
            'data' => $this->serializePage($record->fresh(['featuredMedia', 'translations'])),
        ]);
    }

    public function transition(
        Request $request,
        int $page,
        string $locale,
    ): JsonResponse {
        $record = CmsPage::query()->with('translations')->findOrFail($page);
        $validated = $request->validate([
            'translation_status' => [
                'required',
                'string',
                Rule::enum(TranslationStatus::class),
            ],
        ]);
        $translation = $this->localization->transition(
            $record,
            $locale,
            TranslationStatus::from($validated['translation_status']),
        );

        return response()->json([
            'message' => 'Đã chuyển trạng thái bản dịch Page.',
            'data' => [
                'page' => $this->serializePage(
                    $record->fresh(['featuredMedia', 'translations']),
                ),
                'translation' => $this->localization->serializeTranslation($translation),
            ],
        ]);
    }

    public function destroy(Request $request, int $page): JsonResponse
    {
        CmsPage::query()->findOrFail($page)->delete();

        return response()->json([
            'message' => 'Đã xóa Page và toàn bộ bản dịch liên quan.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $deleted = DB::transaction(
            fn (): int => CmsPage::query()->whereKey($ids)->delete(),
        );

        return response()->json([
            'message' => sprintf('Đã xóa %d Page.', $deleted),
            'data' => ['deleted' => $deleted],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'locale' => ['nullable', 'string', 'max:35'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'translation_status' => [
                'nullable',
                'string',
                Rule::enum(TranslationStatus::class),
            ],
            'is_machine_translated' => ['nullable', 'boolean'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'template' => ['nullable', 'string', 'max:255'],
            'featured_media_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_media', 'id')->where(
                    fn ($query) => $query->where(
                        'website_key',
                        $page?->website_key ?: app(SiteContext::class)->websiteKey(),
                    ),
                ),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function translationPayload(array $validated): array
    {
        return collect(CmsPageLocalization::TRANSLATABLE_FIELDS)
            ->mapWithKeys(fn (string $field): array => [
                $field => $validated[$field] ?? null,
            ])
            ->all();
    }

    private function applyRequestedStatus(
        CmsPage $page,
        CmsPageTranslation $translation,
        mixed $requested,
    ): void {
        $target = match ((string) $requested) {
            TranslationStatus::InReview->value => TranslationStatus::InReview,
            TranslationStatus::Ready->value => TranslationStatus::Ready,
            TranslationStatus::Published->value, 'published' => TranslationStatus::Published,
            default => null,
        };

        if ($target === null || $target === TranslationStatus::Draft) {
            return;
        }

        if ($target === TranslationStatus::Published) {
            $translation = $this->localization->transition(
                $page,
                $translation->locale,
                TranslationStatus::Ready,
            );
        }

        $this->localization->transition($page, $translation->locale, $target);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePage(CmsPage $page): array
    {
        $defaultLocale = $this->localeContext->defaultLocale($page->website_key);
        $translation = $page->translations->firstWhere('locale', $defaultLocale)
            ?? $page->translations->firstWhere(
                'locale',
                $this->localeContext->sourceLocale(),
            )
            ?? $page->translations->first();
        $serializedTranslation = $translation
            ? $this->localization->serializeTranslation($translation)
            : [];

        return [
            'id' => $page->id,
            'title' => $serializedTranslation['title'] ?? $page->title,
            'slug' => $serializedTranslation['slug'] ?? $page->slug,
            'status' => $serializedTranslation['translation_status'] ?? $page->status,
            'translation_status' => $serializedTranslation['translation_status'] ?? 'missing',
            'excerpt' => $serializedTranslation['excerpt'] ?? $page->excerpt,
            'body' => $serializedTranslation['body'] ?? $page->body,
            'meta_title' => $serializedTranslation['meta_title'] ?? $page->meta_title,
            'meta_description' => $serializedTranslation['meta_description'] ?? $page->meta_description,
            'meta_keywords' => $serializedTranslation['meta_keywords'] ?? $page->meta_keywords,
            'template' => $page->template,
            'featured_media_id' => $page->featured_media_id,
            'featured_media_url' => $page->featuredMedia?->file_url,
            'publish_at' => $page->publish_at?->toAtomString(),
            'default_locale' => $defaultLocale,
            'translations' => $page->translations
                ->map(fn (CmsPageTranslation $item): array => (
                    $this->localization->serializeTranslation($item)
                ))
                ->keyBy('locale')
                ->all(),
            'preview_url' => route('site.preview.pages', [
                'locale' => $defaultLocale,
                'previewSegment' => FrontendLocalization::segment('preview', $defaultLocale),
                'pagesSegment' => FrontendLocalization::segment('pages', $defaultLocale),
                'page' => $page->id,
            ]),
        ];
    }
}
