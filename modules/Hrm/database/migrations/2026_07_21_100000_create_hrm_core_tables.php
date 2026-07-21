<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_departments', function (Blueprint $table): void {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->text('description')->nullable(); $table->boolean('is_active')->default(true)->index(); $table->timestamps();
        });
        Schema::create('hrm_positions', function (Blueprint $table): void {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name');
            $table->text('description')->nullable(); $table->boolean('is_active')->default(true)->index(); $table->timestamps();
        });
        Schema::create('hrm_employees', function (Blueprint $table): void {
            $table->id(); $table->string('employee_code', 50)->unique();
            $table->foreignId('admin_id')->nullable()->unique()->constrained('admins')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
            $table->string('full_name'); $table->string('work_email')->nullable()->index(); $table->string('personal_email')->nullable();
            $table->string('phone', 30)->nullable(); $table->date('date_of_birth')->nullable(); $table->string('gender', 20)->nullable();
            $table->string('identity_number', 100)->nullable(); $table->text('address')->nullable();
            $table->string('work_location')->nullable(); $table->date('join_date')->nullable(); $table->date('termination_date')->nullable();
            $table->string('employment_status', 40)->default('active')->index(); $table->text('note')->nullable(); $table->timestamps();
        });
        Schema::create('hrm_contracts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('contract_number', 80)->unique(); $table->string('contract_type', 50); $table->date('start_date');
            $table->date('end_date')->nullable(); $table->decimal('base_salary', 15, 2)->default(0); $table->string('status', 40)->default('draft')->index();
            $table->text('note')->nullable(); $table->timestamps();
        });
        Schema::create('hrm_leave_requests', function (Blueprint $table): void {
            $table->id(); $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('leave_type', 50); $table->date('start_date'); $table->date('end_date'); $table->decimal('days', 6, 2);
            $table->text('reason')->nullable(); $table->string('status', 40)->default('pending')->index();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable(); $table->timestamps();
        });
        Schema::create('hrm_attendance_records', function (Blueprint $table): void {
            $table->id(); $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->date('work_date'); $table->time('check_in_at')->nullable(); $table->time('check_out_at')->nullable();
            $table->decimal('worked_hours', 5, 2)->default(0); $table->string('status', 40)->default('present')->index();
            $table->string('source', 40)->default('manual'); $table->text('note')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete(); $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_attendance_records'); Schema::dropIfExists('hrm_leave_requests'); Schema::dropIfExists('hrm_contracts');
        Schema::dropIfExists('hrm_employees'); Schema::dropIfExists('hrm_positions'); Schema::dropIfExists('hrm_departments');
    }
};
