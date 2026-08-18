<?php

namespace App\Support;

use App\Models\CatalogProduct;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;

class StorefrontCart
{
    private const SESSION_KEY = 'storefront_cart';

    public function __construct(
        private readonly Store $session,
        private readonly InventoryAvailabilityResolver $availability,
        private readonly SiteContext $siteContext,
    ) {}

    public function summary(): array
    {
        return $this->summarize($this->items());
    }

    public function add(CatalogProduct $product, int $quantity): array
    {
        $items = $this->items();
        $key = (string) $product->getKey();
        $existingQuantity = (int) ($items[$key]['quantity'] ?? 0);
        $stock = $this->availability->quantity($product, $this->siteContext->websiteKey());
        $this->assertInStock($stock);
        $maxQuantity = $this->resolveMaxQuantity($stock);
        $nextQuantity = min($existingQuantity + max(1, $quantity), $maxQuantity);

        $items[$key] = [
            'product_id' => $product->getKey(),
            'slug' => (string) ($product->slug ?? $product->getKey()),
            'sku' => $product->sku,
            'title' => $product->name,
            'price' => (float) $product->price,
            'old_price' => $product->original_price !== null ? (float) $product->original_price : null,
            'image' => $product->image_url,
            'quantity' => $nextQuantity,
            'stock' => $stock,
            'url' => FrontendRouteUrl::productPath((string) ($product->slug ?: $product->getKey())),
        ];

        $this->session->put(self::SESSION_KEY, $items);

        return $items[$key];
    }

    public function remove(int|string $productId): void
    {
        $items = $this->items();

        unset($items[(string) $productId]);

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function update(int|string $productId, int $quantity): ?array
    {
        $items = $this->items();
        $key = (string) $productId;

        if (! isset($items[$key])) {
            return null;
        }

        $product = $this->currentProduct($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'cart' => ['Sản phẩm không còn được bán trên website hiện tại.'],
            ]);
        }

        $stock = $this->availability->quantity($product, $this->siteContext->websiteKey());
        $this->assertInStock($stock);
        $quantity = max(1, min($quantity, $this->resolveMaxQuantity($stock)));
        $items[$key]['quantity'] = $quantity;
        $items[$key]['stock'] = $stock;

        $this->session->put(self::SESSION_KEY, $items);

        return $items[$key];
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function hasItems(): bool
    {
        return $this->items() !== [];
    }

    /**
     * Reload current products and stock immediately before an order is written.
     * This prevents a stale session snapshot from bypassing Inventory authority.
     *
     * @return array{items: list<array<string, mixed>>, count: int, unique_count: int, subtotal: float}
     */
    public function revalidatedSummary(): array
    {
        $items = $this->items();

        foreach ($items as $key => $item) {
            $product = $this->currentProduct($item['product_id'] ?? $key);

            if ($product === null) {
                $title = (string) ($item['title'] ?? 'Sản phẩm');

                throw ValidationException::withMessages([
                    'cart' => ["Sản phẩm {$title} không còn được bán trên website hiện tại."],
                ]);
            }

            $stock = $this->availability->quantity($product, $this->siteContext->websiteKey());
            $this->assertInStock($stock);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($stock !== null && $quantity > $stock) {
                throw ValidationException::withMessages([
                    'cart' => ["Sản phẩm {$product->name} chỉ còn {$stock} sản phẩm; vui lòng cập nhật giỏ hàng."],
                ]);
            }

            $items[$key] = [
                ...$item,
                'slug' => (string) ($product->slug ?? $product->getKey()),
                'sku' => $product->sku,
                'title' => $product->name,
                'price' => (float) $product->price,
                'old_price' => $product->original_price !== null ? (float) $product->original_price : null,
                'image' => $product->image_url,
                'stock' => $stock,
                'url' => FrontendRouteUrl::productPath((string) ($product->slug ?: $product->getKey())),
            ];
        }

        $this->session->put(self::SESSION_KEY, $items);

        return $this->summarize($items);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function items(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, count: int, unique_count: int, subtotal: float}
     */
    private function summarize(array $items): array
    {
        return [
            'items' => array_values($items),
            'count' => array_sum(array_map(fn (array $item): int => (int) ($item['quantity'] ?? 0), $items)),
            'unique_count' => count($items),
            'subtotal' => array_reduce($items, fn (float $carry, array $item): float => $carry + ((float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)), 0.0),
        ];
    }

    private function resolveMaxQuantity(?int $stock): int
    {
        if ($stock !== null) {
            return min(99, $stock);
        }

        return 99;
    }

    private function assertInStock(?int $stock): void
    {
        if ($stock !== null && $stock <= 0) {
            throw ValidationException::withMessages([
                'cart' => ['Sản phẩm hiện đã hết hàng.'],
            ]);
        }
    }

    private function currentProduct(int|string $productId): ?CatalogProduct
    {
        return CatalogProduct::query()
            ->forWebsite($this->siteContext->websiteKey())
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();
    }
}
