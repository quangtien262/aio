<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsProjectCategory;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectCategoryManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $record = CmsProjectCategory::query()->create(array_merge($this->normalizePayload($validated), [
            'slug' => 'pending-project-category-'.Str::uuid(),
        ]));
        $record->update(['slug' => $this->uniqueSlug($record->name, $record->id)]);

        return response()->json([
            'message' => 'Da tao danh muc du an.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ], 201);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $record = CmsProjectCategory::query()->with('parent:id,name')->findOrFail($category);
        $validated = $this->validatePayload($request, $record);

        $record->update($this->normalizePayload($validated, $record));

        return response()->json([
            'message' => 'Da cap nhat danh muc du an.',
            'data' => $this->serializeCategory($record->fresh(['parent'])),
        ]);
    }

    public function destroy(int $category): JsonResponse
    {
        $record = CmsProjectCategory::query()->findOrFail($category);
        $record->delete();

        return response()->json([
            'message' => 'Da xoa danh muc du an.',
        ]);
    }

    private function validatePayload(Request $request, ?CmsProjectCategory $category = null): array
    {
        $websiteKey = $category?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:cms_project_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('cms_project_categories', 'slug')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizePayload(array $validated, ?CmsProjectCategory $category = null): array
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
        $baseSlug = Str::slug($name) ?: 'danh-muc-du-an-'.$id;
        $exists = CmsProjectCategory::query()
            ->where('slug', $baseSlug)
            ->whereKeyNot($id)
            ->exists();

        return $exists ? $baseSlug.'-'.$id : $baseSlug;
    }

    private function serializeCategory(CmsProjectCategory $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'image_url' => $category->image_url,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }
}
