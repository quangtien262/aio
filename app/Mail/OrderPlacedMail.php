<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $audience = 'customer',
        public readonly array $branding = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->audience === 'admin'
                ? 'Thong bao don hang moi '.$this->order->order_code
                : 'Xac nhan don hang '.$this->order->order_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-placed',
        );
    }
}
