<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsFeaturedCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeaturedCategoryManagementController
{
    private const DEFAULT_LOCATIONS = [
        ['label' => 'Home featured categories', 'value' => 'home-featured-categories'],
        ['label' => 'Footer featured categories', 'value' => 'footer-featured-categories'],
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $group = CmsFeaturedCategory::query()->create($validated);

        return response()->json([
            'message' => 'Đã tạo danh mục nổi bật.',
            'data' => $this->serialize($group),
        ], 201);
    }

    public function update(Request $request, int $featuredCategory): JsonResponse
    {
        $group = CmsFeaturedCategory::query()->findOrFail($featuredCategory);
        $validated = $this->validatePayload($request);
        $group->update($validated);

        return response()->json([
            'message' => 'Đã cập nhật danh mục nổi bật.',
            'data' => $this->serialize($group->fresh()),
        ]);
    }

    public function destroy(int $featuredCategory): JsonResponse
    {
        $group = CmsFeaturedCategory::query()->findOrFail($featuredCategory);
        $group->delete();

        return response()->json(['message' => 'Đã xóa danh mục nổi bật.']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', Rule::in(collect(self::DEFAULT_LOCATIONS)->pluck('value')->all())],
            'website_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.url' => ['nullable', 'string', 'max:2000'],
            'items.*.target' => ['nullable', 'string', 'max:50'],
            'items.*.link_type' => ['nullable', 'string', 'max:50'],
            'items.*.link_value' => ['nullable', 'string', 'max:255'],
            'items.*.custom_url' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function serialize(CmsFeaturedCategory $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'location' => $group->location,
            'website_key' => $group->website_key,
            'items' => $group->items ?? [],
        ];
    }
}
