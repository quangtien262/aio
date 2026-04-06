<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Models\CatalogProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $product = DB::transaction(function () use ($validated): CatalogProduct {
            $product = CatalogProduct::query()->create($this->normalizePayload($validated));
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
        return $request->validate([
            'catalog_category_id' => ['nullable', 'integer', 'exists:catalog_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('catalog_products', 'slug')->ignore($product?->id)],
            'sku' => ['required', 'string', 'max:255', Rule::unique('catalog_products', 'sku')->ignore($product?->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string'],
            'detail_content' => ['nullable', 'string'],
            'highlights' => ['nullable', 'string'],
            'usage_terms' => ['nullable', 'string'],
            'usage_location' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['nullable', 'url', 'max:2048'],
            'sold_count' => ['nullable', 'integer', 'min:0'],
            'deal_end_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
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
            'highlights' => $product->highlights,
            'usage_terms' => $product->usage_terms,
            'usage_location' => $product->usage_location,
            'image_url' => $product->image_url,
            'gallery_images' => $product->images->pluck('image_url')->all(),
            'sold_count' => $product->sold_count,
            'deal_end_at' => $product->deal_end_at?->toIso8601String(),
            'is_featured' => $product->is_featured,
            'sort_order' => $product->sort_order,
            'is_active' => $product->is_active,
        ];
    }

    private function normalizePayload(array $validated): array
    {
        $name = trim((string) ($validated['name'] ?? ''));

        return array_merge($validated, [
            'slug' => trim((string) ($validated['slug'] ?? '')) !== '' ? $validated['slug'] : Str::slug($name),
            'detail_content' => $this->normalizeTextBlock($validated['detail_content'] ?? null),
            'highlights' => $this->normalizeTextBlock($validated['highlights'] ?? null),
            'usage_terms' => $this->normalizeTextBlock($validated['usage_terms'] ?? null),
            'usage_location' => $this->normalizeTextBlock($validated['usage_location'] ?? null),
            'sold_count' => (int) ($validated['sold_count'] ?? 0),
            'deal_end_at' => $validated['deal_end_at'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
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
}
