<?php

namespace App\Http\Controllers\Admin\Api\RealEstate;

use App\Models\RealEstateListing;
use App\Support\RealEstate\RealEstateListingPresenter;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RealEstateListingManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $listing = DB::transaction(function () use ($validated): RealEstateListing {
            $media = $validated['gallery_images'] ?? [];
            unset($validated['gallery_images']);
            $listing = RealEstateListing::query()->create($this->normalized($validated));
            $listing->update([
                'slug' => $this->uniqueSlug($validated['slug'] ?? $listing->title, $listing->id),
                'code' => trim((string) ($validated['code'] ?? '')) ?: 'BDS'.str_pad((string) $listing->id, 6, '0', STR_PAD_LEFT),
            ]);
            $this->syncMedia($listing, $media);

            return $listing;
        });

        return response()->json([
            'message' => 'Đã tạo tin bất động sản.',
            'data' => RealEstateListingPresenter::make($listing->fresh()),
        ], 201);
    }

    public function update(Request $request, RealEstateListing $listing): JsonResponse
    {
        $validated = $this->validatePayload($request, $listing);

        DB::transaction(function () use ($validated, $listing): void {
            $media = $validated['gallery_images'] ?? [];
            unset($validated['gallery_images']);
            $listing->update($this->normalized($validated));
            $listing->update(['slug' => $this->uniqueSlug($validated['slug'] ?? $listing->title, $listing->id)]);
            $this->syncMedia($listing, $media);
        });

        return response()->json([
            'message' => 'Đã cập nhật tin bất động sản.',
            'data' => RealEstateListingPresenter::make($listing->fresh()),
        ]);
    }

    public function destroy(RealEstateListing $listing): JsonResponse
    {
        $listing->delete();

        return response()->json(['message' => 'Đã xóa tin bất động sản.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?RealEstateListing $listing = null): array
    {
        $websiteKey = $listing?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'property_type_id' => ['required', 'integer', 'exists:real_estate_property_types,id'],
            'cms_project_id' => ['nullable', 'integer', 'exists:cms_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('real_estate_listings')->where('website_key', $websiteKey)->ignore($listing?->id)],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('real_estate_listings')->where('website_key', $websiteKey)->ignore($listing?->id)],
            'publication_status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'availability_status' => ['required', Rule::in(['available', 'reserved', 'sold', 'rented', 'unavailable'])],
            'transaction_type' => ['required', Rule::in(['sale', 'rent'])],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'province' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'ward' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'floors' => ['nullable', 'integer', 'min:0'],
            'land_area' => ['nullable', 'numeric', 'min:0'],
            'floor_area' => ['nullable', 'numeric', 'min:0'],
            'direction' => ['nullable', 'string', 'max:100'],
            'legal_status' => ['nullable', 'string', 'max:255'],
            'furnishing_status' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'virtual_tour_url' => ['nullable', 'url', 'max:2048'],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            'is_hot' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['required', 'url', 'max:2048'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalized(array $data): array
    {
        $title = trim((string) $data['title']);

        return array_merge($data, [
            'slug' => 'pending-'.Str::lower(Str::random(18)),
            'publication_status' => $data['publication_status'] ?? 'draft',
            'availability_status' => $data['availability_status'] ?? 'available',
            'currency' => $data['currency'] ?? 'VND',
            'price_unit' => $data['price_unit'] ?? ($data['transaction_type'] === 'rent' ? 'tháng' : 'tổng'),
            'meta_title' => trim((string) ($data['meta_title'] ?? '')) ?: $title,
            'meta_description' => trim((string) ($data['meta_description'] ?? '')) ?: ($data['summary'] ?? null),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_hot' => (bool) ($data['is_hot'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'published_at' => ($data['publication_status'] ?? 'draft') === 'published'
                ? ($data['published_at'] ?? now())
                : ($data['published_at'] ?? null),
        ]);
    }

    private function uniqueSlug(string $value, int $id): string
    {
        $base = Str::slug($value) ?: 'bat-dong-san-'.$id;
        $exists = RealEstateListing::query()->where('slug', $base)->whereKeyNot($id)->exists();

        return $exists ? $base.'-'.$id : $base;
    }

    /**
     * @param array<int, string> $urls
     */
    private function syncMedia(RealEstateListing $listing, array $urls): void
    {
        $listing->media()->delete();

        foreach (collect($urls)->map(fn ($url): string => trim((string) $url))->filter()->unique()->values() as $index => $url) {
            $listing->media()->create([
                'media_type' => 'image',
                'media_url' => $url,
                'alt_text' => $listing->title,
                'is_featured' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }
}
