<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createChecks();
        $this->createPayments();
        $this->createRefunds();
        $this->createCompensations();
        $this->createCashLedger();
    }

    private function createChecks(): void
    {
        if (! Schema::hasTable('fnb_checks')) {
            Schema::create('fnb_checks', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->string('creation_key', 120);
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('customer_profile_id')->nullable();
                $table->unsignedBigInteger('sequence_no');
                $table->string('check_no', 80);
                $table->string('status', 30)->default('open');
                $table->string('currency', 3);
                $table->string('timezone_snapshot', 64);
                $table->bigInteger('subtotal_minor')->default(0);
                $table->bigInteger('discount_total_minor')->default(0);
                $table->bigInteger('tax_total_minor')->default(0);
                $table->bigInteger('service_charge_total_minor')->default(0);
                $table->bigInteger('pricing_rounding_minor')->default(0);
                $table->bigInteger('grand_total_minor')->default(0);
                $table->string('settlement_mode', 30)->nullable();
                $table->bigInteger('cash_rounding_minor')->nullable();
                $table->bigInteger('settlement_total_minor')->nullable();
                $table->string('settlement_hash', 64)->nullable();
                $table->json('buyer_snapshot')->nullable();
                $table->boolean('invoice_requested')->default(false);
                $table->timestamp('finalized_at')->nullable();
                $this->actor($table, 'finalized_by');
                $table->timestamp('settlement_planned_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $this->actor($table, 'closed_by');
                $table->timestamp('voided_at')->nullable();
                $this->actor($table, 'voided_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'creation_key'], 'fnb_check_creation_uq');
                $table->unique(['outlet_id', 'business_day_id', 'sequence_no'], 'fnb_check_day_sequence_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'id'], 'fnb_check_day_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'id'], 'fnb_check_session_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_check_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'status', 'closed_at'], 'fnb_check_status_idx');
                $table->index(['website_key', 'customer_profile_id', 'closed_at'], 'fnb_check_customer_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id'], 'fnb_check_session_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'id'])->on('fnb_service_sessions')->restrictOnDelete();
                $table->foreign(['website_key', 'customer_profile_id'], 'fnb_check_customer_fk')
                    ->references(['website_key', 'id'])->on('fnb_customer_profiles')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_check_financial_projections')) {
            Schema::create('fnb_check_financial_projections', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('check_id');
                $table->bigInteger('gross_paid_total_minor')->default(0);
                $table->bigInteger('refunded_total_minor')->default(0);
                $table->bigInteger('net_collected_total_minor')->default(0);
                $table->string('payment_status', 30)->default('unpaid');
                $table->string('refund_status', 30)->default('none');
                $table->unsignedBigInteger('source_watermark')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('updated_at');
                $table->unique('check_id', 'fnb_check_projection_check_uq');
                $table->foreign(['website_key', 'outlet_id', 'check_id'], 'fnb_check_projection_check_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_checks')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_check_lines')) {
            Schema::create('fnb_check_lines', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('business_day_id');
                $table->string('currency', 3);
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('check_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_line_id');
                $table->decimal('allocated_quantity', 18, 6);
                $table->bigInteger('allocated_subtotal_minor');
                $table->bigInteger('allocated_discount_minor')->default(0);
                $table->bigInteger('allocated_service_charge_minor')->default(0);
                $table->bigInteger('allocated_tax_minor')->default(0);
                $table->bigInteger('allocated_pricing_rounding_minor')->default(0);
                $table->bigInteger('allocated_total_minor');
                $table->timestamps();
                $table->unique(['check_id', 'order_line_id'], 'fnb_check_line_order_line_uq');
                $table->unique(['website_key', 'outlet_id', 'check_id', 'id'], 'fnb_check_line_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'order_line_id', 'id'], 'fnb_check_line_source_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'order_id', 'order_line_id', 'check_id', 'id'], 'fnb_check_line_full_chain_id_uq');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'check_id'], 'fnb_check_line_check_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'id'])->on('fnb_checks')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'order_id'], 'fnb_check_line_order_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'order_line_id'], 'fnb_check_line_order_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_order_lines')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_check_adjustments')) {
            Schema::create('fnb_check_adjustments', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('check_id');
                $table->string('kind', 40);
                $table->bigInteger('amount_minor');
                $table->json('policy_snapshot')->nullable();
                $table->text('reason')->nullable();
                $this->actor($table, 'actor_id');
                $table->string('idempotency_key', 120);
                $table->timestamp('occurred_at');
                $table->unique(['check_id', 'kind', 'idempotency_key'], 'fnb_check_adjustment_idem_uq');
                $table->foreign(['website_key', 'outlet_id', 'check_id'], 'fnb_check_adjustment_check_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_checks')->restrictOnDelete();
            });
        }
    }

    private function createPayments(): void
    {
        if (! Schema::hasTable('fnb_payments')) {
            Schema::create('fnb_payments', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('business_day_id');
                $table->string('currency', 3);
                $table->unsignedBigInteger('check_id');
                $table->unsignedBigInteger('shift_id');
                $table->unsignedBigInteger('terminal_id');
                $table->unsignedBigInteger('payment_method_id');
                $table->string('method_code_snapshot', 40);
                $table->string('method_name_snapshot');
                $table->string('method_kind_snapshot', 30);
                $table->string('provider_connection_key', 100)->nullable();
                $table->string('provider_operation_key', 160)->nullable();
                $table->string('status', 30)->default('reserved');
                $table->bigInteger('amount_minor');
                $table->bigInteger('tendered_minor')->nullable();
                $table->bigInteger('change_minor')->default(0);
                $table->string('provider_reference', 160)->nullable();
                $table->string('reference', 160)->nullable();
                $table->string('idempotency_key', 120);
                $table->string('request_fingerprint', 64);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('reserved_at');
                $table->timestamp('provider_dispatched_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $this->actor($table, 'cancelled_by');
                $table->text('cancel_reason')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->json('metadata')->nullable();
                $this->actor($table, 'created_by');
                $table->timestamps();
                $table->unique(['outlet_id', 'idempotency_key'], 'fnb_payment_idempotency_uq');
                $table->unique(['provider_connection_key', 'provider_operation_key'], 'fnb_payment_provider_operation_uq');
                $table->unique(['provider_connection_key', 'provider_reference'], 'fnb_payment_provider_reference_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_payment_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'check_id', 'id'], 'fnb_payment_check_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'check_id', 'id'], 'fnb_payment_check_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id', 'id'], 'fnb_payment_shift_chain_id_uq');
                $table->index(['website_key', 'outlet_id', 'check_id', 'status'], 'fnb_payment_check_status_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'check_id'], 'fnb_payment_check_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'id'])->on('fnb_checks')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id'], 'fnb_payment_shift_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'id'])->on('fnb_shifts')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'payment_method_id'], 'fnb_payment_method_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_payment_methods')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_payment_attempts')) {
            Schema::create('fnb_payment_attempts', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('payment_id');
                $table->unsignedInteger('attempt_no');
                $table->string('status', 30);
                $table->string('request_hash', 64);
                $table->string('response_hash', 64)->nullable();
                $table->string('response_code', 80)->nullable();
                $table->string('provider_trace_reference', 160)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('resolved_at')->nullable();
                $table->unique(['payment_id', 'attempt_no'], 'fnb_payment_attempt_no_uq');
                $table->foreign(['website_key', 'outlet_id', 'payment_id'], 'fnb_payment_attempt_payment_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_payments')->restrictOnDelete();
            });
        }
    }

    private function createRefunds(): void
    {
        if (! Schema::hasTable('fnb_refunds')) {
            Schema::create('fnb_refunds', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('original_business_day_id');
                $table->unsignedBigInteger('processing_business_day_id');
                $table->string('currency', 3);
                $table->unsignedBigInteger('processed_shift_id');
                $table->unsignedBigInteger('processed_terminal_id');
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('check_id');
                $table->string('method_code_snapshot', 40);
                $table->string('method_name_snapshot');
                $table->string('method_kind_snapshot', 30);
                $table->unsignedBigInteger('sequence_no');
                $table->string('refund_no', 80);
                $table->string('status', 30)->default('reserved');
                $table->bigInteger('amount_minor');
                $table->text('reason');
                $table->string('idempotency_key', 120);
                $table->string('request_fingerprint', 64);
                $table->string('provider_connection_key', 100)->nullable();
                $table->string('provider_operation_key', 160)->nullable();
                $table->string('provider_reference', 160)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('requested_at');
                $this->actor($table, 'requested_by');
                $table->timestamp('approved_at')->nullable();
                $this->actor($table, 'approved_by');
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('provider_dispatched_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $this->actor($table, 'rejected_by');
                $table->timestamp('cancelled_at')->nullable();
                $this->actor($table, 'cancelled_by');
                $table->text('cancel_reason')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['outlet_id', 'idempotency_key'], 'fnb_refund_idempotency_uq');
                $table->unique(['outlet_id', 'processing_business_day_id', 'sequence_no'], 'fnb_refund_day_sequence_uq');
                $table->unique(['provider_connection_key', 'provider_operation_key'], 'fnb_refund_provider_operation_uq');
                $table->unique(['provider_connection_key', 'provider_reference'], 'fnb_refund_provider_reference_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_refund_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'check_id', 'id'], 'fnb_refund_check_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'processing_business_day_id', 'currency', 'processed_terminal_id', 'processed_shift_id', 'id'], 'fnb_refund_shift_chain_id_uq');
                $table->index(['website_key', 'outlet_id', 'payment_id', 'status'], 'fnb_refund_payment_status_idx');
                $table->foreign(['website_key', 'outlet_id', 'original_business_day_id', 'currency', 'check_id', 'payment_id'], 'fnb_refund_payment_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'check_id', 'id'])->on('fnb_payments')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'processing_business_day_id', 'currency', 'processed_terminal_id', 'processed_shift_id'], 'fnb_refund_shift_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'id'])->on('fnb_shifts')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_refund_attempts')) {
            Schema::create('fnb_refund_attempts', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('refund_id');
                $table->unsignedInteger('attempt_no');
                $table->string('status', 30);
                $table->string('request_hash', 64);
                $table->string('response_hash', 64)->nullable();
                $table->string('response_code', 80)->nullable();
                $table->string('provider_trace_reference', 160)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('resolved_at')->nullable();
                $table->unique(['refund_id', 'attempt_no'], 'fnb_refund_attempt_no_uq');
                $table->foreign(['website_key', 'outlet_id', 'refund_id'], 'fnb_refund_attempt_refund_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_refunds')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_refund_allocations')) {
            Schema::create('fnb_refund_allocations', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('refund_id');
                $table->unsignedBigInteger('check_id');
                $table->unsignedBigInteger('check_line_id')->nullable();
                $table->string('allocation_kind', 30)->default('line');
                $table->decimal('quantity', 18, 6)->nullable();
                $table->bigInteger('allocated_subtotal_minor')->default(0);
                $table->bigInteger('allocated_discount_minor')->default(0);
                $table->bigInteger('allocated_service_charge_minor')->default(0);
                $table->bigInteger('allocated_tax_minor')->default(0);
                $table->bigInteger('allocated_pricing_rounding_minor')->default(0);
                $table->bigInteger('allocated_cash_rounding_minor')->default(0);
                $table->bigInteger('allocated_total_minor');
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'refund_id', 'check_id', 'id'], 'fnb_refund_allocation_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'refund_id', 'check_id', 'check_line_id', 'id'], 'fnb_refund_allocation_line_id_uq');
                $table->foreign(['website_key', 'outlet_id', 'check_id', 'refund_id'], 'fnb_refund_allocation_refund_fk')
                    ->references(['website_key', 'outlet_id', 'check_id', 'id'])->on('fnb_refunds')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'check_id', 'check_line_id'], 'fnb_refund_allocation_line_fk')
                    ->references(['website_key', 'outlet_id', 'check_id', 'id'])->on('fnb_check_lines')->restrictOnDelete();
            });
        }
    }

    private function createCompensations(): void
    {
        if (! Schema::hasTable('fnb_fulfillment_compensations')) {
            Schema::create('fnb_fulfillment_compensations', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('business_day_id');
                $table->string('currency', 3);
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_line_id');
                $table->unsignedBigInteger('check_id');
                $table->unsignedBigInteger('check_line_id');
                $table->unsignedBigInteger('kitchen_line_id')->nullable();
                $table->decimal('requested_quantity', 18, 6);
                $table->json('financial_snapshot');
                $table->json('kitchen_snapshot');
                $table->json('recipe_snapshot')->nullable();
                $table->string('snapshot_hash', 64);
                $table->string('refund_group_key', 160);
                $table->string('status', 40)->default('requested');
                $table->text('reason');
                $table->unsignedBigInteger('approval_id')->nullable();
                $table->string('idempotency_key', 120);
                $table->string('request_fingerprint', 64);
                $table->string('active_slot', 20)->nullable()->default('active');
                $table->timestamp('requested_at');
                $this->actor($table, 'requested_by');
                $table->timestamp('resolved_at')->nullable();
                $this->actor($table, 'resolved_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'idempotency_key'], 'fnb_compensation_idempotency_uq');
                $table->unique(['outlet_id', 'refund_group_key'], 'fnb_compensation_refund_group_uq');
                $table->unique(['check_line_id', 'active_slot'], 'fnb_compensation_line_active_uq');
                $table->unique(['website_key', 'outlet_id', 'check_id', 'check_line_id', 'id'], 'fnb_compensation_source_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_compensation_scope_id_uq');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'order_id'], 'fnb_compensation_order_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'order_line_id'], 'fnb_compensation_order_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_order_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'check_id'], 'fnb_compensation_check_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'id'])->on('fnb_checks')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'order_id', 'order_line_id', 'check_id', 'check_line_id'], 'fnb_compensation_check_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'order_id', 'order_line_id', 'check_id', 'id'])->on('fnb_check_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'kitchen_line_id'], 'fnb_compensation_kitchen_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_kitchen_ticket_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'approval_id'], 'fnb_compensation_approval_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_approvals')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_fulfillment_compensation_refund_allocations')) {
            Schema::create('fnb_fulfillment_compensation_refund_allocations', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('compensation_id');
                $table->unsignedBigInteger('refund_id');
                $table->unsignedBigInteger('refund_allocation_id');
                $table->unsignedBigInteger('check_id');
                $table->unsignedBigInteger('source_check_line_id');
                $table->unsignedBigInteger('allocation_check_line_id')->nullable();
                $table->string('allocation_kind', 30);
                $table->decimal('quantity', 18, 6)->nullable();
                $table->bigInteger('allocated_subtotal_minor')->default(0);
                $table->bigInteger('allocated_discount_minor')->default(0);
                $table->bigInteger('allocated_service_charge_minor')->default(0);
                $table->bigInteger('allocated_tax_minor')->default(0);
                $table->bigInteger('allocated_pricing_rounding_minor')->default(0);
                $table->bigInteger('allocated_cash_rounding_minor')->default(0);
                $table->bigInteger('allocated_total_minor');
                $table->timestamps();
                $table->unique('refund_allocation_id', 'fnb_compensation_refund_allocation_uq');
                $table->unique(['compensation_id', 'refund_allocation_id'], 'fnb_compensation_refund_link_uq');
                $table->foreign(['website_key', 'outlet_id', 'check_id', 'source_check_line_id', 'compensation_id'], 'fnb_compensation_refund_source_fk')
                    ->references(['website_key', 'outlet_id', 'check_id', 'check_line_id', 'id'])->on('fnb_fulfillment_compensations')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'refund_id', 'check_id', 'refund_allocation_id'], 'fnb_compensation_refund_allocation_fk')
                    ->references(['website_key', 'outlet_id', 'refund_id', 'check_id', 'id'])->on('fnb_refund_allocations')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'check_id', 'allocation_check_line_id'], 'fnb_compensation_refund_line_fk')
                    ->references(['website_key', 'outlet_id', 'check_id', 'id'])->on('fnb_check_lines')->restrictOnDelete();
            });
        }
    }

    private function createCashLedger(): void
    {
        if (! Schema::hasTable('fnb_cash_movements')) {
            Schema::create('fnb_cash_movements', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('business_day_id');
                $table->string('currency', 3);
                $table->unsignedBigInteger('terminal_id');
                $table->unsignedBigInteger('shift_id');
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->unsignedBigInteger('refund_id')->nullable();
                $table->string('kind', 30);
                $table->string('direction', 10);
                $table->bigInteger('amount_minor');
                $table->text('reason')->nullable();
                $this->actor($table, 'actor_id');
                $table->unsignedBigInteger('approval_id')->nullable();
                $table->string('idempotency_key', 120);
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->unique(['shift_id', 'idempotency_key'], 'fnb_cash_movement_idempotency_uq');
                $table->index(['website_key', 'outlet_id', 'shift_id', 'occurred_at'], 'fnb_cash_movement_hot_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id'], 'fnb_cash_movement_shift_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'id'])->on('fnb_shifts')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id', 'payment_id'], 'fnb_cash_movement_payment_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id', 'id'])->on('fnb_payments')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id', 'refund_id'], 'fnb_cash_movement_refund_fk')
                    ->references(['website_key', 'outlet_id', 'processing_business_day_id', 'currency', 'processed_terminal_id', 'processed_shift_id', 'id'])->on('fnb_refunds')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'approval_id'], 'fnb_cash_movement_approval_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_approvals')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_shift_tender_totals')) {
            Schema::create('fnb_shift_tender_totals', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('shift_id');
                $table->unsignedBigInteger('payment_method_id');
                $table->string('method_code_snapshot', 40);
                $table->string('method_kind_snapshot', 30);
                $table->bigInteger('expected_minor');
                $table->bigInteger('counted_minor');
                $table->bigInteger('variance_minor');
                $table->json('closing_snapshot');
                $table->timestamps();
                $table->unique(['shift_id', 'payment_method_id'], 'fnb_shift_tender_method_uq');
                $table->foreign(['website_key', 'outlet_id', 'shift_id'], 'fnb_shift_tender_shift_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_shifts')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'payment_method_id'], 'fnb_shift_tender_method_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_payment_methods')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fnb_shift_tender_totals', 'fnb_cash_movements',
            'fnb_fulfillment_compensation_refund_allocations', 'fnb_fulfillment_compensations',
            'fnb_refund_allocations', 'fnb_refund_attempts', 'fnb_refunds',
            'fnb_payment_attempts', 'fnb_payments', 'fnb_check_adjustments',
            'fnb_check_lines', 'fnb_check_financial_projections', 'fnb_checks',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function scope(Blueprint $table): void
    {
        $table->string('website_key', 120);
        $table->unsignedBigInteger('outlet_id');
    }

    private function actor(Blueprint $table, string $name): void
    {
        $table->foreignId($name)->nullable()->constrained('admins')->nullOnDelete();
    }
};
