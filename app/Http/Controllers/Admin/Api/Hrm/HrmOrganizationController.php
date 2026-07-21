<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmDepartment;
use App\Models\HrmPosition;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrmOrganizationController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => ['departments' => HrmDepartment::query()->with('parent')->withCount('employees')->orderBy('name')->get(), 'positions' => HrmPosition::query()->withCount('employees')->orderBy('name')->get()]]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $model = $this->model($type);
        $data = $this->validateData($request, $type);
        $record = DB::transaction(function () use ($model, $type, $data) {
            $data['code'] = $type === 'departments'
                ? $this->nextDepartmentCode()
                : $this->nextPositionCode();

            return $model::query()->create($data);
        });
        $this->auditLogger->record("hrm.{$type}.created", $record, null, $record->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã tạo dữ liệu tổ chức.', 'data' => $record], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->model($type);
        $record = $model::query()->findOrFail($id);
        $before = $record->toArray();
        $record->update($this->validateData($request, $type, $record));
        $this->auditLogger->record("hrm.{$type}.updated", $record, $before, $record->fresh()->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cập nhật dữ liệu tổ chức.', 'data' => $record->fresh()]);
    }

    private function model(string $type): string
    {
        abort_unless(in_array($type, ['departments', 'positions'], true), 404);

        return $type === 'departments' ? HrmDepartment::class : HrmPosition::class;
    }

    private function validateData(Request $request, string $type, ?Model $record = null): array
    {
        $rules = ['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['required', 'boolean']];
        if ($type === 'departments') {
            $rules['parent_id'] = ['nullable', 'integer', 'exists:hrm_departments,id', Rule::notIn(array_filter([$record?->id]))];
        }

        return $request->validate($rules);
    }

    private function nextDepartmentCode(): string
    {
        $lastNumber = HrmDepartment::query()
            ->where('code', 'like', 'PB%')
            ->lockForUpdate()
            ->pluck('code')
            ->map(fn (string $code): int => preg_match('/^PB(\d+)$/', $code, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'PB'.str_pad((string) ($lastNumber + 1), 2, '0', STR_PAD_LEFT);
    }

    private function nextPositionCode(): string
    {
        $lastNumber = HrmPosition::query()
            ->where('code', 'like', 'CV%')
            ->lockForUpdate()
            ->pluck('code')
            ->map(fn (string $code): int => preg_match('/^CV(\d+)$/', $code, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'CV'.str_pad((string) ($lastNumber + 1), 2, '0', STR_PAD_LEFT);
    }
}
