<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Support\FrontendLocalization;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LandingPageBlockController
{
    public function index(LandingPage $landingPage, Request $request, LandingPageBuilder $builder): JsonResponse
    {
        $locale = $this->locale($request);
        $landingPage->load(['blocks.data']);

        return response()->json([
            'data' => $landingPage->blocks
                ->map(fn (LandingPageBlock $block): array => $builder->serializeBlock($block, $locale))
                ->values(),
            'available_blocks' => $builder->availableBlocks($landingPage->theme_key),
        ]);
    }

    public function store(LandingPage $landingPage, Request $request, LandingPageBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'block_type' => ['required', 'string', 'max:100'],
        ]);

        $block = $builder->createBlock($landingPage->load('blocks'), $validated['block_type']);

        return response()->json([
            'message' => 'Đã thêm khối landing page.',
            'data' => $builder->serializeBlock($block, $this->locale($request)),
        ], 201);
    }

    public function update(LandingPageBlock $block, Request $request, LandingPageBuilder $builder): JsonResponse
    {
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
        ]);

        DB::transaction(function () use ($block, $validated, $locale): void {
            $master = collect($validated)->only(['is_visible', 'anchor_id', 'settings', 'media'])->all();

            if ($master !== []) {
                $block->update($master);
            }

            if (array_key_exists('data', $validated)) {
                LandingPageBlockData::query()->updateOrCreate(
                    ['landing_page_block_id' => $block->id, 'locale' => $locale],
                    [
                        'title' => $validated['data']['title'] ?? null,
                        'subtitle' => $validated['data']['subtitle'] ?? null,
                        'description' => $validated['data']['description'] ?? null,
                        'button_label' => $validated['data']['button_label'] ?? null,
                        'content' => json_encode($validated['data']['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
            }
        });

        return response()->json([
            'message' => 'Đã cập nhật khối landing page.',
            'data' => $builder->serializeBlock($block->fresh(['data']), $locale),
        ]);
    }

    public function sourcePreview(LandingPageBlock $block, Request $request, LandingPageBuilder $builder): JsonResponse
    {
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

    public function destroy(LandingPageBlock $block): JsonResponse
    {
        $block->delete();

        return response()->json([
            'message' => 'Đã xóa khối landing page.',
        ]);
    }

    public function reorder(LandingPage $landingPage, Request $request): JsonResponse
    {
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
        $locale = (string) $request->input('locale', app()->getLocale());

        return in_array($locale, FrontendLocalization::supportedLocales(), true)
            ? $locale
            : FrontendLocalization::defaultLocale();
    }
}
