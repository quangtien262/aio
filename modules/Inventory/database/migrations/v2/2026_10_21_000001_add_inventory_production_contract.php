<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inv_warehouses' => ['production_managed', 'boolean'], 'inv_reservations' => ['purpose', 'string']] as $table => [$column, $type]) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column, $type): void {
                    $type === 'boolean' ? $t->boolean($column)->default(false) : $t->string($column, 30)->default('operational');
                });
            }
        }
        if (! Schema::hasColumn('inv_reservations', 'order_ref')) {
            Schema::table('inv_reservations', function (Blueprint $t): void {
                $t->string('order_ref', 191)->nullable()->index();
            });
        }
        if (! Schema::hasColumn('inv_reservations', 'version')) {
            Schema::table('inv_reservations', fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
        }
        if (! Schema::hasTable('inv_trace_units')) {
            Schema::create('inv_trace_units', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('code', 100)->unique();
                $t->unsignedBigInteger('warehouse_id')->index();
                $t->unsignedBigInteger('item_id')->index();
                $t->unsignedBigInteger('batch_id')->unique();
                $t->string('lot_code', 100)->nullable();
                $t->string('stock_owner_ref', 100);
                $t->string('primary_unit', 24);
                $t->decimal('quantity_received', 24, 6);
                $t->string('secondary_unit', 24)->nullable();
                $t->decimal('secondary_received', 24, 6)->nullable();
                $t->decimal('secondary_on_hand', 24, 6)->nullable();
                $t->string('disposition', 30)->default('quarantine');
                $t->unsignedInteger('version')->default(1);
                $t->json('metadata')->nullable();
                $t->string('source_type', 80);
                $t->string('source_id', 191);
                $t->timestamps();
                $t->index(['warehouse_id', 'disposition']);
            });
        }
        if (! Schema::hasTable('inv_production_commands')) {
            Schema::create('inv_production_commands', function (Blueprint $t): void {
                $t->id();
                $t->string('command_key', 191)->unique();
                $t->string('operation', 80);
                $t->unsignedBigInteger('actor_id');
                $t->string('fingerprint', 64);
                $t->json('response');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_production_issues')) {
            Schema::create('inv_production_issues', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('order_ref', 191)->index();
                $t->unsignedBigInteger('reservation_id');
                $t->unsignedBigInteger('warehouse_id');
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('document_id');
                $t->decimal('quantity', 24, 6);
                $t->decimal('total_cost', 24, 6);
                $t->decimal('returned_quantity', 24, 6)->default(0);
                $t->decimal('allocated_quantity', 24, 6)->default(0);
                $t->unsignedInteger('version')->default(1);
                $t->unsignedBigInteger('actor_id');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_production_issue_layers')) {
            Schema::create('inv_production_issue_layers', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('issue_id')->index();
                $t->unsignedBigInteger('cost_layer_id');
                $t->unsignedBigInteger('batch_id')->nullable();
                $t->unsignedBigInteger('location_id')->nullable();
                $t->decimal('quantity', 24, 6);
                $t->decimal('unit_cost', 24, 6);
                $t->decimal('returned_quantity', 24, 6)->default(0);
                $t->decimal('allocated_quantity', 24, 6)->default(0);
                $t->decimal('secondary_quantity', 24, 6)->nullable();
            });
        }
        if (! Schema::hasTable('inv_production_returns')) {
            Schema::create('inv_production_returns', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('issue_id')->index();
                $t->decimal('quantity', 24, 6);
                $t->decimal('total_cost', 24, 6);
                $t->json('receipts');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_production_postings')) {
            Schema::create('inv_production_postings', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('order_ref', 191)->index();
                $t->unsignedBigInteger('warehouse_id');
                $t->decimal('material_cost', 24, 6);
                $t->decimal('conversion_cost', 24, 6);
                $t->decimal('scrap_cost', 24, 6)->default(0);
                $t->decimal('rounding_variance', 24, 6)->default(0);
                $t->json('materials');
                $t->json('outputs');
                $t->unsignedBigInteger('actor_id');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_trace_measurements')) {
            Schema::create('inv_trace_measurements', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('trace_unit_id')->index();
                $t->string('kind', 40);
                $t->decimal('primary_quantity', 24, 6);
                $t->decimal('secondary_quantity', 24, 6)->nullable();
                $t->string('source_id', 191);
                $t->unsignedBigInteger('actor_id');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Production stock and cost provenance require a forward migration.');
    }
};
