<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Http\Controllers\Admin\Api\Cms\Concerns\InteractsWithScopedCmsRecords;
use App\Models\CmsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryManagementController
{
    use InteractsWithScopedCmsRecords;

    public function store(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $category = CmsCategory::query()->create($validated);

        return response()->json(['message' => 'Đã tạo category CMS.', 'data' => $this->serialize($category)], 201);
    }

    public function update(Request $request, AdminDataScope $adminDataScope, int $category): JsonResponse
    {
        /** @var CmsCategory $record */
        $record = $this->resolveScopedRecord($request, $adminDataScope, new CmsCategory(), $category);
        $validated = $this->validatePayload($request, $record);
        $this->ensureScopedPayloadAllowed($request, $validated);
        $record->update($validated);

        return response()->json(['message' => 'Đã cập nhật category CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function destroy(Request $request, AdminDataScope $adminDataScope, int $category): JsonResponse
    {
        /** @var CmsCategory $record */
        $record = $this->resolveScopedRecord($request, $adminDataScope, new CmsCategory(), $category);
        $record->delete();

        return response()->json(['message' => 'Đã xóa category CMS.']);
    }

    private function validatePayload(Request $request, ?CmsCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('cms_categories', 'id')],
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function serialize(CmsCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'parent_id' => $category->parent_id,
            'website_key' => $category->website_key,
            'owner_key' => $category->owner_key,
            'tenant_key' => $category->tenant_key,
        ];
    }
}
