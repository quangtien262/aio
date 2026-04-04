<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CmsMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuManagementController
{
    public function store(Request $request, CmsMenuLocationRegistry $locationRegistry): JsonResponse
    {
        $validated = $this->validatePayload($request, $locationRegistry);

        $menu = CmsMenu::query()->create($validated);

        return response()->json(['message' => 'Đã tạo menu CMS.', 'data' => $this->serialize($menu)], 201);
    }

    public function update(Request $request, CmsMenuLocationRegistry $locationRegistry, int $menu): JsonResponse
    {
        $record = CmsMenu::query()->findOrFail($menu);
        $validated = $this->validatePayload($request, $locationRegistry);
        $record->update($validated);

        return response()->json(['message' => 'Đã cập nhật menu CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function destroy(int $menu): JsonResponse
    {
        $record = CmsMenu::query()->findOrFail($menu);
        $record->delete();

        return response()->json(['message' => 'Đã xóa menu CMS.']);
    }

    private function validatePayload(Request $request, CmsMenuLocationRegistry $locationRegistry): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', Rule::in($locationRegistry->values())],
            'items' => ['required', 'array'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.url' => ['nullable', 'string', 'max:2000'],
            'items.*.target' => ['nullable', 'string', 'max:50'],
        ]);
    }

    private function serialize(CmsMenu $menu): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'location' => $menu->location,
            'items' => $menu->items ?? [],
        ];
    }
}
