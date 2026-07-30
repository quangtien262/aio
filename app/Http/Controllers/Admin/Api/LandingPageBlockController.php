<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\TranslationStatus;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Support\FrontendLocalization;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LandingPageLocalization;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LandingPageBlockController
{
    public function __construct(
        private readonly LandingPageLocalization $localization,
        private readonly SiteContext $siteContext,
    ) {}

    public function index(LandingPage $landingPage, Request $request, LandingPageBuilder $builder): JsonResponse
    {
        $this->assertAccessible($landingPage);
        $locale = $this->locale($request);
        $landingPage->load(['blocks.data']);

        return response()->json([
            'data' => $landingPage->blocks
                ->reject(fn (LandingPageBlock $block): bool => $block->block_type === 'footer_contact')
                ->map(fn (LandingPageBlock $block): array => $builder->serializeBlock(
                    $block,
                    $locale,
                    FrontendLocalization::fallbackLocale(),
                    false,
                    true,
                ))
                ->values(),
            'available_blocks' => $builder->availableBlocks($landingPage->theme_key),
        ]);
    }

    public function store(LandingPage $landingPage, Request $request, LandingPageBuilder $builder): JsonResponse
    {
        $this->assertAccessible($landingPage);
        $validated = $request->validate([
            'block_type' => ['required', 'string', 'max:100'],
        ]);

        $block = $builder->createBlock($landingPage->load('blocks'), $validated['block_type']);

        return response()->json([
            'message' => 'Đã thêm khối landing page.',
            'data' => $builder->serializeBlock(
                $block,
                $this->locale($request),
                FrontendLocalization::fallbackLocale(),
                false,
                true,
            ),
        ], 201);
    }

    public function update(LandingPageBlock $block, Request $request, LandingPageBuilder $builder): JsonResponse
    {
        $this->assertAccessible($block->landingPage()->firstOrFail());
        abort_if($block->block_type === 'footer_contact', 404);

        $locale = $this->locale($request);
        $validated = $request->validate([
            'is_visible' => ['sometimes', 'boolean'],
            'anchor_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'settings' => ['sometimes', 'array'],
            'media' => ['sometimes', 'array'],
            'data' => ['sometimes', 'array'],
            'data.title' => ['nullable', 'string', 'max:255'],
            'data.subtitle' => ['nullable', 'string', 'max:255'],
            'data.description' => ['nullable', 'string'],
            'data.button_label' => ['nullable', 'string', 'max:255'],
            'data.content' => ['nullable', 'array'],
            'publish' => ['nullable', 'boolean'],
            'is_machine_translated' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($block, $validated, $locale): void {
            $master = collect($validated)->only(['is_visible', 'anchor_id', 'settings', 'media'])->all();

            if ($master !== []) {
                $block->update($master);
            }

            if (array_key_exists('data', $validated)) {
                $translation = $this->localization->saveBlockDraft(
                    $block,
                    $locale,
                    $validated['data'],
                    (bool) ($validated['is_machine_translated'] ?? false),
                );

                if ((bool) ($validated['publish'] ?? false)) {
                    $translation = $this->localization->transitionBlock(
                        $block,
                        $locale,
                        TranslationStatus::Ready,
                    );
                    $this->localization->transitionBlock(
                        $block,
                        $locale,
                        TranslationStatus::Published,
                    );
                }
            }
        });

        return response()->json([
            'message' => 'Đã cập nhật khối landing page.',
            'data' => $builder->serializeBlock(
                $block->fresh(['data']),
                $locale,
                FrontendLocalization::fallbackLocale(),
                false,
                true,
            ),
        ]);
    }

    public function sourcePreview(LandingPageBlock $block, Request $request, LandingPageBuilder $builder): JsonResponse
    {
        $this->assertAccessible($block->landingPage()->firstOrFail());
        if ($request->has('featured_only')) {
            $request->merge(['featured_only' => $request->boolean('featured_only')]);
        }

        $validated = $request->validate([
            'locale' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'featured_only' => ['nullable', 'boolean'],
        ]);

        $settings = collect($validated)
            ->except('locale')
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        return response()->json([
            'data' => [
                'items' => $builder->previewDynamicItems($block, $this->locale($request), $settings),
                'settings' => $settings,
            ],
        ]);
    }

    public function transition(
        LandingPageBlock $block,
        string $locale,
        Request $request,
        LandingPageBuilder $builder,
    ): JsonResponse {
        $this->assertAccessible($block->landingPage()->firstOrFail());
        $validated = $request->validate([
            'translation_status' => [
                'required',
                'string',
                Rule::enum(TranslationStatus::class),
            ],
        ]);
        $translation = $this->localization->transitionBlock(
            $block,
            $locale,
            TranslationStatus::from($validated['translation_status']),
        );

        return response()->json([
            'message' => 'Đã chuyển trạng thái bản dịch block.',
            'data' => $builder->serializeBlock(
                $block->fresh(['data']),
                $locale,
                FrontendLocalization::fallbackLocale(),
                false,
                true,
            ),
            'translation_status' => $translation->translation_status->value,
        ]);
    }

    public function destroy(LandingPageBlock $block): JsonResponse
    {
        $this->assertAccessible($block->landingPage()->firstOrFail());
        abort_if($block->block_type === 'footer_contact', 404);

        $block->delete();

        return response()->json([
            'message' => 'Đã xóa khối landing page.',
        ]);
    }

    public function reorder(LandingPage $landingPage, Request $request): JsonResponse
    {
        $this->assertAccessible($landingPage);
        $validated = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*.id' => ['required', 'integer', Rule::exists('landing_page_blocks', 'id')],
            'blocks.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $allowedIds = $landingPage->blocks()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        DB::transaction(function () use ($validated, $allowedIds): void {
            foreach ($validated['blocks'] as $item) {
                $id = (int) $item['id'];

                if (! in_array($id, $allowedIds, true)) {
                    continue;
                }

                LandingPageBlock::query()
                    ->whereKey($id)
                    ->update(['sort_order' => (int) $item['sort_order']]);
            }
        });

        return response()->json([
            'message' => 'Đã sắp xếp lại khối landing page.',
        ]);
    }

    private function locale(Request $request): string
    {
        return FrontendLocalization::resolveEditableLocale(
            (string) $request->input('locale', app()->getLocale()),
        );
    }

    private function assertAccessible(LandingPage $landingPage): void
    {
        abort_unless(
            $landingPage->website_key === $this->siteContext->websiteKey()
            && strtoupper((string) $landingPage->theme_key) === strtoupper((string) $this->siteContext->themeKey()),
            404,
        );
    }
}
