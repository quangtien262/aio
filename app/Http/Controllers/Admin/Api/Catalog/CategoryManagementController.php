<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Models\CatalogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $record = CatalogCategory::query()->create($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã tạo danh mục catalog.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ], 201);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $record = CatalogCategory::query()->with('parent:id,name')->findOrFail($category);
        $validated = $this->validatePayload($request, $record);

        $record->update($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã cập nhật danh mục catalog.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ]);
    }

    public function destroy(int $category): JsonResponse
    {
        $record = CatalogCategory::query()->findOrFail($category);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa danh mục catalog.',
        ]);
    }

    private function validatePayload(Request $request, ?CatalogCategory $category = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:catalog_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('catalog_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizePayload(array $validated): array
    {
        $name = trim((string) ($validated['name'] ?? ''));

        return array_merge($validated, [
            'slug' => trim((string) ($validated['slug'] ?? '')) !== '' ? $validated['slug'] : Str::slug($name),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
    }

    private function serializeCategory(CatalogCategory $category): array
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
