<?php

namespace App\Http\Controllers\Customer\Api;

use App\Models\CustomerFavorite;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountOverviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $orders = Order::query()
            ->with('items')
            ->where('customer_id', $customer?->id)
            ->latest('placed_at')
            ->latest('id')
            ->get();

        $favorites = CustomerFavorite::query()
            ->with(['product.images'])
            ->where('customer_id', $customer?->id)
            ->latest('id')
            ->get();

        $subscriber = NewsletterSubscriber::query()
            ->where(function ($query) use ($customer): void {
                $query->where('customer_id', $customer?->id);

                if (filled($customer?->email)) {
                    $query->orWhere('email', $customer?->email);
                }
            })
            ->latest('subscribed_at')
            ->first();

        return response()->json([
            'data' => [
                'customer' => [
                    'name' => $customer?->name,
                    'email' => $customer?->email,
                    'phone' => $customer?->phone,
                ],
                'stats' => [
                    'orders' => $orders->count(),
                    'favorites' => $favorites->count(),
                    'placed' => $orders->where('status', 'placed')->count(),
                    'pending' => $orders->where('status', 'pending')->count(),
                    'cancelled' => $orders->where('status', 'cancelled')->count(),
                ],
                'orders' => $orders->map(function (Order $order): array {
                    return [
                        'id' => $order->id,
                        'order_code' => $order->order_code,
                        'status' => $order->status,
                        'payment_label' => $order->payment_label,
                        'subtotal' => (float) $order->subtotal,
                        'item_count' => $order->item_count,
                        'placed_at' => $order->placed_at?->toIso8601String(),
                        'delivery_address' => $order->delivery_address,
                        'items' => $order->items->map(fn ($item): array => [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'line_total' => (float) $item->line_total,
                        ])->all(),
                    ];
                })->all(),
                'favorites' => $favorites->map(function (CustomerFavorite $favorite): array {
                    $product = $favorite->product;
                    $imageUrl = $product?->images?->sortBy('sort_order')->first()?->image_url
                        ?? 'https://picsum.photos/seed/customer-favorite/640/420';

                    return [
                        'id' => $favorite->id,
                        'product_id' => $product?->id,
                        'title' => $product?->name,
                        'slug' => $product?->slug,
                        'price' => $product?->price !== null ? (float) $product->price : null,
                        'image' => $imageUrl,
                        'url' => $product?->slug ? route('site.catalog.product', ['slug' => $product->slug]) : null,
                        'created_at' => $favorite->created_at?->toIso8601String(),
                    ];
                })->filter(fn (array $favorite): bool => filled($favorite['title']))->values()->all(),
                'newsletter' => [
                    'is_subscribed' => $subscriber !== null,
                    'email' => $subscriber?->email,
                    'subscribed_at' => $subscriber?->subscribed_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}