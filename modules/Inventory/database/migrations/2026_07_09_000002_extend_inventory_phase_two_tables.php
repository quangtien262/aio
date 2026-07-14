<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendLocations();
        $this->extendItems();
        $this->createBatches();
        $this->createSerialNumbers();
        $this->extendStockBalances();
        $this->extendDocumentLines();
        $this->extendMovements();
        $this->createCostLayers();
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_cost_layers');

        if (Schema::hasTable('inv_stock_movements')) {
            Schema::table('inv_stock_movements', function (Blueprint $table): void {
                if (Schema::hasColumn('inv_stock_movements', 'serial_number_id')) $table->dropConstrainedForeignId('serial_number_id');
                if (Schema::hasColumn('inv_stock_movements', 'batch_id')) $table->dropConstrainedForeignId('batch_id');
            });
        }

        if (Schema::hasTable('inv_stock_document_lines')) {
            Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
                foreach (['serial_numbers', 'expires_at', 'batch_code'] as $column) if (Schema::hasColumn('inv_stock_document_lines', $column)) $table->dropColumn($column);
                if (Schema::hasColumn('inv_stock_document_lines', 'batch_id')) $table->dropConstrainedForeignId('batch_id');
            });
        }

        if (Schema::hasTable('inv_stock_balances')) {
            Schema::table('inv_stock_balances', function (Blueprint $table): void {
                if ($this->hasIndex('inv_stock_balances', 'inv_balance_unique_location_item_batch')) $table->dropUnique('inv_balance_unique_location_item_batch');
                if (Schema::hasColumn('inv_stock_balances', 'batch_id')) $table->dropConstrainedForeignId('batch_id');
                if (! $this->hasIndex('inv_stock_balances', 'inv_balance_unique_location_item')) $table->unique(['warehouse_id', 'location_id', 'item_id'], 'inv_balance_unique_location_item');
                if ($this->hasIndex('inv_stock_balances', 'inv_balance_location_item_lookup')) $table->dropIndex('inv_balance_location_item_lookup');
            });
        }

        Schema::dropIfExists('inv_serial_numbers');
        Schema::dropIfExists('inv_batches');

        if (Schema::hasTable('inv_items')) Schema::table('inv_items', function (Blueprint $table): void { foreach (['barcode', 'costing_method', 'track_batch', 'track_serial', 'reorder_min', 'reorder_max', 'preferred_supplier'] as $column) if (Schema::hasColumn('inv_items', $column)) $table->dropColumn($column); });
        if (Schema::hasTable('inv_locations')) Schema::table('inv_locations', function (Blueprint $table): void { foreach (['barcode', 'sort_order'] as $column) if (Schema::hasColumn('inv_locations', $column)) $table->dropColumn($column); if (Schema::hasColumn('inv_locations', 'parent_id')) $table->dropConstrainedForeignId('parent_id'); });
    }

    private function extendLocations(): void
    {
        Schema::table('inv_locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('inv_locations', 'parent_id')) $table->unsignedBigInteger('parent_id')->nullable()->after('warehouse_id');
            if (! Schema::hasColumn('inv_locations', 'barcode')) $table->string('barcode')->nullable()->after('name');
            if (! Schema::hasColumn('inv_locations', 'sort_order')) $table->unsignedInteger('sort_order')->default(0)->after('type');
        });
        $this->ensureForeignKey('inv_locations', 'parent_id', 'inv_locations', 'inv_locations_parent_id_foreign', 'set null');
        $this->ensureIndex('inv_locations', ['barcode'], 'inv_locations_barcode_index');
    }

    private function extendItems(): void
    {
        Schema::table('inv_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inv_items', 'barcode')) $table->string('barcode')->nullable()->after('sku');
            if (! Schema::hasColumn('inv_items', 'costing_method')) $table->string('costing_method', 40)->default('fifo')->after('unit');
            if (! Schema::hasColumn('inv_items', 'track_batch')) $table->boolean('track_batch')->default(false)->after('costing_method');
            if (! Schema::hasColumn('inv_items', 'track_serial')) $table->boolean('track_serial')->default(false)->after('track_batch');
            if (! Schema::hasColumn('inv_items', 'reorder_min')) $table->decimal('reorder_min', 14, 3)->default(0)->after('sale_price');
            if (! Schema::hasColumn('inv_items', 'reorder_max')) $table->decimal('reorder_max', 14, 3)->default(0)->after('reorder_min');
            if (! Schema::hasColumn('inv_items', 'preferred_supplier')) $table->string('preferred_supplier')->nullable()->after('reorder_max');
        });
        $this->ensureIndex('inv_items', ['barcode'], 'inv_items_barcode_index');
    }

    private function createBatches(): void
    {
        if (! Schema::hasTable('inv_batches')) {
            Schema::create('inv_batches', function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('item_id'); $table->string('batch_code'); $table->date('manufactured_at')->nullable(); $table->date('expires_at')->nullable(); $table->text('note')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }
        $this->ensureForeignKey('inv_batches', 'item_id', 'inv_items', 'inv_batches_item_id_foreign', 'cascade');
        $this->ensureUnique('inv_batches', ['item_id', 'batch_code'], 'inv_batches_item_id_batch_code_unique');
        $this->ensureIndex('inv_batches', ['item_id', 'expires_at'], 'inv_batches_item_id_expires_at_index');
        $this->ensureIndex('inv_batches', ['is_active'], 'inv_batches_is_active_index');
    }

    private function createSerialNumbers(): void
    {
        if (! Schema::hasTable('inv_serial_numbers')) {
            Schema::create('inv_serial_numbers', function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('item_id'); $table->unsignedBigInteger('batch_id')->nullable(); $table->unsignedBigInteger('warehouse_id')->nullable(); $table->string('serial_number'); $table->string('status', 40)->default('in_stock'); $table->timestamp('received_at')->nullable(); $table->timestamp('issued_at')->nullable(); $table->text('note')->nullable(); $table->timestamps();
            });
        }
        $this->ensureForeignKey('inv_serial_numbers', 'item_id', 'inv_items', 'inv_serial_numbers_item_id_foreign', 'cascade');
        $this->ensureForeignKey('inv_serial_numbers', 'batch_id', 'inv_batches', 'inv_serial_numbers_batch_id_foreign', 'set null');
        $this->ensureForeignKey('inv_serial_numbers', 'warehouse_id', 'inv_warehouses', 'inv_serial_numbers_warehouse_id_foreign', 'set null');
        $this->ensureUnique('inv_serial_numbers', ['serial_number'], 'inv_serial_numbers_serial_number_unique');
        $this->ensureIndex('inv_serial_numbers', ['status'], 'inv_serial_numbers_status_index');
    }

    private function extendStockBalances(): void
    {
        if (! Schema::hasColumn('inv_stock_balances', 'batch_id')) Schema::table('inv_stock_balances', fn (Blueprint $table) => $table->unsignedBigInteger('batch_id')->nullable()->after('item_id'));
        if (! $this->hasIndex('inv_stock_balances', 'inv_balance_unique_location_item_batch')) {
            if ($this->hasIndex('inv_stock_balances', 'inv_balance_unique_location_item')) {
                $this->ensureIndex('inv_stock_balances', ['warehouse_id', 'location_id', 'item_id'], 'inv_balance_location_item_lookup');
                Schema::table('inv_stock_balances', fn (Blueprint $table) => $table->dropUnique('inv_balance_unique_location_item'));
            }
            $this->ensureUnique('inv_stock_balances', ['warehouse_id', 'location_id', 'item_id', 'batch_id'], 'inv_balance_unique_location_item_batch');
        }
        $this->ensureForeignKey('inv_stock_balances', 'batch_id', 'inv_batches', 'inv_stock_balances_batch_id_foreign', 'set null');
    }

    private function extendDocumentLines(): void
    {
        Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('inv_stock_document_lines', 'batch_id')) $table->unsignedBigInteger('batch_id')->nullable()->after('item_id');
            if (! Schema::hasColumn('inv_stock_document_lines', 'batch_code')) $table->string('batch_code')->nullable()->after('batch_id');
            if (! Schema::hasColumn('inv_stock_document_lines', 'expires_at')) $table->date('expires_at')->nullable()->after('batch_code');
            if (! Schema::hasColumn('inv_stock_document_lines', 'serial_numbers')) $table->json('serial_numbers')->nullable()->after('expires_at');
        });
        $this->ensureForeignKey('inv_stock_document_lines', 'batch_id', 'inv_batches', 'inv_stock_document_lines_batch_id_foreign', 'set null');
    }

    private function extendMovements(): void
    {
        Schema::table('inv_stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('inv_stock_movements', 'batch_id')) $table->unsignedBigInteger('batch_id')->nullable()->after('item_id');
            if (! Schema::hasColumn('inv_stock_movements', 'serial_number_id')) $table->unsignedBigInteger('serial_number_id')->nullable()->after('batch_id');
        });
        $this->ensureForeignKey('inv_stock_movements', 'batch_id', 'inv_batches', 'inv_stock_movements_batch_id_foreign', 'set null');
        $this->ensureForeignKey('inv_stock_movements', 'serial_number_id', 'inv_serial_numbers', 'inv_stock_movements_serial_number_id_foreign', 'set null');
    }

    private function createCostLayers(): void
    {
        if (! Schema::hasTable('inv_cost_layers')) {
            Schema::create('inv_cost_layers', function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('item_id'); $table->unsignedBigInteger('warehouse_id'); $table->unsignedBigInteger('batch_id')->nullable(); $table->unsignedBigInteger('document_line_id')->nullable(); $table->decimal('quantity_received', 14, 3); $table->decimal('quantity_remaining', 14, 3); $table->decimal('unit_cost', 14, 2)->default(0); $table->timestamp('received_at')->nullable(); $table->timestamps();
            });
        }
        $this->ensureForeignKey('inv_cost_layers', 'item_id', 'inv_items', 'inv_cost_layers_item_id_foreign', 'cascade');
        $this->ensureForeignKey('inv_cost_layers', 'warehouse_id', 'inv_warehouses', 'inv_cost_layers_warehouse_id_foreign', 'cascade');
        $this->ensureForeignKey('inv_cost_layers', 'batch_id', 'inv_batches', 'inv_cost_layers_batch_id_foreign', 'set null');
        $this->ensureForeignKey('inv_cost_layers', 'document_line_id', 'inv_stock_document_lines', 'inv_cost_layers_document_line_id_foreign', 'set null');
        $this->ensureIndex('inv_cost_layers', ['item_id', 'warehouse_id', 'quantity_remaining'], 'inv_cost_layers_item_id_warehouse_id_quantity_remaining_index');
    }

    private function ensureForeignKey(string $tableName, string $column, string $referenceTable, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column) || $this->hasForeignKey($tableName, $name)) return;
        Schema::table($tableName, function (Blueprint $table) use ($column, $referenceTable, $name, $onDelete): void { $foreign = $table->foreign($column, $name)->references('id')->on($referenceTable); $onDelete === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete(); });
    }

    private function ensureIndex(string $tableName, array $columns, string $name): void
    {
        if (! $this->hasIndex($tableName, $name)) Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $name));
    }

    private function ensureUnique(string $tableName, array $columns, string $name): void
    {
        if (! $this->hasIndex($tableName, $name)) Schema::table($tableName, fn (Blueprint $table) => $table->unique($columns, $name));
    }

    private function hasIndex(string $tableName, string $name): bool
    {
        return Schema::hasTable($tableName) && collect(Schema::getIndexes($tableName))->contains(fn (array $index): bool => ($index['name'] ?? '') === $name);
    }

    private function hasForeignKey(string $tableName, string $name): bool
    {
        return collect(Schema::getForeignKeys($tableName))->contains(fn (array $foreign): bool => ($foreign['name'] ?? '') === $name);
    }
};
