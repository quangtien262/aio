<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xac nhan don hang '.$this->order->order_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-placed',
        );
    }
}
