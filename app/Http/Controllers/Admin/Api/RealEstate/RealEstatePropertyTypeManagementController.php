<?php

namespace App\Http\Controllers\Admin\Api\RealEstate;

use App\Models\RealEstatePropertyType;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RealEstatePropertyTypeManagementController
{
    public function store(Request $request): JsonResponse
    {
        $type = RealEstatePropertyType::query()->create($this->validated($request));

        return response()->json(['message' => 'Đã tạo loại hình bất động sản.', 'data' => $type], 201);
    }

    public function update(Request $request, RealEstatePropertyType $type): JsonResponse
    {
        $type->update($this->validated($request, $type));

        return response()->json(['message' => 'Đã cập nhật loại hình bất động sản.', 'data' => $type->fresh()]);
    }

    public function destroy(RealEstatePropertyType $type): JsonResponse
    {
        abort_if($type->listings()->exists(), 422, 'Loại hình đang có tin đăng, không thể xóa.');
        $type->delete();

        return response()->json(['message' => 'Đã xóa loại hình bất động sản.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?RealEstatePropertyType $type = null): array
    {
        $websiteKey = $type?->website_key ?: app(SiteContext::class)->websiteKey();
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:real_estate_property_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('real_estate_property_types')->where('website_key', $websiteKey)->ignore($type?->id)],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['slug'] = Str::slug(trim((string) ($data['slug'] ?? '')) ?: $data['name']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
