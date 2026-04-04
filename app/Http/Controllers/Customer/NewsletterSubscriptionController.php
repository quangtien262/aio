<?php

namespace App\Http\Controllers\Customer;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterSubscriptionController
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $customer = $request->user('customer');
        $validated = $request->validate([
            'email' => [$customer ? 'nullable' : 'required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower((string) ($validated['email'] ?? $customer?->email ?? ''));

        abort_if($email === '', 422, 'Cần cung cấp email để đăng ký nhận bản tin.');

        NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            [
                'customer_id' => $customer?->id,
                'name' => $customer?->name,
                'phone' => $customer?->phone,
                'source' => 'theme-header',
                'subscribed_at' => now(),
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ],
            ],
        );

        $message = 'Đã đăng ký nhận bản tin thành công.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'email' => $email,
                ],
            ]);
        }

        return back()->with('cart_success', $message);
    }
}