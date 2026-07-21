<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsSidePromo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SidePromoManagementController
{
    private const DEFAULT_LOCATIONS = [
        ['label' => 'Home hero side promos', 'value' => 'home-hero-side-promos'],
        ['label' => 'Home secondary side promos', 'value' => 'home-secondary-side-promos'],
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $group = CmsSidePromo::query()->create($validated);

        return response()->json([
            'message' => 'Đã tạo block side promo.',
            'data' => $this->serialize($group),
        ], 201);
    }

    public function update(Request $request, int $sidePromo): JsonResponse
    {
        $group = CmsSidePromo::query()->findOrFail($sidePromo);
        $validated = $this->validatePayload($request);
        $group->update($validated);

        return response()->json([
            'message' => 'Đã cập nhật block side promo.',
            'data' => $this->serialize($group->fresh()),
        ]);
    }

    public function destroy(int $sidePromo): JsonResponse
    {
        $group = CmsSidePromo::query()->findOrFail($sidePromo);
        $group->delete();

        return response()->json(['message' => 'Đã xóa block side promo.']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', Rule::in(collect(self::DEFAULT_LOCATIONS)->pluck('value')->all())],
            'website_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.badge' => ['nullable', 'string', 'max:80'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'items.*.cta_label' => ['nullable', 'string', 'max:80'],
            'items.*.image' => ['required', 'string', 'max:2000'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.url' => ['nullable', 'string', 'max:2000'],
            'items.*.target' => ['nullable', 'string', 'max:50'],
            'items.*.link_type' => ['nullable', 'string', 'max:50'],
            'items.*.link_value' => ['nullable', 'string', 'max:255'],
            'items.*.custom_url' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function serialize(CmsSidePromo $group): array
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
