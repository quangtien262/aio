<?php

namespace App\Support;

use App\Models\CatalogProduct;
use Illuminate\Session\Store;

class StorefrontCart
{
    private const SESSION_KEY = 'storefront_cart';

    public function __construct(
        private readonly Store $session,
    ) {
    }

    public function summary(): array
    {
        $items = $this->items();

        return [
            'items' => array_values($items),
            'count' => array_sum(array_map(fn (array $item): int => (int) ($item['quantity'] ?? 0), $items)),
            'unique_count' => count($items),
            'subtotal' => array_reduce($items, fn (float $carry, array $item): float => $carry + ((float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)), 0.0),
        ];
    }

    public function add(CatalogProduct $product, int $quantity): array
    {
        $items = $this->items();
        $key = (string) $product->getKey();
        $existingQuantity = (int) ($items[$key]['quantity'] ?? 0);
        $maxQuantity = $this->resolveMaxQuantity($product);
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
            'stock' => $product->stock !== null ? (int) $product->stock : null,
            'url' => '/san-pham/'.($product->slug ?: $product->getKey()),
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

        $quantity = max(1, min($quantity, $this->resolveStoredItemMaxQuantity($items[$key])));
        $items[$key]['quantity'] = $quantity;

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
     * @return array<string, array<string, mixed>>
     */
    private function items(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function resolveMaxQuantity(CatalogProduct $product): int
    {
        if ($product->stock !== null && (int) $product->stock > 0) {
            return min(99, (int) $product->stock);
        }

        return 99;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveStoredItemMaxQuantity(array $item): int
    {
        $stock = $item['stock'] ?? null;

        if ($stock !== null && (int) $stock > 0) {
            return min(99, (int) $stock);
        }

        return 99;
    }
}
