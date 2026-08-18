<?php

namespace App\Jobs;

use App\Mail\AccountingDocumentMail;
use App\Models\AcctDocument;
use App\Models\AcctEmailDelivery;
use App\Models\AcctEmailDeliveryAttempt;
use App\Support\AccountingTax\AccountingArtifactStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendAccountingDocumentEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [30, 180, 600];

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(AccountingArtifactStore $artifacts): void
    {
        $claim = DB::transaction(function (): ?array {
            $delivery = AcctEmailDelivery::query()->lockForUpdate()->find($this->deliveryId);

            if (! $delivery || $delivery->status === 'sent') {
                return null;
            }

            if (! in_array($delivery->status, ['queued', 'retrying', 'failed'], true)) {
                return null;
            }

            $attemptNo = $delivery->attempt_count + 1;
            $attempt = AcctEmailDeliveryAttempt::query()->create([
                'delivery_id' => $delivery->id,
                'attempt_no' => $attemptNo,
                'status' => 'sending',
                'provider' => $delivery->provider ?: (string) config('mail.default'),
                'started_at' => now(),
            ]);

            $delivery->forceFill([
                'status' => 'sending',
                'attempt_count' => $attemptNo,
                'started_at' => now(),
                'completed_at' => null,
                'last_error' => null,
            ])->save();

            return ['delivery' => $delivery, 'attempt' => $attempt];
        });

        if ($claim === null) {
            return;
        }

        /** @var AcctEmailDelivery $delivery */
        $delivery = $claim['delivery'];
        /** @var AcctEmailDeliveryAttempt $attempt */
        $attempt = $claim['attempt'];

        try {
            foreach ($delivery->attachments ?? [] as $attachment) {
                $path = $attachment['path'] ?? null;
                $checksum = $attachment['checksum'] ?? null;

                if (($attachment['disk'] ?? null) !== AccountingArtifactStore::DISK
                    || ! is_string($path) || $path === ''
                    || ! is_string($checksum) || ! $artifacts->existsWithChecksum($path, $checksum)) {
                    throw new RuntimeException('File đính kèm bất biến không còn khớp checksum.');
                }
            }

            $sentMessage = Mail::to($delivery->recipient_email, $delivery->recipient_name)
                ->send(new AccountingDocumentMail($delivery));
            $providerMessageId = $sentMessage?->getMessageId();

            DB::transaction(function () use ($delivery, $attempt, $providerMessageId): void {
                AcctEmailDeliveryAttempt::query()->whereKey($attempt->id)->update([
                    'status' => 'sent',
                    'provider_message_id' => $providerMessageId,
                    'completed_at' => now(),
                ]);
                AcctEmailDelivery::query()->whereKey($delivery->id)->update([
                    'status' => 'sent',
                    'provider_message_id' => $providerMessageId,
                    'sent_at' => now(),
                    'completed_at' => now(),
                    'last_error' => null,
                ]);
                $this->updateDocumentMailStatus((int) $delivery->document_id, 'sent');
            });
        } catch (Throwable $exception) {
            DB::transaction(function () use ($delivery, $attempt, $exception): void {
                $message = Str::limit($exception->getMessage(), 4000, '');
                AcctEmailDeliveryAttempt::query()->whereKey($attempt->id)->update([
                    'status' => 'failed',
                    'error_class' => $exception::class,
                    'error_message' => $message,
                    'completed_at' => now(),
                ]);
                AcctEmailDelivery::query()->whereKey($delivery->id)->update([
                    'status' => 'retrying',
                    'last_error' => $message,
                    'completed_at' => now(),
                ]);
                $this->updateDocumentMailStatus((int) $delivery->document_id, 'failed');
            });

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $delivery = AcctEmailDelivery::query()->lockForUpdate()->find($this->deliveryId);

            if ($delivery === null) {
                return;
            }

            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => $exception ? Str::limit($exception->getMessage(), 4000, '') : 'Email job failed.',
                'completed_at' => now(),
            ])->save();
            $this->updateDocumentMailStatus((int) $delivery->document_id, 'failed');
        });
    }

    private function updateDocumentMailStatus(int $documentId, string $status): void
    {
        $document = AcctDocument::query()->lockForUpdate()->find($documentId);

        if ($document === null) {
            return;
        }

        $document->forceFill([
            'mail_status' => $status,
            'version' => $document->version + 1,
        ])->saveTrusted();
    }
}
