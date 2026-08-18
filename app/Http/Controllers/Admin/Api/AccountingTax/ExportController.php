<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Jobs\GenerateAccountingExport;
use App\Models\AcctExport;
use App\Models\AcctOrganization;
use App\Support\AccountingTax\AccountingArtifactStore;
use App\Support\AccountingTax\AccountingExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController
{
    public function index(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'status' => ['nullable', Rule::in(['queued', 'processing', 'completed', 'failed'])],
            'report_type' => ['nullable', Rule::in(array_keys(AccountingExportService::DEFINITIONS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = AcctExport::query()
            ->where('organization_id', $payload['organization_id'])
            ->when($payload['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($payload['report_type'] ?? null, fn ($query, $type) => $query->where('report_type', $type))
            ->latest()
            ->paginate((int) ($payload['per_page'] ?? 30));
        $items = collect($page->items())
            ->map(self::serialize(...))
            ->values()
            ->all();

        return response()->json(['data' => [
            'items' => $items,
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
        ]]);
    }

    public function store(Request $request, AccountingExportService $exports): JsonResponse
    {
        $payload = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'report_type' => ['required', Rule::in(array_keys(AccountingExportService::DEFINITIONS))],
            'format' => ['sometimes', Rule::in(['csv', 'xlsx', 'pdf'])],
            'timezone' => ['sometimes', 'timezone'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'filters' => ['sometimes', 'array'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date', 'after_or_equal:filters.from'],
            'filters.direction' => ['nullable', Rule::in(['inbound', 'outbound'])],
            'filters.document_type' => ['nullable', 'string', 'max:50'],
            'filters.workflow_status' => ['nullable', 'string', 'max:30'],
            'filters.legal_status' => ['nullable', 'string', 'max:30'],
            'filters.currency' => ['nullable', 'string', 'size:3'],
        ]);
        $organization = AcctOrganization::query()->findOrFail($payload['organization_id']);
        $export = $exports->request(
            organization: $organization,
            reportType: $payload['report_type'],
            format: $payload['format'] ?? 'csv',
            filters: $payload['filters'] ?? [],
            timezone: $payload['timezone'] ?? 'Asia/Ho_Chi_Minh',
            requestedBy: $request->user('admin')?->id,
            clientIdempotencyKey: $request->header('Idempotency-Key') ?: ($payload['idempotency_key'] ?? null),
        );

        if ($export->wasRecentlyCreated) {
            GenerateAccountingExport::dispatch($export->id);
        }

        return response()->json(['data' => self::serialize($export)], 202);
    }

    public function retry(AcctExport $export, AccountingExportService $exports): JsonResponse
    {
        $export = $exports->retry($export);
        GenerateAccountingExport::dispatch($export->id);

        return response()->json(['data' => self::serialize($export)], 202);
    }

    public function download(Request $request, AcctExport $export, AccountingArtifactStore $artifacts): StreamedResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin && (
            $admin->canAccess('accounting.report.view', 'organization', (string) $export->organization_id)
            || $admin->canAccess('accounting.export.create', 'organization', (string) $export->organization_id)
        ), 403);
        abort_unless($export->status === 'completed' && $export->artifact_path && $export->checksum, 404);
        abort_unless($artifacts->existsWithChecksum($export->artifact_path, $export->checksum), 409, 'File báo cáo không còn khớp checksum.');

        return $artifacts->disk()->download(
            $export->artifact_path,
            $export->original_name ?: "accounting-export-{$export->id}.{$export->format}",
            ['Content-Type' => $export->mime_type ?: 'application/octet-stream'],
        );
    }

    public static function serialize(AcctExport $export): array
    {
        return [
            'id' => $export->id,
            'uuid' => $export->uuid,
            'organization_id' => $export->organization_id,
            'report_type' => $export->report_type,
            'definition_version' => $export->definition_version,
            'format' => $export->format,
            'status' => $export->status,
            'filters' => $export->filters ?? [],
            'timezone' => $export->timezone,
            'mime_type' => $export->mime_type,
            'original_name' => $export->original_name,
            'checksum' => $export->checksum,
            'byte_size' => $export->byte_size,
            'row_count' => $export->row_count,
            'snapshot_at' => $export->snapshot_at?->toIso8601String(),
            'started_at' => $export->started_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'last_error' => $export->last_error,
            'download_url' => $export->status === 'completed'
                ? "/admin/api/accounting-tax/exports/{$export->id}/download"
                : null,
            'created_at' => $export->created_at?->toIso8601String(),
        ];
    }
}
