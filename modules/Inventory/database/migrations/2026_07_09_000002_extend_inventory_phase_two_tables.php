<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_locations', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('warehouse_id')->constrained('inv_locations')->nullOnDelete();
            $table->string('barcode')->nullable()->after('name')->index();
            $table->unsignedInteger('sort_order')->default(0)->after('type');
        });

        Schema::table('inv_items', function (Blueprint $table): void {
            $table->string('barcode')->nullable()->after('sku')->index();
            $table->string('costing_method', 40)->default('fifo')->after('unit');
            $table->boolean('track_batch')->default(false)->after('costing_method');
            $table->boolean('track_serial')->default(false)->after('track_batch');
            $table->decimal('reorder_min', 14, 3)->default(0)->after('sale_price');
            $table->decimal('reorder_max', 14, 3)->default(0)->after('reorder_min');
            $table->string('preferred_supplier')->nullable()->after('reorder_max');
        });

        Schema::create('inv_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('inv_items')->cascadeOnDelete();
            $table->string('batch_code');
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['item_id', 'batch_code']);
            $table->index(['item_id', 'expires_at']);
        });

        Schema::create('inv_serial_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('inv_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inv_batches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inv_warehouses')->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('status', 40)->default('in_stock')->index();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('inv_stock_balances', function (Blueprint $table): void {
            $table->dropUnique('inv_balance_unique_location_item');
            $table->foreignId('batch_id')->nullable()->after('item_id')->constrained('inv_batches')->nullOnDelete();
            $table->unique(['warehouse_id', 'location_id', 'item_id', 'batch_id'], 'inv_balance_unique_location_item_batch');
        });

        Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('item_id')->constrained('inv_batches')->nullOnDelete();
            $table->string('batch_code')->nullable()->after('batch_id');
            $table->date('expires_at')->nullable()->after('batch_code');
            $table->json('serial_numbers')->nullable()->after('expires_at');
        });

        Schema::table('inv_stock_movements', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('item_id')->constrained('inv_batches')->nullOnDelete();
            $table->foreignId('serial_number_id')->nullable()->after('batch_id')->constrained('inv_serial_numbers')->nullOnDelete();
        });

        Schema::create('inv_cost_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('inv_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('inv_warehouses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inv_batches')->nullOnDelete();
            $table->foreignId('document_line_id')->nullable()->constrained('inv_stock_document_lines')->nullOnDelete();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_remaining', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'warehouse_id', 'quantity_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_cost_layers');

        Schema::table('inv_stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('serial_number_id');
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
            $table->dropColumn(['serial_numbers', 'expires_at', 'batch_code']);
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::table('inv_stock_balances', function (Blueprint $table): void {
            $table->dropUnique('inv_balance_unique_location_item_batch');
            $table->dropConstrainedForeignId('batch_id');
            $table->unique(['warehouse_id', 'location_id', 'item_id'], 'inv_balance_unique_location_item');
        });

        Schema::dropIfExists('inv_serial_numbers');
        Schema::dropIfExists('inv_batches');

        Schema::table('inv_items', function (Blueprint $table): void {
            $table->dropColumn(['barcode', 'costing_method', 'track_batch', 'track_serial', 'reorder_min', 'reorder_max', 'preferred_supplier']);
        });

        Schema::table('inv_locations', function (Blueprint $table): void {
            $table->dropColumn(['barcode', 'sort_order']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
