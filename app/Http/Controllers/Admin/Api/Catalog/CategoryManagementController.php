<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Core\Access\AdminDataScope;
use App\Models\CatalogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryManagementController
{
    public function store(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $record = CatalogCategory::query()->create($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã tạo danh mục catalog.',
            'data' => $this->serializeCategory($this->resolveScopedCategory($request, $adminDataScope, $record->id)),
        ], 201);
    }

    public function update(Request $request, AdminDataScope $adminDataScope, int $category): JsonResponse
    {
        $record = $this->resolveScopedCategory($request, $adminDataScope, $category);
        $validated = $this->validatePayload($request, $record);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $record->update($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã cập nhật danh mục catalog.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ]);
    }

    public function destroy(Request $request, AdminDataScope $adminDataScope, int $category): JsonResponse
    {
        $record = $this->resolveScopedCategory($request, $adminDataScope, $category);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa danh mục catalog.',
        ]);
    }

    private function resolveScopedCategory(Request $request, AdminDataScope $adminDataScope, int $categoryId): CatalogCategory
    {
        $query = CatalogCategory::query()->with('parent:id,name');

        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        return $query->findOrFail($categoryId);
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
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
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

    private function ensureScopedPayloadAllowed(Request $request, array $validated): void
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return;
        }

        $scopeMatrix = $admin->scopeMatrix();

        foreach (['website' => 'website_key', 'owner' => 'owner_key', 'tenant' => 'tenant_key'] as $scopeType => $field) {
            $allowedValues = array_values(array_filter($scopeMatrix[$scopeType] ?? []));

            if ($allowedValues === []) {
                continue;
            }

            $value = Arr::get($validated, $field);

            if (! is_string($value) || $value === '' || ! in_array($value, $allowedValues, true)) {
                throw ValidationException::withMessages([
                    $field => ['Giá trị scope nằm ngoài phạm vi admin được cấp.'],
                ]);
            }
        }
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
            'website_key' => $category->website_key,
            'owner_key' => $category->owner_key,
            'tenant_key' => $category->tenant_key,
        ];
    }
}
