<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctEmailDelivery;
use App\Models\AcctExport;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AccountingEmailService
{
    private const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly AccountingArtifactStore $artifacts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int, int>  $exportIds
     * @return array{delivery:AcctEmailDelivery,created:bool}
     */
    public function prepare(
        AcctDocument $document,
        string $recipientEmail,
        ?string $recipientName,
        ?string $subject,
        string $templateKey,
        array $exportIds,
        bool $includeDocumentCsv,
        ?int $requestedBy,
        ?string $clientIdempotencyKey,
    ): array {
        $exportIds = collect($exportIds)->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values()->all();
        $document->loadMissing(['organization', 'party', 'lines']);

        if ($document->workflow_status !== 'posted') {
            throw ValidationException::withMessages([
                'document' => ['Chỉ gửi email cho chứng từ đã ghi sổ.'],
            ]);
        }

        $subject = trim((string) $subject) ?: $this->defaultSubject($document);
        $fingerprintPayload = [
            'organization_id' => $document->organization_id,
            'document_id' => $document->id,
            'recipient_email' => Str::lower(trim($recipientEmail)),
            'recipient_name' => trim((string) $recipientName) ?: null,
            'subject' => $subject,
            'template_key' => $templateKey,
            'export_ids' => $exportIds,
            'include_document_csv' => $includeDocumentCsv,
        ];
        $fingerprint = AccountingRequestFingerprint::make($fingerprintPayload);
        $clientKey = trim((string) $clientIdempotencyKey) ?: (string) Str::uuid();
        $idempotencyKey = hash('sha256', "accounting-email|{$document->organization_id}|{$clientKey}");
        $writtenPaths = [];

        try {
            return DB::transaction(function () use (
                $document,
                $recipientEmail,
                $recipientName,
                $subject,
                $templateKey,
                $exportIds,
                $includeDocumentCsv,
                $requestedBy,
                $fingerprint,
                $idempotencyKey,
                &$writtenPaths,
            ): array {
                $existing = AcctEmailDelivery::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => ['Khóa idempotency đã được dùng cho một yêu cầu email khác.'],
                        ]);
                    }

                    return ['delivery' => $existing, 'created' => false];
                }

                $uuid = (string) Str::uuid();
                $attachmentBase = "email-deliveries/org-{$document->organization_id}/{$uuid}";
                $attachments = [];

                if ($includeDocumentCsv) {
                    $name = 'chung-tu-'.($document->document_no ?: $document->id).'.csv';
                    $name = $this->safeFileName($name);
                    $path = "{$attachmentBase}/{$name}";
                    $csv = $this->documentCsv($document);

                    try {
                        $artifact = $this->artifacts->putAtomically($path, $csv);
                    } finally {
                        fclose($csv);
                    }

                    $writtenPaths[] = $path;
                    $attachments[] = [
                        ...$artifact,
                        'name' => $name,
                        'mime_type' => 'text/csv; charset=UTF-8',
                        'source_type' => 'document_snapshot',
                        'source_id' => $document->id,
                    ];
                }

                if ($exportIds !== []) {
                    $exports = AcctExport::query()->whereIn('id', $exportIds)->lockForUpdate()->get()->keyBy('id');

                    foreach ($exportIds as $exportId) {
                        /** @var AcctExport|null $export */
                        $export = $exports->get($exportId);

                        if (! $export || (int) $export->organization_id !== (int) $document->organization_id
                            || $export->status !== 'completed' || ! $export->artifact_path || ! $export->checksum) {
                            throw ValidationException::withMessages([
                                'export_ids' => ["Báo cáo #{$exportId} chưa hoàn tất hoặc không thuộc cùng pháp nhân."],
                            ]);
                        }

                        $name = $this->safeFileName("export-{$export->id}-".($export->original_name ?: 'report.csv'));
                        $path = "{$attachmentBase}/{$name}";
                        $artifact = $this->artifacts->copyImmutable($export->artifact_path, $path, $export->checksum);
                        $writtenPaths[] = $path;
                        $attachments[] = [
                            ...$artifact,
                            'name' => $name,
                            'mime_type' => $export->mime_type ?: 'application/octet-stream',
                            'source_type' => 'accounting_export',
                            'source_id' => $export->id,
                        ];
                    }
                }

                if (collect($attachments)->sum('byte_size') > self::MAX_ATTACHMENT_BYTES) {
                    throw ValidationException::withMessages([
                        'attachments' => ['Tổng file đính kèm vượt quá 20 MB; hãy gửi ít báo cáo hơn hoặc thu hẹp dữ liệu xuất.'],
                    ]);
                }

                $delivery = AcctEmailDelivery::query()->create([
                    'uuid' => $uuid,
                    'organization_id' => $document->organization_id,
                    'document_id' => $document->id,
                    'recipient_email' => Str::lower(trim($recipientEmail)),
                    'recipient_name' => trim((string) $recipientName) ?: null,
                    'template_key' => $templateKey,
                    'subject' => $subject,
                    'status' => 'queued',
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'payload_snapshot' => $this->snapshot($document),
                    'attachments' => $attachments,
                    'attempt_count' => 0,
                    'provider' => (string) config('mail.default'),
                    'requested_by' => $requestedBy,
                    'queued_at' => now(),
                ]);
                $statusDocument = AcctDocument::query()->lockForUpdate()->findOrFail($document->id);
                $statusDocument->forceFill([
                    'mail_status' => 'queued',
                    'version' => $statusDocument->version + 1,
                ])->saveTrusted();
                $this->audit->record(
                    'accounting.email.queued',
                    $delivery,
                    null,
                    [
                        'organization_id' => $delivery->organization_id,
                        'document_id' => $delivery->document_id,
                        'recipient_hash' => hash('sha256', $delivery->recipient_email),
                        'template_key' => $delivery->template_key,
                        'attachment_count' => count($attachments),
                        'status' => $delivery->status,
                    ],
                    moduleKey: 'accounting-tax',
                );

                return ['delivery' => $delivery, 'created' => true];
            });
        } catch (Throwable $exception) {
            foreach ($writtenPaths as $path) {
                $this->artifacts->delete($path);
            }

            if ($exception instanceof UniqueConstraintViolationException) {
                $existing = AcctEmailDelivery::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing && hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    return ['delivery' => $existing, 'created' => false];
                }

                if ($existing) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Khóa idempotency đã được dùng cho một yêu cầu email khác.'],
                    ]);
                }
            }

            throw $exception;
        }
    }

    public function retry(AcctEmailDelivery $delivery): AcctEmailDelivery
    {
        return DB::transaction(function () use ($delivery): AcctEmailDelivery {
            $locked = AcctEmailDelivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if (! in_array($locked->status, ['failed', 'retrying'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Chỉ email thất bại mới có thể đưa lại vào hàng đợi.'],
                ]);
            }

            foreach ($locked->attachments ?? [] as $attachment) {
                if (! $this->artifacts->existsWithChecksum((string) ($attachment['path'] ?? ''), $attachment['checksum'] ?? null)) {
                    throw ValidationException::withMessages([
                        'attachments' => ['File đính kèm bất biến không còn hợp lệ; cần tạo yêu cầu email mới.'],
                    ]);
                }
            }

            $locked->forceFill([
                'status' => 'queued',
                'last_error' => null,
                'queued_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ])->save();
            $this->audit->record(
                'accounting.email.retry_queued',
                $locked,
                null,
                [
                    'organization_id' => $locked->organization_id,
                    'document_id' => $locked->document_id,
                    'attempt_count' => $locked->attempt_count,
                    'status' => 'queued',
                ],
                moduleKey: 'accounting-tax',
            );

            return $locked->fresh('attempts');
        });
    }

    private function defaultSubject(AcctDocument $document): string
    {
        $number = $document->document_no ?: '#'.$document->id;
        $organization = $document->direction === 'inbound'
            ? $document->buyer_snapshot
            : $document->seller_snapshot;
        $organizationName = data_get($organization, 'legal_name') ?: $document->organization->name;

        return "Hóa đơn/chứng từ {$number} - {$organizationName}";
    }

    private function snapshot(AcctDocument $document): array
    {
        $organization = $document->direction === 'inbound'
            ? $document->buyer_snapshot
            : $document->seller_snapshot;
        $party = $document->direction === 'inbound'
            ? $document->seller_snapshot
            : $document->buyer_snapshot;

        return [
            'schema_version' => 'accounting-document-email.v1',
            'captured_at' => now()->toIso8601String(),
            'organization' => $organization ? [
                ...$organization,
                'name' => $organization['legal_name'] ?? null,
            ] : null,
            'seller' => $document->seller_snapshot,
            'buyer' => $document->buyer_snapshot,
            'document' => [
                'id' => $document->id,
                'document_no' => $document->document_no,
                'document_date' => $document->document_date?->toDateString(),
                'due_date' => $document->due_date?->toDateString(),
                'direction' => $document->direction,
                'document_type' => $document->document_type,
                'currency' => $document->currency,
                'workflow_status' => $document->workflow_status,
                'payment_status' => $document->payment_status,
                'legal_status' => $document->legal_status,
                'subtotal' => (string) $document->subtotal,
                'discount_total' => (string) $document->discount_total,
                'tax_total' => (string) $document->tax_total,
                'grand_total' => (string) $document->grand_total,
                'notes' => $document->notes,
            ],
            'party' => $party ? [
                ...$party,
                'name' => $party['legal_name'] ?? null,
            ] : null,
            'lines' => $document->lines->map(fn ($line): array => [
                'name' => $line->name,
                'sku' => $line->sku,
                'unit' => $line->unit,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'discount_amount' => (string) $line->discount_amount,
                'tax_rate' => $line->tax_rate !== null ? (string) $line->tax_rate : null,
                'tax_amount' => (string) $line->tax_amount,
                'line_total' => (string) $line->line_total,
            ])->values()->all(),
        ];
    }

    /** @return resource */
    private function documentCsv(AcctDocument $document): mixed
    {
        $stream = fopen('php://temp/maxmemory:1048576', 'w+b');

        if (! is_resource($stream)) {
            throw new RuntimeException('Không thể tạo file đính kèm chứng từ.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Tên hàng hóa/dịch vụ', 'SKU', 'Đơn vị', 'Số lượng', 'Đơn giá', 'Chiết khấu', 'Thuế suất', 'Tiền thuế', 'Thành tiền'], ',', '"', '');

        foreach ($document->lines as $line) {
            $values = [
                $line->name,
                $line->sku,
                $line->unit,
                $line->quantity,
                $line->unit_price,
                $line->discount_amount,
                $line->tax_rate,
                $line->tax_amount,
                $line->line_total,
            ];
            fputcsv($stream, array_map($this->safeCsvValue(...), $values), ',', '"', '');
        }

        rewind($stream);

        return $stream;
    }

    private function safeCsvValue(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'{$value}" : $value;
    }

    private function safeFileName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '-', Str::ascii($name)) ?: 'attachment';

        return trim($name, '.-') ?: 'attachment';
    }
}
