<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctExport;
use App\Models\AcctOrganization;
use App\Support\AuditLogger;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class AccountingExportService
{
    /** @var array<string, string> */
    public const DEFINITIONS = [
        'document_register' => 'document-register.v1',
        'vat_operational_estimate' => 'vat-operational-estimate.v1',
    ];

    private const MAX_XLSX_ROWS = 50000;

    public function __construct(
        private readonly AccountingArtifactStore $artifacts,
        private readonly AuditLogger $audit,
    ) {}

    public function request(
        AcctOrganization $organization,
        string $reportType,
        string $format,
        array $filters,
        string $timezone,
        ?int $requestedBy,
        ?string $clientIdempotencyKey,
    ): AcctExport {
        $filters = $this->normalizeFilters($filters);
        $definitionVersion = self::DEFINITIONS[$reportType] ?? throw ValidationException::withMessages([
            'report_type' => ['Loại báo cáo chưa được hỗ trợ.'],
        ]);

        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            throw ValidationException::withMessages([
                'format' => ['Định dạng báo cáo chưa được hỗ trợ.'],
            ]);
        }

        $fingerprintPayload = [
            'organization_id' => $organization->id,
            'report_type' => $reportType,
            'definition_version' => $definitionVersion,
            'format' => $format,
            'filters' => $filters,
            'timezone' => $timezone,
        ];
        $fingerprint = AccountingRequestFingerprint::make($fingerprintPayload);
        $clientKey = trim((string) $clientIdempotencyKey) ?: (string) Str::uuid();
        $idempotencyKey = hash('sha256', "accounting-export|{$organization->id}|{$clientKey}");

        try {
            return DB::transaction(function () use (
                $organization,
                $reportType,
                $definitionVersion,
                $format,
                $filters,
                $timezone,
                $requestedBy,
                $fingerprint,
                $idempotencyKey,
            ): AcctExport {
                $existing = AcctExport::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $this->assertMatchingFingerprint($existing, $fingerprint);
                }

                $export = AcctExport::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'organization_id' => $organization->id,
                    'report_type' => $reportType,
                    'definition_version' => $definitionVersion,
                    'format' => $format,
                    'status' => 'queued',
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'filters' => $filters,
                    'timezone' => $timezone,
                    'requested_by' => $requestedBy,
                    'snapshot_at' => null,
                    'expires_at' => now()->addDays(max(1, (int) config('accounting_tax.export_retention_days', 90))),
                ]);
                $this->audit->record(
                    'accounting.export.queued',
                    $export,
                    null,
                    [
                        'organization_id' => $export->organization_id,
                        'report_type' => $export->report_type,
                        'definition_version' => $export->definition_version,
                        'format' => $export->format,
                        'status' => $export->status,
                    ],
                    moduleKey: 'accounting-tax',
                );

                return $export;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = AcctExport::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

            return $this->assertMatchingFingerprint($existing, $fingerprint);
        }
    }

    public function generate(AcctExport $export): AcctExport
    {
        $claimed = DB::transaction(function () use ($export): ?AcctExport {
            $locked = AcctExport::query()->lockForUpdate()->findOrFail($export->id);

            if ($locked->status === 'completed' && $locked->artifact_path
                && $this->artifacts->existsWithChecksum($locked->artifact_path, $locked->checksum)) {
                return null;
            }

            if ($locked->status === 'processing' && $locked->started_at?->isAfter(now()->subMinutes(15))) {
                return null;
            }

            $locked->forceFill([
                'status' => 'processing',
                'snapshot_at' => $locked->snapshot_at ?? now(),
                'started_at' => now(),
                'completed_at' => null,
                'last_error' => null,
            ])->save();

            return $locked;
        });

        if ($claimed === null) {
            return $export->fresh();
        }

        try {
            $result = match ($claimed->format) {
                'xlsx' => $this->writeXlsx($claimed),
                'pdf' => $this->writePdf($claimed),
                default => $this->writeCsv($claimed),
            };

            $claimed->forceFill([
                'status' => 'completed',
                'artifact_path' => $result['path'],
                'mime_type' => $result['mime_type'],
                'original_name' => $result['name'],
                'checksum' => $result['checksum'],
                'byte_size' => $result['byte_size'],
                'row_count' => $result['row_count'],
                'completed_at' => now(),
                'last_error' => null,
            ])->save();

            return $claimed->fresh();
        } catch (Throwable $exception) {
            $this->markFailed($claimed->id, $exception);
            throw $exception;
        }
    }

    public function retry(AcctExport $export): AcctExport
    {
        return DB::transaction(function () use ($export): AcctExport {
            $locked = AcctExport::query()->lockForUpdate()->findOrFail($export->id);

            if ($locked->status !== 'failed') {
                throw ValidationException::withMessages([
                    'status' => ['Chỉ báo cáo thất bại mới có thể đưa lại vào hàng đợi.'],
                ]);
            }

            $locked->forceFill([
                'status' => 'queued',
                'last_error' => null,
                'started_at' => null,
                'completed_at' => null,
            ])->save();
            $this->audit->record(
                'accounting.export.retry_queued',
                $locked,
                null,
                [
                    'organization_id' => $locked->organization_id,
                    'report_type' => $locked->report_type,
                    'format' => $locked->format,
                    'status' => 'queued',
                ],
                moduleKey: 'accounting-tax',
            );

            return $locked->fresh();
        });
    }

    public function markFailed(int $exportId, Throwable $exception): void
    {
        AcctExport::query()->whereKey($exportId)->update([
            'status' => 'failed',
            'last_error' => Str::limit($exception->getMessage(), 4000, ''),
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array{path:string,name:string,mime_type:string,checksum:string,byte_size:int,row_count:int}
     */
    private function writeCsv(AcctExport $export): array
    {
        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');

        if (! is_resource($stream)) {
            throw new RuntimeException('Không thể khởi tạo bộ đệm báo cáo.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $headers = $this->headers($export->report_type);
            fputcsv($stream, $headers, ',', '"', '');

            $rowCount = 0;
            foreach ($this->query($export)->cursor() as $document) {
                fputcsv($stream, array_map($this->safeCsvValue(...), $this->row($export, $document)), ',', '"', '');
                $rowCount++;
            }

            rewind($stream);
            $date = $export->snapshot_at?->setTimezone($export->timezone)->format('Ymd-His') ?? now()->format('Ymd-His');
            $name = "{$export->report_type}-{$date}-v1.csv";
            $path = $this->artifactPath($export, $name);
            $artifact = $this->artifacts->putAtomically($path, $stream);

            return [
                ...$artifact,
                'name' => $name,
                'mime_type' => 'text/csv; charset=UTF-8',
                'row_count' => $rowCount,
            ];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{path:string,name:string,mime_type:string,checksum:string,byte_size:int,row_count:int}
     */
    private function writeXlsx(AcctExport $export): array
    {
        if ($this->query($export)->count() > self::MAX_XLSX_ROWS) {
            throw ValidationException::withMessages([
                'format' => ['XLSX giới hạn 50.000 dòng; hãy thu hẹp bộ lọc hoặc dùng CSV streaming.'],
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name'))
            ->setTitle($export->definition_version)
            ->setDescription('Accounting export generated from an immutable request definition.');

        $metadata = $spreadsheet->getActiveSheet();
        $metadata->setTitle('Metadata');
        $metadataRows = [
            ['Report type', $export->report_type],
            ['Definition version', $export->definition_version],
            ['Organization ID', $export->organization_id],
            ['Snapshot at', $export->snapshot_at?->setTimezone($export->timezone)->toIso8601String()],
            ['Timezone', $export->timezone],
            ['Filters', json_encode($export->filters ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['Notice', $export->report_type === 'vat_operational_estimate'
                ? 'Ước tính vận hành; không dùng trực tiếp để kê khai thuế.'
                : 'Sổ chứng từ phục vụ đối chiếu vận hành.'],
        ];
        $metadata->fromArray($metadataRows);
        $metadata->getColumnDimension('A')->setWidth(24);
        $metadata->getColumnDimension('B')->setWidth(88);
        $metadata->getStyle('A1:A'.count($metadataRows))->getFont()->setBold(true);

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data');
        $headers = $this->headers($export->report_type);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        foreach ($headers as $index => $header) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1).'1';
            $sheet->setCellValueExplicit($coordinate, $header, DataType::TYPE_STRING);
        }

        $rowNumber = 2;
        foreach ($this->query($export)->cursor() as $document) {
            foreach ($this->row($export, $document) as $index => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;

                if ($value !== null && is_numeric($value) && in_array($index, [0, 11, 12, 13, 14, 18], true)) {
                    $sheet->setCellValue($coordinate, (float) $value);
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string) $this->safeCsvValue($value), DataType::TYPE_STRING);
                }
            }
            $rowNumber++;
        }

        $rowCount = $rowNumber - 2;
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9EDE8');
        $sheet->getStyle('L2:O'.max(2, $rowNumber - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range(1, count($headers)) as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($column)->setWidth(min(42, max(12, mb_strlen($headers[$columnIndex - 1]) + 4)));
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'acct-xlsx-');

        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Không thể tạo file XLSX tạm thời.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
            $stream = fopen($temporaryFile, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Không thể đọc file XLSX vừa tạo.');
            }

            try {
                $name = $this->artifactName($export, 'xlsx');
                $path = $this->artifactPath($export, $name);
                $artifact = $this->artifacts->putAtomically($path, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($temporaryFile);
        }

        return [
            ...$artifact,
            'name' => $name,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'row_count' => $rowCount,
        ];
    }

    /**
     * @return array{path:string,name:string,mime_type:string,checksum:string,byte_size:int,row_count:int}
     */
    private function writePdf(AcctExport $export): array
    {
        $documents = $this->query($export)->limit(2001)->get();

        if ($documents->count() > 2000) {
            throw ValidationException::withMessages([
                'format' => ['PDF giới hạn 2.000 dòng; hãy thu hẹp bộ lọc hoặc dùng CSV/XLSX.'],
            ]);
        }

        $headers = $this->headers($export->report_type);
        $headerHtml = collect($headers)->map(fn (string $header): string => '<th>'.$this->escapeHtml($header).'</th>')->implode('');
        $bodyHtml = $documents->map(function (AcctDocument $document) use ($export): string {
            return '<tr>'.collect($this->row($export, $document))
                ->map(fn (mixed $value): string => '<td>'.$this->escapeHtml($value).'</td>')
                ->implode('').'</tr>';
        })->implode('');
        $filters = $this->escapeHtml(json_encode($export->filters ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $notice = $export->report_type === 'vat_operational_estimate'
            ? '<p class="warning">Ước tính vận hành; chưa đánh giá điều kiện khấu trừ và không dùng trực tiếp để kê khai thuế.</p>'
            : '';
        $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>
            @page { margin: 24px; } body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #17201f; }
            h1 { font-size: 16px; margin: 0 0 6px; } .meta { color: #536460; margin: 2px 0; }
            .warning { color: #9a3412; border: 1px solid #fdba74; padding: 6px; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; } th { background: #d9ede8; }
            th, td { border: 1px solid #b9c9c5; padding: 3px; vertical-align: top; word-break: break-word; }
        </style></head><body><h1>'.$this->escapeHtml($export->definition_version).'</h1>
            <p class="meta">Snapshot: '.$this->escapeHtml($export->snapshot_at?->setTimezone($export->timezone)->toIso8601String()).'</p>
            <p class="meta">Timezone: '.$this->escapeHtml($export->timezone).' · Filters: '.$filters.'</p>'.$notice.'
            <table><thead><tr>'.$headerHtml.'</tr></thead><tbody>'.$bodyHtml.'</tbody></table></body></html>';

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $name = $this->artifactName($export, 'pdf');
        $path = $this->artifactPath($export, $name);
        $artifact = $this->artifacts->putAtomically($path, $dompdf->output());

        return [
            ...$artifact,
            'name' => $name,
            'mime_type' => 'application/pdf',
            'row_count' => $documents->count(),
        ];
    }

    private function artifactName(AcctExport $export, string $extension): string
    {
        $date = $export->snapshot_at?->setTimezone($export->timezone)->format('Ymd-His') ?? now()->format('Ymd-His');

        return "{$export->report_type}-{$date}-v1.{$extension}";
    }

    private function artifactPath(AcctExport $export, string $name): string
    {
        return "exports/org-{$export->organization_id}/{$export->uuid}/".bin2hex(random_bytes(8))."/{$name}";
    }

    private function escapeHtml(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function query(AcctExport $export): Builder
    {
        $filters = $export->filters ?? [];
        $query = AcctDocument::query()
            ->with('party')
            ->where('organization_id', $export->organization_id)
            ->where('created_at', '<=', $export->snapshot_at ?? now());

        if ($export->report_type === 'vat_operational_estimate') {
            $query->where('workflow_status', 'posted')
                ->whereIn('document_type', ['internal_invoice', 'tax_invoice', 'credit_note', 'debit_note'])
                ->whereNull('voided_at');
        }

        foreach (['direction', 'document_type', 'workflow_status', 'legal_status', 'currency'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['from'])) {
            $query->whereDate('document_date', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('document_date', '<=', $filters['to']);
        }

        return $query->orderBy('id');
    }

    /** @return array<int, string> */
    private function headers(string $reportType): array
    {
        $headers = [
            'ID chứng từ', 'Số chứng từ', 'Ngày chứng từ', 'Chiều', 'Loại chứng từ',
            'Đối tác', 'Mã số thuế đối tác', 'Trạng thái quy trình', 'Trạng thái pháp lý',
            'Trạng thái thanh toán', 'Tiền tệ', 'Tiền trước thuế', 'Chiết khấu', 'Tiền thuế',
            'Tổng thanh toán', 'Website', 'Ngày tạo', 'Ngày ghi sổ',
        ];

        if ($reportType === 'vat_operational_estimate') {
            $headers[] = 'Thuế VAT ước tính có dấu';
            $headers[] = 'Điều kiện khấu trừ';
        }

        return $headers;
    }

    /** @return array<int, int|float|string|null> */
    private function row(AcctExport $export, AcctDocument $document): array
    {
        $row = [
            $document->id,
            $document->document_no,
            $document->document_date?->toDateString(),
            $document->direction,
            $document->document_type,
            $document->party?->name,
            $document->party?->tax_code,
            $document->workflow_status,
            $document->legal_status,
            $document->payment_status,
            $document->currency,
            $document->subtotal,
            $document->discount_total,
            $document->tax_total,
            $document->grand_total,
            $document->website_key,
            $document->created_at?->setTimezone($export->timezone)->toIso8601String(),
            $document->posted_at?->setTimezone($export->timezone)->toIso8601String(),
        ];

        if ($export->report_type === 'vat_operational_estimate') {
            $directionMultiplier = $document->direction === 'outbound' ? 1 : -1;
            $effectSign = (int) ($document->effect_sign ?? 1) < 0 ? -1 : 1;
            $row[] = (float) ($document->base_tax_total ?? $document->tax_total) * $directionMultiplier * $effectSign;
            $row[] = ($document->tax_eligibility ?: 'not_assessed').' - không dùng trực tiếp để kê khai';
        }

        return $row;
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'{$value}" : $value;
    }

    private function normalizeFilters(array $filters): array
    {
        $filters = Arr::only($filters, [
            'from', 'to', 'direction', 'document_type', 'workflow_status', 'legal_status', 'currency',
        ]);

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                unset($filters[$key]);
            } elseif (is_string($value)) {
                $filters[$key] = trim($value);
            }
        }

        ksort($filters);

        return $filters;
    }

    private function assertMatchingFingerprint(AcctExport $export, string $fingerprint): AcctExport
    {
        if (! hash_equals((string) $export->request_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Khóa idempotency đã được dùng cho một yêu cầu khác.'],
            ]);
        }

        return $export;
    }
}
