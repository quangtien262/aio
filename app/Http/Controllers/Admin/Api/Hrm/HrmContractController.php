<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmContract;
use App\Models\HrmEmployee;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrmContractController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(HrmEmployee $employee): JsonResponse
    {
        return response()->json(['data' => $employee->contracts()->latest('start_date')->get()]);
    }

    public function store(Request $request, HrmEmployee $employee): JsonResponse
    {
        $data = $this->validated($request);
        $contract = DB::transaction(function () use ($employee, $data) {
            if (blank($data['contract_number'] ?? null)) {
                $data['contract_number'] = $this->nextContractNumber($data['start_date']);
            }

            return $employee->contracts()->create($data);
        });
        $this->auditLogger->record('hrm.contract.created', $contract, null, $contract->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã tạo hợp đồng.', 'data' => $contract], 201);
    }

    public function update(Request $request, HrmContract $contract): JsonResponse
    {
        $before = $contract->toArray();
        $data = $this->validated($request, $contract);
        if (blank($data['contract_number'] ?? null)) {
            unset($data['contract_number']);
        }
        $contract->update($data);
        $this->auditLogger->record('hrm.contract.updated', $contract, $before, $contract->fresh()->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cập nhật hợp đồng.', 'data' => $contract->fresh()]);
    }

    private function validated(Request $request, ?HrmContract $contract = null): array
    {
        return $request->validate([
            'contract_number' => ['nullable', 'string', 'max:80', Rule::unique('hrm_contracts', 'contract_number')->ignore($contract?->id)],
            'contract_type' => ['required', Rule::in(['probation', 'fixed_term', 'indefinite', 'seasonal', 'service'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function nextContractNumber(string $startDate): string
    {
        $year = Carbon::parse($startDate)->format('Y');
        $prefix = "HD{$year}-";
        $lastNumber = HrmContract::query()
            ->where('contract_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('contract_number')
            ->map(fn (string $number): int => preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $number, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
