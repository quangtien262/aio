<?php

namespace App\Http\Controllers\Customer\Api;

use App\Models\CmsService;
use App\Models\CustomerFavorite;
use App\Models\CustomerServiceInterest;
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

        $addresses = $customer?->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get() ?? collect();

        $serviceInterests = CustomerServiceInterest::query()
            ->with(['service.featuredImage'])
            ->where('customer_id', $customer?->id)
            ->latest('id')
            ->get();

        $availableServices = CmsService::query()
            ->with('featuredImage')
            ->where('status', 'published')
            ->orderByDesc('is_highlight')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(12)
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
                    'id' => $customer?->id,
                    'name' => $customer?->name,
                    'email' => $customer?->email,
                    'phone' => $customer?->phone,
                    'created_at' => $customer?->created_at?->toIso8601String(),
                ],
                'stats' => [
                    'orders' => $orders->count(),
                    'favorites' => $favorites->count(),
                    'addresses' => $addresses->count(),
                    'service_interests' => $serviceInterests->count(),
                    'placed' => $orders->where('status', 'placed')->count(),
                    'pending' => $orders->where('status', 'pending')->count(),
                    'cancelled' => $orders->where('status', 'cancelled')->count(),
                    'total_spent' => (float) $orders->whereNotIn('status', ['cancelled'])->sum('subtotal'),
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
                'addresses' => $addresses->map(fn ($address): array => [
                    'id' => $address->id,
                    'receiver_name' => $address->receiver_name,
                    'phone' => $address->phone,
                    'email' => $address->email,
                    'province' => $address->province,
                    'district' => $address->district,
                    'ward' => $address->ward,
                    'address_line' => $address->address_line,
                    'note' => $address->note,
                    'is_default' => $address->is_default,
                ])->all(),
                'service_interests' => $serviceInterests->map(function (CustomerServiceInterest $interest): array {
                    $service = $interest->service;

                    return [
                        'id' => $interest->id,
                        'cms_service_id' => $interest->cms_service_id,
                        'title' => $interest->title,
                        'message' => $interest->message,
                        'status' => $interest->status,
                        'service_url' => $service?->slug ? route('site.services.show', ['slug' => $service->slug]) : null,
                        'image' => $service?->featuredImage?->image_url,
                        'created_at' => $interest->created_at?->toIso8601String(),
                    ];
                })->all(),
                'available_services' => $availableServices->map(fn (CmsService $service): array => [
                    'id' => $service->id,
                    'title' => $service->title,
                    'summary' => $service->summary,
                    'url' => $service->slug ? route('site.services.show', ['slug' => $service->slug]) : null,
                    'image' => $service->featuredImage?->image_url,
                ])->all(),
                'newsletter' => [
                    'is_subscribed' => $subscriber !== null,
                    'email' => $subscriber?->email,
                    'subscribed_at' => $subscriber?->subscribed_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
