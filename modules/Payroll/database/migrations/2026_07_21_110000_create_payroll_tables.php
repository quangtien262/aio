<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id(); $table->string('code', 60)->unique(); $table->string('name'); $table->date('start_date'); $table->date('end_date');
            $table->string('status', 40)->default('draft')->index(); $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable(); $table->timestamp('published_at')->nullable(); $table->timestamp('locked_at')->nullable(); $table->timestamps();
        });
        Schema::create('payroll_payslips', function (Blueprint $table): void {
            $table->id(); $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0); $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0); $table->decimal('net_salary', 15, 2)->default(0);
            $table->string('status', 40)->default('draft')->index(); $table->json('snapshot')->nullable(); $table->text('note')->nullable(); $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });
        Schema::create('payroll_payslip_lines', function (Blueprint $table): void {
            $table->id(); $table->foreignId('payslip_id')->constrained('payroll_payslips')->cascadeOnDelete();
            $table->string('type', 30); $table->string('code', 80); $table->string('label'); $table->decimal('amount', 15, 2); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payroll_payslip_lines'); Schema::dropIfExists('payroll_payslips'); Schema::dropIfExists('payroll_periods');
    }
};
