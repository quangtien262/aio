<?php

namespace App\Support;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\SiteProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderConfirmationSender
{
    public function send(Order $order): void
    {
        $branding = SiteProfile::query()->first()?->branding ?? [];
        $adminNotificationEmail = (string) ($branding['support_email'] ?? config('mail.from.address', ''));
        $queuedEmail = false;

        if (filled($order->customer_email)) {
            try {
                Mail::to($order->customer_email)->queue((new OrderPlacedMail($order->loadMissing('items'), 'customer', $branding))->onQueue('mail')->afterCommit());
                $queuedEmail = true;
            } catch (Throwable $exception) {
                Log::warning('Failed to send order confirmation email', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (filled($adminNotificationEmail)) {
            try {
                Mail::to($adminNotificationEmail)->queue((new OrderPlacedMail($order->loadMissing('items'), 'admin', $branding))->onQueue('mail')->afterCommit());
                $queuedEmail = true;
            } catch (Throwable $exception) {
                Log::warning('Failed to queue admin order notification email', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($queuedEmail) {
            $order->forceFill(['email_queued_at' => now()])->save();
        }
    }
}
