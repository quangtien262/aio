<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $base = static function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('chain_id')->index();
            $table->unsignedBigInteger('pharmacy_id')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        };
        Schema::create('ph_patients', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->longText('encrypted_profile');
            $table->string('status', 30)->default('active');
        });
        Schema::create('ph_prescriptions', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->unsignedBigInteger('patient_id');
            $table->string('reference', 100);
            $table->dateTime('issued_at');
            $table->dateTime('expires_at');
            $table->longText('encrypted_snapshot');
            $table->string('status', 30)->default('draft');
            $table->string('verification_method', 30)->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->unique(['chain_id', 'reference']);
            $table->index(['pharmacy_id', 'status', 'id']);
        });
        Schema::create('ph_prescription_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('prescription_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_allowed', 20, 6);
            $table->string('unit', 50);
            $table->longText('encrypted_instructions');
            $table->timestamps();
        });
        Schema::create('ph_dispensings', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('prescription_id')->nullable()->index();
            $table->string('mode', 20)->default('otc');
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->longText('encrypted_snapshot');
            $table->dateTime('reservation_expires_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('handed_over_at')->nullable();
            $table->index(['pharmacy_id', 'status', 'id']);
        });
        Schema::create('ph_dispensing_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('dispensing_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('prescription_line_id')->nullable()->index();
            $table->unsignedBigInteger('packaging_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('quantity', 20, 6);
            $table->decimal('sale_quantity', 20, 6);
            $table->string('unit', 50);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('total_minor');
            $table->longText('encrypted_snapshot');
            $table->timestamps();
        });
        Schema::create('ph_dispense_claims', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prescription_line_id')->index();
            $table->unsignedBigInteger('dispensing_line_id')->unique();
            $table->decimal('quantity', 20, 6);
            $table->string('status', 40)->default('reserved');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ph_dispensing_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('dispensing_line_id')->index();
            $table->unsignedBigInteger('batch_id');
            $table->uuid('reservation_id')->unique();
            $table->decimal('quantity', 20, 6);
            $table->string('status', 30)->default('reserved');
            $table->string('operation_id', 100)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->decimal('unit_cost', 20, 6)->nullable();
            $table->decimal('total_cost', 20, 6)->nullable();
            $table->timestamps();
        });
        Schema::create('ph_dispensing_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('dispensing_id')->index();
            $table->unsignedBigInteger('reviewer_id');
            $table->string('decision', 20);
            $table->string('fingerprint', 64);
            $table->longText('encrypted_reason');
            $table->dateTime('invalidated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ph_shifts', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->unsignedBigInteger('counter_id')->index();
            $table->unsignedBigInteger('admin_id');
            $table->string('status', 20)->default('open');
            $table->bigInteger('opening_minor')->default(0);
            $table->bigInteger('closing_minor')->nullable();
            $table->bigInteger('expected_minor')->nullable();
            $table->bigInteger('difference_minor')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->longText('encrypted_close_reason')->nullable();
        });
        Schema::create('ph_sales', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->unsignedBigInteger('dispensing_id')->unique();
            $table->unsignedBigInteger('shift_id');
            $table->string('status', 30)->default('paid');
            $table->bigInteger('total_minor');
            $table->bigInteger('returned_minor')->default(0);
            $table->bigInteger('refunded_minor')->default(0);
            $table->unsignedBigInteger('created_by');
        });
        Schema::create('ph_tenders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('sale_id')->index();
            $table->string('method', 30);
            $table->bigInteger('amount_minor');
            $table->string('status', 30)->default('paid');
            $table->bigInteger('tendered_minor')->nullable();
            $table->bigInteger('change_minor')->default(0);
            $table->longText('encrypted_reference')->nullable();
            $table->timestamps();
        });
        Schema::create('ph_returns', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->unsignedBigInteger('sale_id')->index();
            $table->unsignedBigInteger('dispensing_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->bigInteger('amount_minor');
            $table->bigInteger('refunded_minor')->default(0);
            $table->string('status', 30)->default('received');
            $table->longText('encrypted_reason');
        });
        Schema::create('ph_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('return_id')->index();
            $table->unsignedBigInteger('dispensing_line_id')->index();
            $table->unsignedBigInteger('allocation_id')->index();
            $table->unsignedBigInteger('batch_id');
            $table->decimal('quantity', 20, 6);
            $table->bigInteger('amount_minor');
            $table->string('inventory_operation_id', 100);
            $table->timestamps();
        });
        Schema::create('ph_refunds', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->unsignedBigInteger('sale_id')->index();
            $table->unsignedBigInteger('return_id')->index();
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('created_by');
            $table->bigInteger('amount_minor');
            $table->string('method', 30)->default('cash');
            $table->string('status', 30)->default('paid');
            $table->longText('encrypted_reason');
        });
        Schema::create('ph_custody_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('dispensing_id')->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 40);
            $table->longText('encrypted_evidence');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ph_custody_events', 'ph_refunds', 'ph_return_lines', 'ph_returns', 'ph_tenders', 'ph_sales', 'ph_shifts', 'ph_dispensing_reviews', 'ph_dispensing_allocations', 'ph_dispense_claims', 'ph_dispensing_lines', 'ph_dispensings', 'ph_prescription_lines', 'ph_prescriptions', 'ph_patients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
