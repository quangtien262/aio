<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('inv_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_product_id')->nullable()->constrained('catalog_products')->nullOnDelete();
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

        Schema::create('inv_stock_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('inv_stock_documents')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

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

        Schema::create('inv_sync_run_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_run_id')->constrained('inv_sync_runs')->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->nullable()->constrained('catalog_products')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('inv_items')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('name')->nullable();
            $table->string('action', 40)->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_sync_run_lines');
        Schema::dropIfExists('inv_sync_runs');
        Schema::dropIfExists('inv_stock_movements');
        Schema::dropIfExists('inv_stock_document_lines');
        Schema::dropIfExists('inv_stock_documents');
        Schema::dropIfExists('inv_stock_balances');
        Schema::dropIfExists('inv_items');
        Schema::dropIfExists('inv_locations');
        Schema::dropIfExists('inv_warehouses');
    }
};
