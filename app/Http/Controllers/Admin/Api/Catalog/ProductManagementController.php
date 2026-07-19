<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Models\CatalogProduct;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $shouldGenerateDefaultSku = trim((string) ($validated['sku'] ?? '')) === '';

        $product = DB::transaction(function () use ($validated, $shouldGenerateDefaultSku): CatalogProduct {
            $product = CatalogProduct::query()->create($this->normalizePayload($validated));
            $product->update([
                'slug' => $this->uniqueSlug($product->name, $product->id),
            ]);

            if ($shouldGenerateDefaultSku) {
                $product->update([
                    'sku' => $this->defaultSku($product->id),
                ]);
            }

            $this->syncGalleryImages($product, $validated['gallery_images'] ?? []);

            return $product;
        });

        return response()->json([
            'message' => 'Đã tạo sản phẩm catalog.',
            'data' => $this->serializeProduct($product->fresh()),
        ], 201);
    }

    public function update(Request $request, int $product): JsonResponse
    {
        $record = CatalogProduct::query()->with('images')->findOrFail($product);
        $validated = $this->validatePayload($request, $record);

        DB::transaction(function () use ($record, $validated): void {
            $record->update($this->normalizePayload($validated));
            $record->update([
                'slug' => $this->uniqueSlug($record->name, $record->id),
            ]);
            $this->syncGalleryImages($record, $validated['gallery_images'] ?? []);
        });

        return response()->json([
            'message' => 'Đã cập nhật sản phẩm catalog.',
            'data' => $this->serializeProduct($record->fresh()),
        ]);
    }

    public function destroy(int $product): JsonResponse
    {
        $record = CatalogProduct::query()->findOrFail($product);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa sản phẩm catalog.',
        ]);
    }

    private function validatePayload(Request $request, ?CatalogProduct $product = null): array
    {
        $websiteKey = $product?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'catalog_category_id' => ['required', 'integer', 'exists:catalog_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('catalog_products', 'slug')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($product?->id),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('catalog_products', 'sku')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($product?->id),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string'],
            'detail_content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'highlights' => ['nullable', 'string'],
            'usage_terms' => ['nullable', 'string'],
            'usage_location' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['nullable', 'url', 'max:2048'],
            'sold_count' => ['nullable', 'integer', 'min:0'],
            'deal_end_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'is_highlight' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function serializeProduct(CatalogProduct $product): array
    {
        return [
            'id' => $product->id,
            'catalog_category_id' => $product->catalog_category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'original_price' => $product->original_price !== null ? (float) $product->original_price : null,
            'stock' => $product->stock,
            'short_description' => $product->short_description,
            'detail_content' => $product->detail_content,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'meta_keywords' => $product->meta_keywords,
            'highlights' => $product->highlights,
            'usage_terms' => $product->usage_terms,
            'usage_location' => $product->usage_location,
            'image_url' => $product->image_url,
            'gallery_images' => $product->images->pluck('image_url')->all(),
            'sold_count' => $product->sold_count,
            'deal_end_at' => $product->deal_end_at?->toIso8601String(),
            'is_featured' => $product->is_featured,
            'is_highlight' => $product->is_highlight,
            'sort_order' => $product->sort_order,
            'is_active' => $product->is_active,
        ];
    }

    private function normalizePayload(array $validated): array
    {
        $name = trim((string) ($validated['name'] ?? ''));
        $sku = trim((string) ($validated['sku'] ?? ''));
        $shortDescription = $this->normalizeTextBlock($validated['short_description'] ?? null);

        $highlighted = (bool) ($validated['is_highlight'] ?? $validated['is_featured'] ?? false);

        return array_merge($validated, [
            'slug' => 'pending-product-'.Str::lower(Str::random(16)),
            'sku' => $sku !== '' ? $sku : 'TMP-'.Str::upper(Str::random(16)),
            'short_description' => $shortDescription,
            'meta_title' => $this->normalizeTextBlock($validated['meta_title'] ?? null) ?: $name,
            'meta_description' => $this->normalizeTextBlock($validated['meta_description'] ?? null) ?: $shortDescription,
            'meta_keywords' => $this->normalizeTextBlock($validated['meta_keywords'] ?? null),
            'detail_content' => $this->normalizeTextBlock($validated['detail_content'] ?? null),
            'highlights' => $this->normalizeTextBlock($validated['highlights'] ?? null),
            'usage_terms' => $this->normalizeTextBlock($validated['usage_terms'] ?? null),
            'usage_location' => $this->normalizeTextBlock($validated['usage_location'] ?? null),
            'sold_count' => (int) ($validated['sold_count'] ?? 0),
            'deal_end_at' => $validated['deal_end_at'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_featured' => $highlighted,
            'is_highlight' => $highlighted,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
    }

    private function syncGalleryImages(CatalogProduct $product, array $galleryImages): void
    {
        $product->images()->delete();

        $normalizedImages = collect($galleryImages)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->values();

        foreach ($normalizedImages as $index => $imageUrl) {
            $product->images()->create([
                'image_url' => $imageUrl,
                'sort_order' => $index,
            ]);
        }
    }

    private function normalizeTextBlock(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : preg_replace("/\r\n?|\n/", PHP_EOL, $text);
    }

    private function defaultSku(int $id): string
    {
        return 'PRO'.$id;
    }

    private function uniqueSlug(string $name, int $id): string
    {
        $baseSlug = Str::slug($name) ?: 'san-pham-'.$id;
        $exists = CatalogProduct::query()
            ->where('website_key', app(SiteContext::class)->websiteKey())
            ->where('slug', $baseSlug)
            ->whereKeyNot($id)
            ->exists();

        return $exists ? "{$baseSlug}-{$id}" : $baseSlug;
    }
}
