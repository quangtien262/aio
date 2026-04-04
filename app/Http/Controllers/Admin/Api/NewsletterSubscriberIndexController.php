<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;

class NewsletterSubscriberIndexController
{
    public function __invoke(): JsonResponse
    {
        $subscribers = NewsletterSubscriber::query()
            ->latest('subscribed_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'stats' => [
                    'total_subscribers' => $subscribers->count(),
                    'linked_customers' => $subscribers->whereNotNull('customer_id')->count(),
                ],
                'subscribers' => $subscribers->map(function (NewsletterSubscriber $subscriber): array {
                    return [
                        'id' => $subscriber->id,
                        'customer_id' => $subscriber->customer_id,
                        'email' => $subscriber->email,
                        'name' => $subscriber->name,
                        'phone' => $subscriber->phone,
                        'source' => $subscriber->source,
                        'subscribed_at' => $subscriber->subscribed_at?->toIso8601String(),
                    ];
                })->all(),
            ],
        ]);
    }
}