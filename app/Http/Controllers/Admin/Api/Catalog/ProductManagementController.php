<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Core\Access\AdminDataScope;
use App\Models\CatalogProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductManagementController
{
    public function store(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $product = CatalogProduct::query()->create($validated);

        return response()->json([
            'message' => 'Đã tạo sản phẩm catalog.',
            'data' => $this->serializeProduct($this->resolveScopedProduct($request, $adminDataScope, $product->id)),
        ], 201);
    }

    public function update(Request $request, AdminDataScope $adminDataScope, int $product): JsonResponse
    {
        $record = $this->resolveScopedProduct($request, $adminDataScope, $product);
        $validated = $this->validatePayload($request, $record);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $record->update($validated);

        return response()->json([
            'message' => 'Đã cập nhật sản phẩm catalog.',
            'data' => $this->serializeProduct($record->fresh()),
        ]);
    }

    public function destroy(Request $request, AdminDataScope $adminDataScope, int $product): JsonResponse
    {
        $record = $this->resolveScopedProduct($request, $adminDataScope, $product);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa sản phẩm catalog.',
        ]);
    }

    private function resolveScopedProduct(Request $request, AdminDataScope $adminDataScope, int $productId): CatalogProduct
    {
        $query = CatalogProduct::query();

        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        return $query->findOrFail($productId);
    }

    private function validatePayload(Request $request, ?CatalogProduct $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('catalog_products', 'sku')->ignore($product?->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
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

    private function serializeProduct(CatalogProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'stock' => $product->stock,
            'website_key' => $product->website_key,
            'owner_key' => $product->owner_key,
            'tenant_key' => $product->tenant_key,
        ];
    }
}
