<?php

namespace App\Support;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Support\SmsSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderConfirmationSender
{
    public function __construct(
        private readonly SmsSender $smsSender,
    ) {
    }

    public function send(Order $order): void
    {
        if (filled($order->customer_email)) {
            try {
                Mail::to($order->customer_email)->send(new OrderPlacedMail($order->loadMissing('items')));
                $order->forceFill(['email_sent_at' => now()])->save();
            } catch (Throwable $exception) {
                Log::warning('Failed to send order confirmation email', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $message = sprintf(
                'Don %s da duoc ghi nhan. Tong tam tinh %s. Hotline 19006760 neu can ho tro.',
                $order->order_code,
                number_format((float) $order->subtotal, 0, ',', '.').'đ'
            );

            if ($this->smsSender->send($order->customer_phone, $message)) {
                $order->forceFill(['sms_sent_at' => now()])->save();
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to send order confirmation SMS', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
