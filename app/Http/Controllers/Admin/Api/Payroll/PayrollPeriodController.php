<?php

namespace App\Http\Controllers\Admin\Api\Payroll;

use App\Models\PayrollPeriod;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollPeriodController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => PayrollPeriod::query()->withCount('payslips')->latest('start_date')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $period = DB::transaction(function () use ($data) {
            $data['code'] = $this->nextPeriodCode();

            return PayrollPeriod::query()->create($data);
        });
        $this->auditLogger->record('payroll.period.created', $period, null, $period->toArray(), moduleKey: 'payroll');

        return response()->json(['message' => 'Đã tạo kỳ lương.', 'data' => $period], 201);
    }

    public function update(Request $request, PayrollPeriod $period): JsonResponse
    {
        abort_if($period->status !== 'draft', 422, 'Chỉ kỳ lương nháp mới được chỉnh sửa.');
        $before = $period->toArray();
        $period->update($this->validated($request, $period));
        $this->auditLogger->record('payroll.period.updated', $period, $before, $period->fresh()->toArray(), moduleKey: 'payroll');

        return response()->json(['message' => 'Đã cập nhật kỳ lương.', 'data' => $period->fresh()]);
    }

    public function transition(Request $request, PayrollPeriod $period): JsonResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['approve', 'publish', 'lock'])]])['action'];
        abort_unless($request->user('admin')->hasPermission("payroll.run.{$action}"), 403, 'Bạn không có quyền thực hiện thao tác này.');
        $allowed = ['approve' => 'draft', 'publish' => 'approved', 'lock' => 'published'];
        abort_if($period->status !== $allowed[$action], 422, 'Trạng thái kỳ lương không phù hợp với thao tác này.');
        $before = $period->toArray();
        $updates = match ($action) {
            'approve' => ['status' => 'approved', 'approved_by_admin_id' => $request->user('admin')->id, 'approved_at' => now()],
            'publish' => ['status' => 'published', 'published_at' => now()],
            'lock' => ['status' => 'locked', 'locked_at' => now()],
        };
        $period->update($updates);
        $period->payslips()->update(['status' => $action === 'publish' ? 'published' : ($action === 'lock' ? 'locked' : 'approved')]);
        $this->auditLogger->record("payroll.period.{$action}", $period, $before, $period->fresh()->toArray(), moduleKey: 'payroll');

        return response()->json(['message' => 'Đã cập nhật trạng thái kỳ lương.', 'data' => $period->fresh()]);
    }

    private function validated(Request $request, ?PayrollPeriod $period = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
    }

    private function nextPeriodCode(): string
    {
        $lastNumber = PayrollPeriod::query()
            ->where('code', 'like', 'LUONG%')
            ->lockForUpdate()
            ->pluck('code')
            ->map(fn (string $code): int => preg_match('/^LUONG(\d+)$/', $code, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'LUONG'.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
