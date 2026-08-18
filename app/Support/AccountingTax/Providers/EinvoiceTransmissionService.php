<?php

namespace App\Support\AccountingTax\Providers;

use App\Jobs\AccountingTax\ProcessEinvoiceTransmission;
use App\Models\AcctDocument;
use App\Models\AcctEinvoiceTransmission;
use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\Minvoice\MinvoicePayloadBuilder;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EinvoiceTransmissionService
{
    public function __construct(
        private readonly MinvoicePayloadBuilder $payloadBuilder,
        private readonly ProviderExecutionGuard $guard,
        private readonly ProviderConnectionPolicy $policy,
        private readonly AuditLogger $audit,
    ) {}

    public function preview(AcctDocument $document, AcctProviderConnection $connection, ?string $series = null): array
    {
        $this->assertConnectionMatches($document, $connection);

        return $this->payloadBuilder->preview($document, $connection, $series);
    }

    public function enqueue(
        AcctDocument $document,
        AcctProviderConnection $connection,
        string $operation,
        array $options = [],
    ): AcctEinvoiceTransmission {
        $this->guard->assertConnectorEnabled();
        $this->assertConnectionMatches($document, $connection);

        if (! in_array($operation, ['create_draft', 'create_and_sign', 'sign_send', 'sync_status', 'download_pdf', 'download_xml'], true)) {
            throw ValidationException::withMessages(['operation' => ['Thao tác hóa đơn điện tử không hợp lệ.']]);
        }

        $this->policy->assertNetworkCallAllowed(
            $connection,
            mutation: in_array($operation, ['create_draft', 'create_and_sign', 'sign_send'], true),
        );

        $providerDocumentId = $this->latestProviderDocumentId($document, $connection);
        $providerKey = $this->payloadBuilder->operationKey($document, $connection);

        if (in_array($operation, ['sign_send', 'download_pdf', 'download_xml'], true) && $providerDocumentId === null) {
            throw ValidationException::withMessages([
                'document' => ['Chứng từ chưa có provider document ID; hãy tạo hóa đơn nháp và đối soát trước.'],
            ]);
        }

        $snapshot = ['options' => $options, 'provider_operation_key' => $providerKey];

        if (in_array($operation, ['create_draft', 'create_and_sign'], true)) {
            $snapshot['payload'] = $this->payloadBuilder->buildForIssue(
                $document,
                $connection,
                $options['series'] ?? null,
            );
        }

        $operationKey = $operation === 'create_draft'
            ? $providerKey
            : $providerKey.':'.$operation;

        $changed = false;
        $transmission = DB::transaction(function () use (
            $document,
            $connection,
            $operation,
            $operationKey,
            $providerDocumentId,
            $snapshot,
            &$changed,
        ): AcctEinvoiceTransmission {
            $transmission = AcctEinvoiceTransmission::query()->firstOrCreate(
                ['operation_key' => $operationKey],
                [
                    'document_id' => $document->id,
                    'connection_id' => $connection->id,
                    'provider' => $connection->provider,
                    'operation' => $operation,
                    'status' => 'queued',
                    'provider_document_id' => $providerDocumentId,
                    'legal_status' => 'pending',
                    'request_snapshot' => $snapshot,
                    'next_attempt_at' => now(),
                ],
            );
            $changed = $transmission->wasRecentlyCreated;

            if (! $transmission->wasRecentlyCreated && in_array($transmission->status, ['failed', 'uncertain'], true)) {
                $transmission->forceFill([
                    'status' => 'queued',
                    'request_snapshot' => $snapshot,
                    'provider_document_id' => $transmission->provider_document_id ?? $providerDocumentId,
                    'next_attempt_at' => now(),
                    'last_error' => null,
                ])->save();
                $changed = true;
            }

            return $transmission;
        });

        if ($changed) {
            $this->audit->record(
                'accounting.einvoice.transmission_queued',
                $transmission,
                null,
                $this->auditSnapshot($transmission),
                moduleKey: 'minvoice-connector',
            );
        }

        if (in_array($transmission->status, ['queued', 'failed', 'uncertain'], true)) {
            ProcessEinvoiceTransmission::dispatch($transmission->id)->afterCommit();
        }

        return $transmission->fresh();
    }

    public function enqueueReconciliation(AcctEinvoiceTransmission $source): AcctEinvoiceTransmission
    {
        $source->loadMissing(['document', 'connection']);
        $key = (string) data_get($source->request_snapshot, 'provider_operation_key');

        $transmission = AcctEinvoiceTransmission::query()->firstOrCreate(
            ['operation_key' => $key.':reconcile'],
            [
                'document_id' => $source->document_id,
                'connection_id' => $source->connection_id,
                'provider' => $source->provider,
                'operation' => 'sync_status',
                'status' => 'queued',
                'provider_document_id' => $source->provider_document_id,
                'legal_status' => 'pending',
                'request_snapshot' => ['provider_operation_key' => $key, 'source_transmission_id' => $source->id],
                'next_attempt_at' => now()->addMinute(),
            ],
        );

        if ($transmission->wasRecentlyCreated) {
            $this->audit->record(
                'accounting.einvoice.reconciliation_queued',
                $transmission,
                null,
                $this->auditSnapshot($transmission),
                moduleKey: 'minvoice-connector',
            );
        }

        ProcessEinvoiceTransmission::dispatch($transmission->id)->delay(now()->addMinute())->afterCommit();

        return $transmission;
    }

    public function legalPreview(
        AcctDocument $document,
        AcctProviderConnection $connection,
        string $operation,
        array $options = [],
    ): array {
        $this->guard->assertConnectorEnabled();
        $this->assertConnectionMatches($document, $connection);

        return $this->payloadBuilder->legalOperationPreview($document, $connection, $operation, $options);
    }

    public function enqueueBlockedLegalOperation(
        AcctDocument $document,
        AcctProviderConnection $connection,
        string $operation,
        array $options = [],
    ): AcctEinvoiceTransmission {
        $preview = $this->legalPreview($document, $connection, $operation, $options);
        $key = $this->payloadBuilder->operationKey($document, $connection).':'.$operation;

        $transmission = AcctEinvoiceTransmission::query()->firstOrCreate(
            ['operation_key' => $key],
            [
                'document_id' => $document->id,
                'connection_id' => $connection->id,
                'provider' => $connection->provider,
                'operation' => $operation,
                'status' => 'blocked',
                'legal_status' => 'pending',
                'request_snapshot' => $preview,
                'last_error' => 'Provider contract chưa được xác nhận; không có network request nào được gửi.',
            ],
        );

        if ($transmission->wasRecentlyCreated) {
            $this->audit->record(
                'accounting.einvoice.legal_operation_blocked',
                $transmission,
                null,
                $this->auditSnapshot($transmission),
                moduleKey: 'minvoice-connector',
            );
        }

        return $transmission;
    }

    private function assertConnectionMatches(AcctDocument $document, AcctProviderConnection $connection): void
    {
        if ($connection->channel !== 'outbound' || $document->organization_id !== $connection->organization_id) {
            throw ValidationException::withMessages([
                'connection_id' => ['Kết nối đầu ra không thuộc pháp nhân của chứng từ.'],
            ]);
        }
    }

    private function latestProviderDocumentId(
        AcctDocument $document,
        AcctProviderConnection $connection,
    ): ?string {
        return AcctEinvoiceTransmission::query()
            ->where('document_id', $document->id)
            ->where('connection_id', $connection->id)
            ->whereNotNull('provider_document_id')
            ->latest('id')
            ->value('provider_document_id');
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(AcctEinvoiceTransmission $transmission): array
    {
        return [
            'document_id' => $transmission->document_id,
            'connection_id' => $transmission->connection_id,
            'provider' => $transmission->provider,
            'operation' => $transmission->operation,
            'operation_key' => $transmission->operation_key,
            'status' => $transmission->status,
            'legal_status' => $transmission->legal_status,
        ];
    }
}
