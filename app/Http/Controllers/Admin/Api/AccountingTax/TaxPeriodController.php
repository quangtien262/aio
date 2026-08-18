<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctTaxPeriod;
use App\Support\AccountingTax\AccountingOrganizationResolver;
use App\Support\AccountingTax\AccountingTaxReportService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxPeriodController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, AccountingOrganizationResolver $organizations): JsonResponse
    {
        $organization = $organizations->resolve($request->integer('organization_id') ?: null);

        return response()->json(['data' => [
            'items' => AcctTaxPeriod::query()
                ->where('organization_id', $organization->id)
                ->latest('start_date')
                ->get()
                ->map(fn (AcctTaxPeriod $period): array => $this->serialize($period))
                ->values()
                ->all(),
        ]]);
    }

    public function store(Request $request, AccountingOrganizationResolver $organizations): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:acct_organizations,id'],
            'code' => ['required', 'string', 'max:50'],
            'period_type' => ['sometimes', Rule::in(['monthly', 'quarterly', 'yearly', 'custom'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'base_currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);
        $organization = $organizations->resolve($data['organization_id'] ?? null);

        $overlap = AcctTaxPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['period' => ['Khoảng thời gian đã giao nhau với một kỳ thuế khác.']]);
        }

        $period = AcctTaxPeriod::query()->create([
            ...collect($data)->except('organization_id')->all(),
            'organization_id' => $organization->id,
            'base_currency' => strtoupper($data['base_currency'] ?? $organization->default_currency ?? 'VND'),
            'status' => 'open',
            'created_by' => $request->user('admin')?->id,
        ]);
        $this->auditLogger->record('accounting.tax-period.created', $period, null, $period->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => $this->serialize($period)], 201);
    }

    public function transition(
        Request $request,
        AcctTaxPeriod $period,
        AccountingTaxReportService $reports,
    ): JsonResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['review', 'lock', 'file', 'reopen'])],
            'filing_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $adminId = $request->user('admin')?->id;

        $period = DB::transaction(function () use ($period, $data, $reports, $adminId): AcctTaxPeriod {
            $locked = AcctTaxPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $before = $locked->toArray();
            $updates = match ($data['action']) {
                'review' => $this->reviewUpdates($locked),
                'lock' => $this->lockUpdates($locked, $reports, $adminId),
                'file' => $this->fileUpdates($locked, $data, $adminId),
                'reopen' => $this->reopenUpdates($locked, $data),
            };
            $locked->forceFill([...$updates, 'version' => $locked->version + 1])->save();
            $this->auditLogger->record(
                'accounting.tax-period.'.$data['action'],
                $locked,
                $before,
                $locked->fresh()->toArray(),
                moduleKey: 'accounting-tax',
            );

            return $locked->fresh();
        }, 3);

        return response()->json(['data' => $this->serialize($period)]);
    }

    /** @return array<string, mixed> */
    private function reviewUpdates(AcctTaxPeriod $period): array
    {
        $this->requireStatus($period, ['open']);

        return ['status' => 'review'];
    }

    /** @return array<string, mixed> */
    private function lockUpdates(AcctTaxPeriod $period, AccountingTaxReportService $reports, ?int $adminId): array
    {
        $this->requireStatus($period, ['review']);
        if ($period->created_by !== null && (int) $period->created_by === $adminId) {
            throw ValidationException::withMessages(['actor' => ['Người tạo kỳ thuế không được tự khóa kỳ.']]);
        }

        $blockers = $reports->filingBlockers(
            $period->organization_id,
            $period->start_date,
            $period->end_date,
        );

        if ($blockers !== []) {
            throw ValidationException::withMessages(['period' => $blockers]);
        }

        $snapshot = $reports->build(
            $period->organization_id,
            $period->start_date,
            $period->end_date,
            'tax',
        );
        $snapshot['filing_ready'] = true;
        $snapshot['filing_blockers'] = [];
        $snapshot['period'] = [
            'code' => $period->code,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'version' => $period->version + 1,
        ];
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'status' => 'locked',
            'report_snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', $encoded),
            'locked_at' => now(),
            'locked_by' => $adminId,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function fileUpdates(AcctTaxPeriod $period, array $data, ?int $adminId): array
    {
        $this->requireStatus($period, ['locked']);
        if (blank($data['filing_reference'] ?? null)) {
            throw ValidationException::withMessages(['filing_reference' => ['Cần mã tham chiếu hồ sơ kê khai.']]);
        }

        return [
            'status' => 'filed',
            'filed_at' => now(),
            'filed_by' => $adminId,
            'filing_reference' => $data['filing_reference'],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function reopenUpdates(AcctTaxPeriod $period, array $data): array
    {
        $this->requireStatus($period, ['locked', 'filed']);
        if (blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => ['Cần nêu lý do mở lại kỳ thuế.']]);
        }

        return [
            'status' => 'open',
            'notes' => trim((string) $period->notes."\nMở lại: ".$data['reason']),
            'report_snapshot' => null,
            'snapshot_hash' => null,
            'locked_at' => null,
            'locked_by' => null,
            'filed_at' => null,
            'filed_by' => null,
            'filing_reference' => null,
        ];
    }

    /** @param array<int, string> $statuses */
    private function requireStatus(AcctTaxPeriod $period, array $statuses): void
    {
        if (! in_array($period->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => ['Trạng thái kỳ thuế không phù hợp với thao tác.']]);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(AcctTaxPeriod $period): array
    {
        return [
            'id' => $period->id,
            'organization_id' => $period->organization_id,
            'code' => $period->code,
            'period_type' => $period->period_type,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'base_currency' => $period->base_currency,
            'status' => $period->status,
            'version' => $period->version,
            'snapshot_hash' => $period->snapshot_hash,
            'report_snapshot' => $period->report_snapshot,
            'locked_at' => $period->locked_at?->toIso8601String(),
            'filed_at' => $period->filed_at?->toIso8601String(),
            'filing_reference' => $period->filing_reference,
            'notes' => $period->notes,
        ];
    }
}
