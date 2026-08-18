<?php

namespace App\Jobs\AccountingTax;

use App\Models\AcctEinvoiceTransmission;
use App\Support\AccountingTax\AccountingArtifactStore;
use App\Support\AccountingTax\AccountingArtifactValidator;
use App\Support\AccountingTax\Providers\EinvoiceTransmissionService;
use App\Support\AccountingTax\Providers\Exceptions\ProviderRequestException;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;
use App\Support\AccountingTax\Providers\ProviderExecutionGuard;
use App\Support\AccountingTax\Providers\ProviderFactory;
use App\Support\AccountingTax\Providers\ProviderResponseSanitizer;
use App\Support\AuditLogger;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessEinvoiceTransmission implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $transmissionId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function uniqueId(): string
    {
        return (string) $this->transmissionId;
    }

    public function handle(
        ProviderFactory $factory,
        ProviderExecutionGuard $guard,
        ProviderResponseSanitizer $sanitizer,
        EinvoiceTransmissionService $transmissions,
        AccountingArtifactStore $artifacts,
        AccountingArtifactValidator $artifactValidator,
        AuditLogger $audit,
    ): void {
        $guard->assertConnectorEnabled();

        $transmission = DB::transaction(function (): ?AcctEinvoiceTransmission {
            $row = AcctEinvoiceTransmission::query()->lockForUpdate()->find($this->transmissionId);

            if ($row === null || $row->status === 'succeeded') {
                return null;
            }

            if ($row->status === 'processing' && $row->updated_at?->isAfter(now()->subMinutes(5))) {
                return null;
            }

            $row->forceFill([
                'status' => 'processing',
                'attempt_count' => $row->attempt_count + 1,
                'last_error' => null,
            ])->save();

            return $row->fresh(['document', 'connection']);
        });

        if ($transmission === null) {
            return;
        }

        try {
            $client = $factory->outbound($transmission->connection);
            $result = $this->execute($client, $transmission);
            $this->complete($transmission, $result, $sanitizer, $artifacts, $artifactValidator, $audit);
        } catch (Throwable $exception) {
            if ($exception instanceof ProviderSafetyException) {
                $transmission->forceFill([
                    'status' => 'queued',
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'next_attempt_at' => now()->addMinutes(5),
                ])->save();
                $this->auditStatus($audit, $transmission, 'retry_deferred');

                return;
            }

            $uncertain = $this->isUncertain($exception, $transmission->operation);
            $permanent = $this->isPermanent($exception);
            $transmission->forceFill([
                'status' => $uncertain ? 'uncertain' : ($permanent ? 'rejected' : 'failed'),
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'next_attempt_at' => ($uncertain || $permanent)
                    ? null
                    : now()->addMinutes(min(60, 2 ** $transmission->attempt_count)),
            ])->save();
            $this->auditStatus($audit, $transmission, $transmission->status);

            if ($uncertain) {
                $transmissions->enqueueReconciliation($transmission);

                return;
            }

            if ($permanent) {
                return;
            }

            throw $exception;
        }
    }

    private function execute(object $client, AcctEinvoiceTransmission $transmission): array|string
    {
        $snapshot = $transmission->request_snapshot ?? [];
        $providerKey = (string) data_get($snapshot, 'provider_operation_key');

        return match ($transmission->operation) {
            'create_draft' => $client->createDraft((array) data_get($snapshot, 'payload', [])),
            'create_and_sign' => $client->createAndSign((array) data_get($snapshot, 'payload', [])),
            'sign_send' => $client->signAndSend(
                (string) $transmission->provider_document_id,
                (array) data_get($snapshot, 'options.signing', []),
            ),
            'sync_status' => $client->status($transmission->provider_document_id, $providerKey),
            'download_pdf' => $client->downloadPdf((string) $transmission->provider_document_id),
            'download_xml' => $client->downloadXml((string) $transmission->provider_document_id),
            default => throw new RuntimeException('Unsupported e-invoice transmission operation.'),
        };
    }

    private function complete(
        AcctEinvoiceTransmission $transmission,
        array|string $result,
        ProviderResponseSanitizer $sanitizer,
        AccountingArtifactStore $artifacts,
        AccountingArtifactValidator $artifactValidator,
        AuditLogger $audit,
    ): void {
        $attributes = [
            'status' => 'succeeded',
            'completed_at' => now(),
            'sent_at' => now(),
            'next_attempt_at' => null,
            'last_error' => null,
        ];

        if (is_string($result)) {
            $attributes += $this->storeArtifact($transmission, $result, $artifacts, $artifactValidator);
        } else {
            $providerDocumentId = $this->firstValue($result, [
                'hoadon68_id',
                'data.hoadon68_id',
                'data.0.hoadon68_id',
                'result.hoadon68_id',
                'id',
                'data.id',
            ]);
            $providerStatus = $this->firstValue($result, [
                'trang_thai',
                'data.trang_thai',
                'data.0.trang_thai',
                'status',
            ]);

            if (in_array($transmission->operation, ['create_draft', 'create_and_sign'], true)
                && $providerDocumentId === null) {
                throw new ProviderRequestException(
                    'Minvoice trả về thành công nhưng thiếu provider document ID; cần đối soát theo key_api.',
                    502,
                );
            }
            $attributes += [
                'provider_document_id' => $providerDocumentId ?: $transmission->provider_document_id,
                'provider_status' => $providerStatus,
                'legal_status' => $this->legalStatus($providerStatus),
                'response_snapshot' => $sanitizer->sanitize($result),
            ];
        }

        DB::transaction(function () use ($transmission, $attributes, $audit): void {
            $transmission->forceFill($attributes)->save();
            $transmission->connection->forceFill([
                'last_used_at' => now(),
                'healthy_at' => now(),
                'last_health_checked_at' => now(),
                'health_status' => 'healthy',
                'readiness_state' => $transmission->connection->environment === 'production'
                    && $transmission->connection->production_allowed_at !== null
                    ? 'production_allowed'
                    : 'healthy',
                'last_error' => null,
            ])->save();

            if (isset($attributes['legal_status']) && $attributes['legal_status'] !== 'pending') {
                $document = $transmission->document()->lockForUpdate()->firstOrFail();

                if ($document->legal_status !== $attributes['legal_status']) {
                    $document->forceFill([
                        'legal_status' => $attributes['legal_status'],
                        'version' => $document->version + 1,
                    ])->saveTrusted();
                }
            }

            $sourceTransmissionId = data_get($transmission->request_snapshot, 'source_transmission_id');

            if ($sourceTransmissionId && ! empty($attributes['provider_document_id'])) {
                AcctEinvoiceTransmission::query()
                    ->whereKey((int) $sourceTransmissionId)
                    ->where('status', 'uncertain')
                    ->update([
                        'status' => 'succeeded',
                        'provider_document_id' => $attributes['provider_document_id'],
                        'provider_status' => $attributes['provider_status'] ?? null,
                        'legal_status' => $attributes['legal_status'] ?? 'pending',
                        'response_snapshot' => $attributes['response_snapshot'] ?? null,
                        'completed_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
            }

            $this->auditStatus($audit, $transmission->fresh(), 'succeeded');
        });
    }

    private function storeArtifact(
        AcctEinvoiceTransmission $transmission,
        string $contents,
        AccountingArtifactStore $artifacts,
        AccountingArtifactValidator $artifactValidator,
    ): array {
        $extension = $transmission->operation === 'download_pdf' ? 'pdf' : 'xml';
        $artifactValidator->assertValid($extension, $contents);
        $checksum = hash('sha256', $contents);
        $path = sprintf(
            'outbound/%d/%d/transmission-%d-%s.%s',
            $transmission->document->organization_id,
            $transmission->document_id,
            $transmission->id,
            substr($checksum, 0, 16),
            $extension,
        );

        $artifact = $artifacts->existsWithChecksum($path, $checksum)
            ? ['checksum' => $checksum]
            : $artifacts->putAtomically($path, $contents);

        return [
            $extension.'_path' => $path,
            $extension.'_checksum' => $artifact['checksum'],
            'checksum' => $artifact['checksum'],
            'response_snapshot' => ['artifact' => $extension, 'size' => strlen($contents)],
        ];
    }

    private function firstValue(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function legalStatus(?string $providerStatus): string
    {
        return match ((string) $providerStatus) {
            '0' => 'waiting_approval',
            '1' => 'waiting_sign',
            '2' => 'signed',
            '3' => 'sent',
            '4', 'success', 'accepted' => 'accepted',
            '5', 'error', 'failed' => 'error',
            '6' => 'signing',
            default => 'pending',
        };
    }

    private function isUncertain(Throwable $exception, string $operation): bool
    {
        if (! in_array($operation, ['create_draft', 'create_and_sign', 'sign_send'], true)) {
            return false;
        }

        return $exception instanceof ConnectionException
            || ($exception instanceof ProviderRequestException
                && (in_array($exception->httpStatus, [408, 409, 425, 429], true)
                    || ($exception->httpStatus ?? 0) >= 500));
    }

    private function isPermanent(Throwable $exception): bool
    {
        if (! $exception instanceof ProviderRequestException || $exception->httpStatus === null) {
            return false;
        }

        return $exception->httpStatus >= 400
            && $exception->httpStatus < 500
            && ! in_array($exception->httpStatus, [408, 409, 425, 429], true);
    }

    private function auditStatus(
        AuditLogger $audit,
        AcctEinvoiceTransmission $transmission,
        string $status,
    ): void {
        $audit->record(
            'accounting.einvoice.transmission_'.$status,
            $transmission,
            null,
            [
                'document_id' => $transmission->document_id,
                'connection_id' => $transmission->connection_id,
                'provider' => $transmission->provider,
                'operation' => $transmission->operation,
                'status' => $transmission->status,
                'legal_status' => $transmission->legal_status,
                'attempt_count' => $transmission->attempt_count,
            ],
            moduleKey: 'minvoice-connector',
        );
    }
}
