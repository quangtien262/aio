<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $payload,
        public readonly array $branding = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lien he moi: '.($this->payload['subject'] ?? 'Yeu cau tu van'),
            replyTo: filled($this->payload['email'] ?? null)
                ? [[
                    'address' => (string) $this->payload['email'],
                    'name' => (string) ($this->payload['name'] ?? 'Khach hang'),
                ]]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-inquiry',
        );
    }
}
