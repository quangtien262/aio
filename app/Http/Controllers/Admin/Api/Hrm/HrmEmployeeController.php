<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\HrmDepartment;
use App\Models\HrmEmployee;
use App\Models\HrmPosition;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class HrmEmployeeController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $query = HrmEmployee::query()->with(['department', 'position', 'manager', 'admin:id,name,username,email,status'])->latest('id');
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('employee_code', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%")->orWhere('work_email', 'like', "%{$search}%"));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->string('employment_status'));
        }
        $canSensitive = $request->user('admin')->hasPermission('hrm.employee.sensitive.view');
        $items = $query->get()->map(function (HrmEmployee $employee) use ($canSensitive) {
            $data = $employee->toArray();
            if (! $canSensitive) {
                unset($data['identity_number'],$data['personal_email'],$data['address']);
            }

return $data;
        });

        return response()->json(['data' => ['items' => $items, 'references' => ['departments' => HrmDepartment::query()->where('is_active', true)->orderBy('name')->get(), 'positions' => HrmPosition::query()->where('is_active', true)->orderBy('name')->get(), 'managers' => HrmEmployee::query()->where('employment_status', 'active')->orderBy('full_name')->get(['id', 'employee_code', 'full_name']), 'available_admins' => Admin::query()->whereKeyNot(Admin::SYSTEM_OWNER_ID)->whereDoesntHave('employeeProfile')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'username', 'email'])]]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $employee = HrmEmployee::query()->create($data);
        $this->auditLogger->record('hrm.employee.created', $employee, null, $employee->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã tạo hồ sơ nhân sự.', 'data' => $employee->load(['department', 'position'])], 201);
    }

    public function update(Request $request, HrmEmployee $employee): JsonResponse
    {
        $before = $employee->toArray();
        $employee->update($this->validated($request, $employee));
        $this->auditLogger->record('hrm.employee.updated', $employee, $before, $employee->fresh()->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cập nhật hồ sơ nhân sự.', 'data' => $employee->fresh(['department', 'position', 'admin'])]);
    }

    public function archive(Request $request, HrmEmployee $employee): JsonResponse
    {
        $before = $employee->toArray();
        DB::transaction(function () use ($employee) {
            $employee->update(['employment_status' => 'terminated', 'termination_date' => $employee->termination_date ?: today()]);
            if ($employee->admin) {
                $employee->admin->update(['status' => 'archived', 'is_active' => false, 'locked_at' => now(), 'locked_reason' => 'Nhân sự đã nghỉ việc.', 'auth_version' => $employee->admin->auth_version + 1]);
            }
        });
        $this->auditLogger->record('hrm.employee.archived', $employee, $before, $employee->fresh()->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã kết thúc làm việc và khóa tài khoản liên kết.']);
    }

    public function assignAccount(Request $request, HrmEmployee $employee): JsonResponse
    {
        abort_if($employee->admin_id !== null, 422, 'Nhân sự đã có tài khoản đăng nhập.');
        $data = $request->validate(['admin_id' => ['nullable', 'integer', Rule::exists('admins', 'id')->where(fn ($q) => $q->where('id', '!=', Admin::SYSTEM_OWNER_ID))], 'name' => ['required_without:admin_id', 'string', 'max:255'], 'username' => ['required_without:admin_id', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('admins', 'username')], 'email' => ['required_without:admin_id', 'nullable', 'email', 'max:255', Rule::unique('admins', 'email')], 'password' => ['required_without:admin_id', 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $admin = DB::transaction(function () use ($data, $employee, $request) {
            $admin = isset($data['admin_id']) ? Admin::query()->findOrFail($data['admin_id']) : Admin::query()->create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'password' => $data['password'], 'status' => 'active', 'is_active' => true, 'must_change_password' => true, 'auth_version' => 1]);
            abort_if($admin->isSystemOwner() || HrmEmployee::query()->where('admin_id', $admin->id)->exists(), 422, 'Tài khoản không thể liên kết.');
            $employee->update(['admin_id' => $admin->id]);
            foreach (['hrm.employee-self', 'payroll.employee-self'] as $roleKey) {
                $role = Role::query()->where('key', $roleKey)->where('status', 'active')->first();
                if ($role) {
                    AdminRoleAssignment::query()->firstOrCreate(['admin_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null], ['assigned_by' => $request->user('admin')->id]);
                }
            }$admin->increment('auth_version');

            return $admin;
        });
        $this->auditLogger->record('hrm.employee.account.assigned', $employee, null, ['admin_id' => $admin->id], moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cấp tài khoản đăng nhập cho nhân sự.', 'data' => $admin->only(['id', 'name', 'username', 'email', 'status'])]);
    }

    private function validated(Request $request, ?HrmEmployee $employee = null): array
    {
        return $request->validate(['employee_code' => ['required', 'string', 'max:50', Rule::unique('hrm_employees', 'employee_code')->ignore($employee?->id)], 'department_id' => ['nullable', 'integer', 'exists:hrm_departments,id'], 'position_id' => ['nullable', 'integer', 'exists:hrm_positions,id'], 'manager_employee_id' => ['nullable', 'integer', 'exists:hrm_employees,id', Rule::notIn(array_filter([$employee?->id]))], 'full_name' => ['required', 'string', 'max:255'], 'work_email' => ['nullable', 'email', 'max:255'], 'personal_email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'date_of_birth' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', Rule::in(['male', 'female', 'other'])], 'identity_number' => ['nullable', 'string', 'max:100'], 'address' => ['nullable', 'string'], 'work_location' => ['nullable', 'string', 'max:255'], 'join_date' => ['nullable', 'date'], 'termination_date' => ['nullable', 'date', 'after_or_equal:join_date'], 'employment_status' => ['required', Rule::in(['onboarding', 'active', 'probation', 'leave', 'suspended', 'terminated'])], 'note' => ['nullable', 'string']]);
    }
}
