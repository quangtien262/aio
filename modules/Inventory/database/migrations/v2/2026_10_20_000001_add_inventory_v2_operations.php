<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_warehouses', function (Blueprint $t): void {
            if (! Schema::hasColumn('inv_warehouses', 'public_id')) {
                $t->uuid('public_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('inv_warehouses', 'catalog_id')) {
                $t->unsignedBigInteger('catalog_id')->nullable()->index();
            }
            if (! Schema::hasColumn('inv_warehouses', 'legal_entity_ref')) {
                $t->string('legal_entity_ref', 100)->nullable()->index();
            }
            if (! Schema::hasColumn('inv_warehouses', 'branch_id')) {
                $t->unsignedBigInteger('branch_id')->nullable()->index();
            }
            if (! Schema::hasColumn('inv_warehouses', 'v2_managed')) {
                $t->boolean('v2_managed')->default(false);
            }
            if (! Schema::hasColumn('inv_warehouses', 'timezone')) {
                $t->string('timezone', 64)->default('UTC');
            }
        });
        Schema::table('inv_items', function (Blueprint $t): void {
            if (! Schema::hasColumn('inv_items', 'merchandise_sku_id')) {
                $t->unsignedBigInteger('merchandise_sku_id')->nullable()->unique();
            }
        });
        Schema::table('inv_cost_layers', function (Blueprint $t): void {
            if (! Schema::hasColumn('inv_cost_layers', 'location_id')) {
                $t->unsignedBigInteger('location_id')->nullable()->index();
            }
        });
        // Optional Catalog records are linked through adapters, never required by Inventory DDL.
        foreach (['inv_items', 'inv_sync_run_lines'] as $table) {
            foreach (Schema::getForeignKeys($table) as $foreign) {
                if (in_array('catalog_product_id', $foreign['columns'] ?? [], true)) {
                    $reference = DB::getDriverName() === 'sqlite' ? $foreign['columns'] : $foreign['name'];
                    Schema::table($table, fn (Blueprint $t) => $t->dropForeign($reference));
                }
            }
        }
        foreach (['inv_stock_balances' => ['quantity_on_hand', 'quantity_reserved'], 'inv_stock_document_lines' => ['quantity'], 'inv_stock_movements' => ['quantity_delta', 'balance_after'], 'inv_cost_layers' => ['quantity_received', 'quantity_remaining']] as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns): void {
                foreach ($columns as $column) {
                    $t->decimal($column, 24, 6)->change();
                }
            });
        }
        foreach (['inv_stock_document_lines', 'inv_stock_movements', 'inv_cost_layers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->decimal('unit_cost', 24, 6)->default(0)->change());
        }
        if (! Schema::hasTable('inv_v2_operations')) {
            Schema::create('inv_v2_operations', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('idempotency_key', 191)->unique();
                $t->string('fingerprint', 64);
                $t->string('kind', 50);
                $t->unsignedBigInteger('actor_id');
                $t->string('source_id', 191)->nullable()->index();
                $t->json('response')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_reservations')) {
            Schema::create('inv_reservations', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('warehouse_id')->index();
                $t->unsignedBigInteger('item_id')->index();
                $t->decimal('quantity', 24, 6);
                $t->string('source_id', 191)->index();
                $t->unsignedBigInteger('actor_id');
                $t->string('status', 30)->index();
                $t->boolean('protected_payment')->default(false);
                $t->timestamp('expires_at');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_reservation_allocations')) {
            Schema::create('inv_reservation_allocations', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('reservation_id')->index();
                $t->unsignedBigInteger('balance_id');
                $t->decimal('quantity', 24, 6);
                $t->unique(['reservation_id', 'balance_id']);
            });
        }
        if (! Schema::hasTable('inv_transfers')) {
            Schema::create('inv_transfers', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('source_warehouse_id');
                $t->unsignedBigInteger('destination_warehouse_id');
                $t->string('legal_entity_ref', 100);
                $t->string('status', 30);
                $t->unsignedInteger('version')->default(1);
                $t->unsignedBigInteger('actor_id');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_transfer_lines')) {
            Schema::create('inv_transfer_lines', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('transfer_id')->index();
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('batch_id')->nullable();
                $t->decimal('quantity', 24, 6);
                $t->decimal('received_quantity', 24, 6)->default(0);
                $t->decimal('unit_cost', 24, 6);
            });
        }
        if (! Schema::hasTable('inv_stocktakes')) {
            Schema::create('inv_stocktakes', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('warehouse_id')->index();
                $t->string('status', 30)->index();
                $t->unsignedBigInteger('maker_id');
                $t->unsignedBigInteger('checker_id')->nullable();
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_stocktake_lines')) {
            Schema::create('inv_stocktake_lines', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('stocktake_id')->index();
                $t->unsignedBigInteger('balance_id');
                $t->decimal('expected_quantity', 24, 6);
                $t->decimal('counted_quantity', 24, 6)->nullable();
                $t->unique(['stocktake_id', 'balance_id']);
            });
        }
        if (! Schema::hasTable('inv_escrows')) {
            Schema::create('inv_escrows', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('gateway_id', 100);
                $t->unsignedBigInteger('epoch');
                $t->unsignedBigInteger('warehouse_id');
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('reservation_id');
                $t->decimal('quantity', 24, 6);
                $t->decimal('consumed_quantity', 24, 6)->default(0);
                $t->unsignedBigInteger('last_sequence')->default(0);
                $t->string('status', 30);
                $t->timestamps();
                $t->index(['gateway_id', 'epoch']);
            });
        }
    }

    public function down(): void
    {
        if (DB::table('inv_v2_operations')->exists()) {
            throw new RuntimeException('Inventory v2 history cannot be rolled back; use a forward migration.');
        }
        foreach (['inv_escrows', 'inv_stocktake_lines', 'inv_stocktakes', 'inv_transfer_lines', 'inv_transfers', 'inv_reservation_allocations', 'inv_reservations', 'inv_v2_operations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
