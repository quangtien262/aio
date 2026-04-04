<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderIndexController
{
    public function __invoke(): JsonResponse
    {
        $orders = Order::query()
            ->with('items')
            ->latest('placed_at')
            ->latest('id')
            ->get();

        $statusCounts = collect(['placed', 'pending', 'processing', 'completed', 'cancelled'])
            ->mapWithKeys(fn (string $status): array => [$status => $orders->where('status', $status)->count()])
            ->all();

        return response()->json([
            'data' => [
                'stats' => [
                    'total_orders' => $orders->count(),
                    'gross_revenue' => round((float) $orders->sum('subtotal'), 2),
                    'status_counts' => $statusCounts,
                ],
                'orders' => $orders->map(function (Order $order): array {
                    return [
                        'id' => $order->id,
                        'order_code' => $order->order_code,
                        'status' => $order->status,
                        'customer_name' => $order->customer_name,
                        'customer_phone' => $order->customer_phone,
                        'customer_email' => $order->customer_email,
                        'delivery_address' => $order->delivery_address,
                        'payment_label' => $order->payment_label,
                        'subtotal' => (float) $order->subtotal,
                        'item_count' => $order->item_count,
                        'placed_at' => $order->placed_at?->toIso8601String(),
                        'email_queued_at' => $order->email_queued_at?->toIso8601String(),
                        'email_sent_at' => $order->email_sent_at?->toIso8601String(),
                        'items' => $order->items->map(fn ($item): array => [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'line_total' => (float) $item->line_total,
                        ])->all(),
                    ];
                })->all(),
            ],
        ]);
    }
}