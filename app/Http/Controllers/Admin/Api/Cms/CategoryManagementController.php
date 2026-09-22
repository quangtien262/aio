<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsCategory;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $category = CmsCategory::query()->create($validated);

        return response()->json(['message' => 'Đã tạo category CMS.', 'data' => $this->serialize($category)], 201);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        /** @var CmsCategory $record */
        $record = CmsCategory::query()->findOrFail($category);
        $validated = $this->validatePayload($request, $record);
        $record->update($validated);

        return response()->json(['message' => 'Đã cập nhật category CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function destroy(Request $request, int $category): JsonResponse
    {
        /** @var CmsCategory $record */
        $record = CmsCategory::query()->findOrFail($category);
        $record->delete();

        return response()->json(['message' => 'Đã xóa category CMS.']);
    }

    private function validatePayload(Request $request, ?CmsCategory $category = null): array
    {
        $websiteKey = $category?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cms_categories', 'slug')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048', function ($attribute, $value, $fail): void {
                $localPath = str_starts_with($value, '/') && ! str_starts_with($value, '//')
                    && ! str_contains($value, chr(92)) && ! preg_match('/\s/', $value);
                $remoteUrl = filter_var($value, FILTER_VALIDATE_URL)
                    && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
                if (! $localPath && ! $remoteUrl) {
                    $fail('Ảnh đại diện phải là URL http/https hoặc đường dẫn ảnh trên website.');
                }
            }],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('cms_categories', 'id')],
        ], [
            'slug.unique' => 'Slug đã được dùng cho danh mục khác. Vui lòng chọn slug khác.',
        ]);
    }

    private function serialize(CmsCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'parent_id' => $category->parent_id,
        ];
    }
}
