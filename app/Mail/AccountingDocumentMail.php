<?php

namespace App\Mail;

use App\Models\AcctEmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountingDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly AcctEmailDelivery $delivery) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) $this->delivery->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.accounting-document',
            with: ['snapshot' => $this->delivery->payload_snapshot ?? []],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return collect($this->delivery->attachments ?? [])
            ->filter(fn (array $attachment): bool => isset($attachment['disk'], $attachment['path'], $attachment['name']))
            ->map(function (array $attachment): Attachment {
                $file = Attachment::fromStorageDisk($attachment['disk'], $attachment['path'])
                    ->as($attachment['name']);

                return isset($attachment['mime_type']) ? $file->withMime($attachment['mime_type']) : $file;
            })
            ->values()
            ->all();
    }
}
