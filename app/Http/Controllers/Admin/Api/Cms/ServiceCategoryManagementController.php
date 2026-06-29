<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceCategoryManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $record = CmsServiceCategory::query()->create(array_merge($this->normalizePayload($validated), [
            'slug' => 'pending-service-category-'.Str::uuid(),
        ]));
        $record->update(['slug' => $this->uniqueSlug($record->name, $record->id)]);

        return response()->json([
            'message' => 'Đã tạo danh mục dịch vụ.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ], 201);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $record = CmsServiceCategory::query()->with('parent:id,name')->findOrFail($category);
        $validated = $this->validatePayload($request, $record);

        $record->update($this->normalizePayload($validated, $record));

        return response()->json([
            'message' => 'Đã cập nhật danh mục dịch vụ.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ]);
    }

    public function destroy(int $category): JsonResponse
    {
        $record = CmsServiceCategory::query()->findOrFail($category);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa danh mục dịch vụ.',
        ]);
    }

    private function validatePayload(Request $request, ?CmsServiceCategory $category = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:cms_service_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_service_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizePayload(array $validated, ?CmsServiceCategory $category = null): array
    {
        $name = trim((string) ($validated['name'] ?? ''));

        return array_merge($validated, [
            'slug' => trim((string) ($validated['slug'] ?? '')) !== '' ? $validated['slug'] : ($category?->slug ?: Str::slug($name)),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
    }

    private function uniqueSlug(string $name, int $id): string
    {
        $baseSlug = Str::slug($name) ?: 'danh-muc-dich-vu-'.$id;
        $exists = CmsServiceCategory::query()
            ->where('slug', $baseSlug)
            ->whereKeyNot($id)
            ->exists();

        return $exists ? $baseSlug.'-'.$id : $baseSlug;
    }

    private function serializeCategory(CmsServiceCategory $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }
}
