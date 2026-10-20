<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inv_warehouses')) {
            Schema::create('inv_warehouses', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inv_locations')) {
            Schema::create('inv_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('inv_warehouses')->cascadeOnDelete();
                $table->string('code', 80);
                $table->string('name');
                $table->string('type', 40)->default('storage')->index();
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique(['warehouse_id', 'code']);
            });
        }

        if (! Schema::hasTable('inv_items')) {
            Schema::create('inv_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('catalog_product_id')->nullable();
                $table->string('sku')->nullable()->index();
                $table->string('name');
                $table->string('unit', 40)->default('pcs');
                $table->decimal('sale_price', 12, 2)->default(0);
                $table->string('image_url')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('sync_snapshot')->nullable();
                $table->timestamps();
                $table->unique('catalog_product_id');
            });
        }

        if (! Schema::hasTable('inv_stock_balances')) {
            Schema::create('inv_stock_balances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('inv_warehouses')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('inv_locations')->nullOnDelete();
                $table->foreignId('item_id')->constrained('inv_items')->cascadeOnDelete();
                $table->decimal('quantity_on_hand', 14, 3)->default(0);
                $table->decimal('quantity_reserved', 14, 3)->default(0);
                $table->timestamp('last_movement_at')->nullable();
                $table->timestamps();
                $table->unique(['warehouse_id', 'location_id', 'item_id'], 'inv_balance_unique_location_item');
                $table->index(['item_id', 'warehouse_id']);
            });
        }

        if (! Schema::hasTable('inv_stock_documents')) {
            Schema::create('inv_stock_documents', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('type', 40)->index();
                $table->string('status', 40)->default('posted')->index();
                $table->foreignId('source_warehouse_id')->nullable()->constrained('inv_warehouses')->nullOnDelete();
                $table->foreignId('destination_warehouse_id')->nullable()->constrained('inv_warehouses')->nullOnDelete();
                $table->string('reference')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inv_stock_document_lines')) {
            Schema::create('inv_stock_document_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('document_id')->constrained('inv_stock_documents')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
                $table->decimal('quantity', 14, 3);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inv_stock_movements')) {
            Schema::create('inv_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
                $table->foreignId('warehouse_id')->constrained('inv_warehouses')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('inv_locations')->nullOnDelete();
                $table->foreignId('document_id')->nullable()->constrained('inv_stock_documents')->nullOnDelete();
                $table->foreignId('document_line_id')->nullable()->constrained('inv_stock_document_lines')->nullOnDelete();
                $table->string('type', 40)->index();
                $table->decimal('quantity_delta', 14, 3);
                $table->decimal('balance_after', 14, 3)->default(0);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->string('reference')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
                $table->index(['warehouse_id', 'item_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('inv_sync_runs')) {
            Schema::create('inv_sync_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 40)->default('catalog_products')->index();
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inv_sync_run_lines')) {
            Schema::create('inv_sync_run_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sync_run_id')->constrained('inv_sync_runs')->cascadeOnDelete();
                $table->unsignedBigInteger('catalog_product_id')->nullable();
                $table->foreignId('item_id')->nullable()->constrained('inv_items')->nullOnDelete();
                $table->string('sku')->nullable();
                $table->string('name')->nullable();
                $table->string('action', 40)->index();
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
        (require __DIR__.'/../2026_07_09_000002_extend_inventory_phase_two_tables.php')->up();
        Schema::table('inv_stock_balances', function (Blueprint $table): void {
            if (! Schema::hasColumn('inv_stock_balances', 'location_key')) {
                $table->unsignedBigInteger('location_key')->default(0);
            }
            if (! Schema::hasColumn('inv_stock_balances', 'batch_key')) {
                $table->unsignedBigInteger('batch_key')->default(0);
            }
        });
        DB::table('inv_stock_balances')->update(['location_key' => DB::raw('COALESCE(location_id, 0)'), 'batch_key' => DB::raw('COALESCE(batch_id, 0)')]);
        $indexes = collect(Schema::getIndexes('inv_stock_balances'))->pluck('name');
        if (! $indexes->contains('inv_balance_unique_scope_item_batch')) {
            Schema::table('inv_stock_balances', fn (Blueprint $table) => $table->unique(['warehouse_id', 'location_key', 'item_id', 'batch_key'], 'inv_balance_unique_scope_item_batch'));
        }
        if ($indexes->contains('inv_balance_unique_location_item_batch')) {
            Schema::table('inv_stock_balances', fn (Blueprint $table) => $table->dropUnique('inv_balance_unique_location_item_batch'));
        }
        Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('inv_stock_document_lines', 'source_location_id')) {
                $table->foreignId('source_location_id')->nullable()->constrained('inv_locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('inv_stock_document_lines', 'destination_location_id')) {
                $table->foreignId('destination_location_id')->nullable()->constrained('inv_locations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        (require __DIR__.'/../2026_07_09_000001_create_inventory_module_tables.php')->down();
    }
};
