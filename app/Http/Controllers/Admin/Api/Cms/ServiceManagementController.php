<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsService;
use App\Models\CmsServiceImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $service = DB::transaction(function () use ($validated): CmsService {
            $images = $validated['images'] ?? [];
            unset($validated['images']);

            /** @var CmsService $service */
            $service = CmsService::query()->create($validated);
            $this->syncImages($service, $images);

            return $service->load(['category', 'images.media']);
        });

        return response()->json(['message' => 'Đã tạo dịch vụ CMS.', 'data' => $this->serialize($service)], 201);
    }

    public function update(Request $request, int $service): JsonResponse
    {
        /** @var CmsService $record */
        $record = CmsService::query()->findOrFail($service);
        $validated = $this->validatePayload($request, $record);

        $record = DB::transaction(function () use ($record, $validated): CmsService {
            $images = $validated['images'] ?? [];
            unset($validated['images']);

            $record->update($validated);
            $this->syncImages($record, $images);

            return $record->fresh(['category', 'images.media']);
        });

        return response()->json(['message' => 'Đã cập nhật dịch vụ CMS.', 'data' => $this->serialize($record)]);
    }

    public function destroy(Request $request, int $service): JsonResponse
    {
        /** @var CmsService $record */
        $record = CmsService::query()->findOrFail($service);
        $record->delete();

        return response()->json(['message' => 'Đã xóa dịch vụ CMS.']);
    }

    private function validatePayload(Request $request, ?CmsService $service = null): array
    {
        return $request->validate([
            'cms_service_category_id' => ['nullable', 'integer', Rule::exists('cms_service_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_services', 'slug')->ignore($service?->id)],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'publish_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'is_highlight' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*.cms_media_id' => ['nullable', 'integer', Rule::exists('cms_media', 'id')],
            'images.*.image_url' => ['required_with:images', 'string', 'max:2048'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.caption' => ['nullable', 'string', 'max:255'],
            'images.*.is_featured' => ['boolean'],
            'images.*.sort_order' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $images
     */
    private function syncImages(CmsService $service, array $images): void
    {
        $normalizedImages = collect($images)
            ->filter(fn (array $image): bool => filled($image['image_url'] ?? null))
            ->values()
            ->map(function (array $image, int $index): array {
                return [
                    'cms_media_id' => $image['cms_media_id'] ?? null,
                    'image_url' => $image['image_url'],
                    'alt_text' => $image['alt_text'] ?? null,
                    'caption' => $image['caption'] ?? null,
                    'is_featured' => (bool) ($image['is_featured'] ?? false),
                    'sort_order' => (int) ($image['sort_order'] ?? $index),
                ];
            });

        if ($normalizedImages->isNotEmpty() && $normalizedImages->where('is_featured', true)->isEmpty()) {
            $first = $normalizedImages->shift();
            $first['is_featured'] = true;
            $normalizedImages->prepend($first);
        }

        $hasFeatured = false;
        $normalizedImages = $normalizedImages->map(function (array $image) use (&$hasFeatured): array {
            if ($image['is_featured'] && ! $hasFeatured) {
                $hasFeatured = true;

                return $image;
            }

            $image['is_featured'] = false;

            return $image;
        });

        $service->images()->delete();
        $normalizedImages->each(fn (array $image): CmsServiceImage => $service->images()->create($image));
    }

    private function serialize(CmsService $service): array
    {
        $images = $service->images->map(fn (CmsServiceImage $image): array => [
            'id' => $image->id,
            'cms_media_id' => $image->cms_media_id,
            'image_url' => $image->media?->file_url ?: $image->image_url,
            'alt_text' => $image->alt_text,
            'caption' => $image->caption,
            'is_featured' => $image->is_featured,
            'sort_order' => $image->sort_order,
        ])->values()->all();
        $featuredImage = collect($images)->firstWhere('is_featured', true) ?? ($images[0] ?? null);

        return [
            'id' => $service->id,
            'cms_service_category_id' => $service->cms_service_category_id,
            'category_name' => $service->category?->name,
            'title' => $service->title,
            'slug' => $service->slug,
            'status' => $service->status,
            'summary' => $service->summary,
            'content' => $service->content,
            'icon' => $service->icon,
            'button_label' => $service->button_label,
            'link_url' => $service->link_url,
            'meta_title' => $service->meta_title,
            'meta_description' => $service->meta_description,
            'meta_keywords' => $service->meta_keywords,
            'publish_at' => $service->publish_at?->toAtomString(),
            'is_featured' => $service->is_featured,
            'is_highlight' => $service->is_highlight,
            'sort_order' => $service->sort_order,
            'website_key' => $service->website_key,
            'owner_key' => $service->owner_key,
            'tenant_key' => $service->tenant_key,
            'featured_image_url' => $featuredImage['image_url'] ?? null,
            'featured_image_alt' => $featuredImage['alt_text'] ?? null,
            'images' => $images,
        ];
    }
}
