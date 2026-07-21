<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmContract;
use App\Models\HrmEmployee;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $contract = $employee->contracts()->create($this->validated($request));
        $this->auditLogger->record('hrm.contract.created', $contract, null, $contract->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã tạo hợp đồng.', 'data' => $contract], 201);
    }

    public function update(Request $request, HrmContract $contract): JsonResponse
    {
        $before = $contract->toArray();
        $contract->update($this->validated($request, $contract));
        $this->auditLogger->record('hrm.contract.updated', $contract, $before, $contract->fresh()->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cập nhật hợp đồng.', 'data' => $contract->fresh()]);
    }

    private function validated(Request $request, ?HrmContract $contract = null): array
    {
        return $request->validate([
            'contract_number' => ['required', 'string', 'max:80', Rule::unique('hrm_contracts', 'contract_number')->ignore($contract?->id)],
            'contract_type' => ['required', Rule::in(['probation', 'fixed_term', 'indefinite', 'seasonal', 'service'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
