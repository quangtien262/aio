<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\HrmAttendanceRecord;
use App\Models\HrmDepartment;
use App\Models\HrmEmployee;
use App\Models\HrmLeaveRequest;
use App\Models\HrmPosition;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrmModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrm_lifecycle_preserves_data_and_blocks_routes_while_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');

        $this->postJson('/admin/api/modules/hrm/install')->assertOk();
        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();

        $this->assertTrue(Schema::hasTable('hrm_employees'));
        $this->assertDatabaseHas('roles', ['key' => 'hrm.employee-self']);
        $this->assertDatabaseHas('permissions', ['key' => 'hrm.employee.sensitive.view', 'risk_level' => 'sensitive']);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'NV0001',
            'full_name' => 'Nguyễn Văn An',
            'employment_status' => 'active',
        ]);

        $this->getJson('/admin/api/hrm/dashboard')->assertOk();
        $this->postJson('/admin/api/modules/hrm/disable')->assertOk();
        $this->getJson('/admin/api/hrm/dashboard')->assertNotFound();
        $this->assertDatabaseHas('hrm_employees', ['id' => $employee->id, 'employee_code' => 'NV0001']);
        $this->deleteJson('/admin/api/modules/hrm')->assertStatus(422);

        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();
        $this->getJson('/admin/api/hrm/dashboard')->assertOk()->assertJsonPath('data.total_employees', 1);
    }

    public function test_employee_can_only_read_own_leave_requests(): void
    {
        [$owner, $employeeAdmin, $employee, $otherEmployee] = $this->prepareHrmEmployees();

        HrmLeaveRequest::query()->create([
            'employee_id' => $employee->id, 'leave_type' => 'annual', 'start_date' => '2026-08-01',
            'end_date' => '2026-08-01', 'days' => 1, 'status' => 'pending',
        ]);
        HrmLeaveRequest::query()->create([
            'employee_id' => $otherEmployee->id, 'leave_type' => 'sick', 'start_date' => '2026-08-02',
            'end_date' => '2026-08-02', 'days' => 1, 'status' => 'pending',
        ]);

        $this->actingAs($employeeAdmin, 'admin')->withSession(['admin_auth_version' => $employeeAdmin->auth_version]);
        $response = $this->getJson('/admin/api/hrm/leave')->assertOk();

        $this->assertSame([$employee->id], collect($response->json('data.items'))->pluck('employee_id')->unique()->all());
        $this->postJson('/admin/api/hrm/leave', [
            'employee_id' => $otherEmployee->id,
            'leave_type' => 'annual', 'start_date' => '2026-08-03', 'end_date' => '2026-08-05', 'days' => 99,
        ])->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.days', '3.00');
    }

    public function test_published_payslips_are_isolated_per_employee(): void
    {
        [$owner, $employeeAdmin, $employee, $otherEmployee] = $this->prepareHrmEmployees();
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/payroll/install')->assertOk();
        $this->postJson('/admin/api/modules/payroll/enable')->assertOk();

        $selfRole = Role::query()->where('key', 'payroll.employee-self')->firstOrFail();
        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => $employeeAdmin->id, 'role_id' => $selfRole->id, 'scope_type' => 'global',
        ]);
        $period = PayrollPeriod::query()->create([
            'code' => '2026-07', 'name' => 'Lương tháng 07/2026', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => 'published', 'published_at' => now(),
        ]);
        PayrollPayslip::query()->create([
            'payroll_period_id' => $period->id, 'employee_id' => $employee->id,
            'base_salary' => 10000000, 'allowances' => 1000000, 'deductions' => 500000,
            'net_salary' => 10500000, 'status' => 'published',
        ]);
        PayrollPayslip::query()->create([
            'payroll_period_id' => $period->id, 'employee_id' => $otherEmployee->id,
            'base_salary' => 30000000, 'allowances' => 0, 'deductions' => 0,
            'net_salary' => 30000000, 'status' => 'published',
        ]);

        $this->actingAs($employeeAdmin, 'admin')->withSession(['admin_auth_version' => $employeeAdmin->auth_version]);
        $response = $this->getJson('/admin/api/payroll/me/payslips')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($employee->id, $response->json('data.0.employee_id'));
        $this->getJson('/admin/api/payroll/payslips')->assertForbidden();
    }

    public function test_employee_can_only_read_own_attendance(): void
    {
        [$owner, $employeeAdmin, $employee, $otherEmployee] = $this->prepareHrmEmployees();
        HrmAttendanceRecord::query()->create([
            'employee_id' => $employee->id, 'work_date' => '2026-07-20', 'worked_hours' => 8, 'status' => 'present',
        ]);
        HrmAttendanceRecord::query()->create([
            'employee_id' => $otherEmployee->id, 'work_date' => '2026-07-20', 'worked_hours' => 4, 'status' => 'remote',
        ]);
        $this->postJson('/admin/api/hrm/attendance', [
            'employee_id' => $otherEmployee->id,
            'work_date' => '2026-07-21',
            'check_in_at' => '08:15',
            'check_out_at' => '17:00',
            'worked_hours' => 24,
            'status' => 'present',
        ])->assertCreated()->assertJsonPath('data.worked_hours', '8.75');

        $this->actingAs($employeeAdmin, 'admin')->withSession(['admin_auth_version' => $employeeAdmin->auth_version]);
        $response = $this->getJson('/admin/api/hrm/attendance')->assertOk();

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame($employee->id, $response->json('data.items.0.employee_id'));
        $this->postJson('/admin/api/hrm/attendance', [
            'employee_id' => $employee->id, 'work_date' => '2026-07-21', 'worked_hours' => 8, 'status' => 'present',
        ])->assertForbidden();
    }

    public function test_department_codes_are_generated_by_the_backend(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/hrm/install')->assertOk();
        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();

        $first = $this->postJson('/admin/api/hrm/organization/departments', [
            'name' => 'Phòng Kinh doanh',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'PB01');

        $this->postJson('/admin/api/hrm/organization/departments', [
            'name' => 'Phòng Nhân sự',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'PB02');

        $department = HrmDepartment::query()->findOrFail($first->json('data.id'));
        $this->putJson("/admin/api/hrm/organization/departments/{$department->id}", [
            'name' => 'Phòng Kinh doanh & Phát triển',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.code', 'PB01');
    }

    public function test_position_codes_are_generated_by_the_backend(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/hrm/install')->assertOk();
        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();

        $first = $this->postJson('/admin/api/hrm/organization/positions', [
            'name' => 'Trưởng phòng',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'CV01');

        $this->postJson('/admin/api/hrm/organization/positions', [
            'name' => 'Chuyên viên',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'CV02');

        $position = HrmPosition::query()->findOrFail($first->json('data.id'));
        $this->putJson("/admin/api/hrm/organization/positions/{$position->id}", [
            'name' => 'Trưởng phòng cấp cao',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.code', 'CV01');
    }

    public function test_contract_number_is_generated_when_omitted(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/hrm/install')->assertOk();
        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'NV-HOPDONG',
            'full_name' => 'Nhân viên Hợp đồng',
            'employment_status' => 'active',
        ]);

        $payload = [
            'contract_type' => 'fixed_term',
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'base_salary' => 15000000,
            'status' => 'draft',
        ];

        $this->postJson("/admin/api/hrm/employees/{$employee->id}/contracts", $payload)
            ->assertCreated()
            ->assertJsonPath('data.contract_number', 'HD2026-0001');
        $this->postJson("/admin/api/hrm/employees/{$employee->id}/contracts", $payload)
            ->assertCreated()
            ->assertJsonPath('data.contract_number', 'HD2026-0002');
    }

    public function test_payroll_period_codes_are_generated_by_the_backend(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/hrm/install')->assertOk();
        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();
        $this->postJson('/admin/api/modules/payroll/install')->assertOk();
        $this->postJson('/admin/api/modules/payroll/enable')->assertOk();

        $first = $this->postJson('/admin/api/payroll/periods', [
            'name' => 'Lương tháng 07/2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ])->assertCreated()->assertJsonPath('data.code', 'LUONG001');

        $this->postJson('/admin/api/payroll/periods', [
            'name' => 'Lương tháng 08/2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ])->assertCreated()->assertJsonPath('data.code', 'LUONG002');

        $period = PayrollPeriod::query()->findOrFail($first->json('data.id'));
        $this->putJson("/admin/api/payroll/periods/{$period->id}", [
            'name' => 'Lương tháng 07/2026 đã cập nhật',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ])->assertOk()->assertJsonPath('data.code', 'LUONG001');
    }

    private function prepareHrmEmployees(): array
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/hrm/install')->assertOk();
        $this->postJson('/admin/api/modules/hrm/enable')->assertOk();

        $employeeAdmin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $selfRole = Role::query()->where('key', 'hrm.employee-self')->firstOrFail();
        AdminRoleAssignment::query()->create([
            'admin_id' => $employeeAdmin->id, 'role_id' => $selfRole->id,
            'scope_type' => 'global', 'scope_value' => null, 'assigned_by' => $owner->id,
        ]);
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'NV0001', 'admin_id' => $employeeAdmin->id,
            'full_name' => 'Nhân viên Một', 'employment_status' => 'active',
        ]);
        $otherEmployee = HrmEmployee::query()->create([
            'employee_code' => 'NV0002', 'full_name' => 'Nhân viên Hai', 'employment_status' => 'active',
        ]);

        return [$owner, $employeeAdmin, $employee, $otherEmployee];
    }
}
