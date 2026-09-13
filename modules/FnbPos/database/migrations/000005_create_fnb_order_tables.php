<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_orders')) {
            Schema::create('fnb_orders', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->string('creation_key', 120);
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('shift_id');
                $table->unsignedBigInteger('terminal_id');
                $table->unsignedBigInteger('sequence_no');
                $table->string('order_no', 80);
                $table->string('lifecycle_status', 30)->default('draft');
                $table->string('fulfillment_status', 30)->default('draft');
                $table->string('currency', 3);
                $table->string('timezone_snapshot', 64);
                $table->bigInteger('subtotal_minor')->default(0);
                $table->bigInteger('discount_total_minor')->default(0);
                $table->bigInteger('tax_total_minor')->default(0);
                $table->bigInteger('service_charge_total_minor')->default(0);
                $table->bigInteger('pricing_rounding_minor')->default(0);
                $table->bigInteger('grand_total_minor')->default(0);
                $table->json('pricing_snapshot')->nullable();
                $table->string('pricing_hash', 64)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $this->actor($table, 'submitted_by');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $this->actor($table, 'created_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'creation_key'], 'fnb_order_creation_uq');
                $table->unique(['outlet_id', 'business_day_id', 'sequence_no'], 'fnb_order_day_sequence_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'id'], 'fnb_order_day_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id', 'id'], 'fnb_order_session_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id', 'id'], 'fnb_order_shift_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_order_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'business_day_id', 'lifecycle_status', 'created_at'], 'fnb_order_hot_idx');
                $table->index(['website_key', 'outlet_id', 'shift_id', 'lifecycle_status'], 'fnb_order_shift_status_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'session_id'], 'fnb_order_session_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'id'])->on('fnb_service_sessions')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'shift_id'], 'fnb_order_shift_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'id'])->on('fnb_shifts')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_order_financial_projections')) {
            Schema::create('fnb_order_financial_projections', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('order_id');
                $table->bigInteger('gross_paid_total_minor')->default(0);
                $table->bigInteger('refunded_total_minor')->default(0);
                $table->bigInteger('net_collected_total_minor')->default(0);
                $table->string('payment_status', 30)->default('unpaid');
                $table->string('refund_status', 30)->default('none');
                $table->unsignedBigInteger('source_watermark')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('updated_at');
                $table->unique('order_id', 'fnb_order_projection_order_uq');
                $table->foreign(['website_key', 'outlet_id', 'order_id'], 'fnb_order_projection_order_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->index(['website_key', 'outlet_id', 'payment_status', 'order_id'], 'fnb_order_projection_hot_idx');
            });
        }

        if (! Schema::hasTable('fnb_order_lines')) {
            Schema::create('fnb_order_lines', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('business_day_id');
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('parent_line_id')->nullable();
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('variant_id');
                $table->string('item_code_snapshot', 60);
                $table->string('item_name_snapshot');
                $table->string('variant_code_snapshot', 60);
                $table->string('variant_name_snapshot');
                $table->string('tax_category_snapshot', 30);
                $table->unsignedInteger('tax_rate_bps_snapshot')->default(0);
                $table->boolean('tax_inclusive_snapshot')->default(true);
                $table->decimal('ordered_quantity', 18, 6);
                $table->decimal('voided_quantity', 18, 6)->default(0);
                $table->decimal('fulfilled_quantity', 18, 6)->default(0);
                $table->decimal('compensated_quantity', 18, 6)->default(0);
                $table->bigInteger('original_unit_price_minor');
                $table->bigInteger('unit_price_minor');
                $table->bigInteger('subtotal_minor');
                $table->bigInteger('discount_total_minor')->default(0);
                $table->bigInteger('tax_total_minor')->default(0);
                $table->bigInteger('service_charge_total_minor')->default(0);
                $table->bigInteger('pricing_rounding_minor')->default(0);
                $table->bigInteger('total_minor');
                $table->string('status', 40)->default('draft');
                $table->unsignedBigInteger('prep_station_id')->nullable();
                $table->unsignedBigInteger('recipe_id')->nullable();
                $table->json('recipe_snapshot')->nullable();
                $table->string('recipe_snapshot_hash', 64)->nullable();
                $table->text('note')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'], 'fnb_order_line_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'order_id', 'id'], 'fnb_order_line_scope_id_uq');
                $table->index(['order_id', 'status', 'sort_order'], 'fnb_order_line_hot_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id'], 'fnb_order_line_order_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'parent_line_id'], 'fnb_order_line_parent_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_order_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'item_id'], 'fnb_order_line_item_fk')
                    ->references(['website_key', 'id'])->on('fnb_menu_items')->restrictOnDelete();
                $table->foreign(['website_key', 'item_id', 'variant_id'], 'fnb_order_line_variant_fk')
                    ->references(['website_key', 'item_id', 'id'])->on('fnb_item_variants')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'prep_station_id'], 'fnb_order_line_station_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_prep_stations')->restrictOnDelete();
                $table->foreign(['website_key', 'recipe_id'], 'fnb_order_line_recipe_fk')
                    ->references(['website_key', 'id'])->on('fnb_recipes')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_order_line_modifiers')) {
            Schema::create('fnb_order_line_modifiers', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_line_id');
                $table->unsignedBigInteger('modifier_group_id');
                $table->unsignedBigInteger('modifier_option_id');
                $table->string('group_code_snapshot', 60);
                $table->string('group_name_snapshot');
                $table->string('option_code_snapshot', 60);
                $table->string('option_name_snapshot');
                $table->decimal('quantity', 18, 6)->default(1);
                $table->bigInteger('unit_price_delta_minor')->default(0);
                $table->bigInteger('total_minor')->default(0);
                $table->json('recipe_snapshot')->nullable();
                $table->string('recipe_snapshot_hash', 64)->nullable();
                $table->timestamps();
                $table->unique(['order_line_id', 'modifier_option_id'], 'fnb_line_modifier_option_uq');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'order_line_id'], 'fnb_line_modifier_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_order_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'modifier_group_id', 'modifier_option_id'], 'fnb_line_modifier_option_fk')
                    ->references(['website_key', 'group_id', 'id'])->on('fnb_modifier_options')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_order_adjustments')) {
            Schema::create('fnb_order_adjustments', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('line_id')->nullable();
                $table->string('kind', 40);
                $table->string('method', 30);
                $table->unsignedInteger('rate_bps')->nullable();
                $table->bigInteger('value_minor')->nullable();
                $table->bigInteger('amount_minor');
                $table->text('reason')->nullable();
                $this->actor($table, 'actor_id');
                $table->unsignedBigInteger('approval_id')->nullable();
                $table->string('idempotency_key', 120);
                $table->timestamp('occurred_at');
                $table->unique(['order_id', 'idempotency_key'], 'fnb_order_adjustment_idem_uq');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id'], 'fnb_order_adjustment_order_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'line_id'], 'fnb_order_adjustment_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_order_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'approval_id'], 'fnb_order_adjustment_approval_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_approvals')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_order_events')) {
            Schema::create('fnb_order_events', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('order_id');
                $table->string('event_type', 60);
                $table->string('from_lifecycle_status', 30)->nullable();
                $table->string('to_lifecycle_status', 30)->nullable();
                $table->string('from_fulfillment_status', 30)->nullable();
                $table->string('to_fulfillment_status', 30)->nullable();
                $table->unsignedInteger('aggregate_version');
                $this->actor($table, 'actor_id');
                $this->actor($table, 'approver_id');
                $table->text('reason')->nullable();
                $table->json('payload')->nullable();
                $table->string('request_id', 100)->nullable();
                $table->string('idempotency_key', 120)->nullable();
                $table->timestamp('occurred_at');
                $table->unique(['order_id', 'event_type', 'idempotency_key'], 'fnb_order_event_idem_uq');
                $table->foreign(['website_key', 'outlet_id', 'order_id'], 'fnb_order_event_order_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->index(['website_key', 'outlet_id', 'order_id', 'occurred_at'], 'fnb_order_event_hot_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fnb_order_events', 'fnb_order_adjustments', 'fnb_order_line_modifiers',
            'fnb_order_lines', 'fnb_order_financial_projections', 'fnb_orders',
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
