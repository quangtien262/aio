<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['total_cost' => 'quantity', 'allocated_cost' => 'allocated_quantity', 'returned_cost' => 'returned_quantity'] as $column => $quantity) {
            if (! Schema::hasColumn('inv_production_issue_layers', $column)) {
                Schema::table('inv_production_issue_layers', fn (Blueprint $t) => $t->decimal($column, 24, 6)->nullable());
            }
            DB::table('inv_production_issue_layers')->whereNull($column)->update([$column => DB::raw($quantity.' * unit_cost')]);
        }
        if (! Schema::hasColumn('inv_production_postings', 'version')) {
            Schema::table('inv_production_postings', fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
        }
        if (! Schema::hasColumn('inv_production_issue_layers', 'valuation_components')) {
            Schema::table('inv_production_issue_layers', fn (Blueprint $t) => $t->json('valuation_components')->nullable());
        }
        if (! Schema::hasColumn('inv_transfer_lines', 'base_unit_cost')) {
            Schema::table('inv_transfer_lines', fn (Blueprint $t) => $t->decimal('base_unit_cost', 24, 6)->nullable());
        }
        if (! Schema::hasTable('inv_valuation_events')) {
            Schema::create('inv_valuation_events', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('posting_id')->index();
                $t->string('source_id', 191);
                $t->string('source_type', 60);
                $t->string('fingerprint', 64);
                $t->decimal('amount', 24, 6);
                $t->string('reason', 1000);
                $t->unsignedBigInteger('actor_id');
                $t->timestamps();
                $t->unique(['posting_id', 'source_type', 'source_id'], 'inv_valuation_source_unique');
            });
        }
        if (! Schema::hasTable('inv_valuation_adjustments')) {
            Schema::create('inv_valuation_adjustments', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('event_id')->index();
                $t->unsignedBigInteger('cost_layer_id')->index();
                $t->decimal('quantity_basis', 24, 6);
                $t->string('row_type', 20)->default('entitlement');
                $t->decimal('amount_total', 24, 6);
                $t->decimal('remaining_value', 24, 6);
                $t->decimal('realized_value', 24, 6);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_valuation_consumptions')) {
            Schema::create('inv_valuation_consumptions', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('adjustment_id')->index();
                $t->unsignedBigInteger('cost_layer_id')->index();
                $t->string('operation_id', 191)->index();
                $t->decimal('quantity', 24, 6);
                $t->decimal('amount', 24, 6);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_cost_layer_links')) {
            Schema::create('inv_cost_layer_links', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('origin_layer_id')->index();
                $t->unsignedBigInteger('target_layer_id')->index();
                $t->decimal('quantity', 24, 6);
                $t->string('source_type', 60);
                $t->string('source_id', 191);
                $t->timestamps();
                $t->unique(['origin_layer_id', 'target_layer_id', 'source_type', 'source_id'], 'inv_cost_link_source_unique');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Valuation events and cost provenance require a forward migration.');
    }
};
