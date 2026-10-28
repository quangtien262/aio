<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('ph_receipts', function (Blueprint $t): void {
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('batch_id');
            $t->decimal('quantity', 20, 6);
            $t->decimal('unit_cost', 20, 6);
            $t->string('stock_operation_id', 100);
            $t->string('reference', 150)->nullable();
            $t->string('supplier_reference', 150)->nullable();
            $t->unsignedBigInteger('purchase_receipt_id')->nullable();
            $t->unsignedBigInteger('actor_id');
            $t->unsignedBigInteger('inspected_by')->nullable();
            $t->timestamp('inspected_at')->nullable();
            $t->text('inspection_reason')->nullable();
        }, 'quarantine');
        $this->table('ph_supplier_links', function (Blueprint $t): void {
            $t->unsignedBigInteger('supplier_id');
            $t->string('license_number');
            $t->timestamp('valid_until');
            $t->unsignedBigInteger('verified_by');
            $t->unique(['chain_id', 'supplier_id']);
        });
        $this->table('ph_purchase_links', function (Blueprint $t): void {
            $t->unsignedBigInteger('order_id')->unique();
            $t->string('order_public_id', 100);
            $t->unsignedBigInteger('supplier_id');
        }, 'draft');
        $this->table('ph_transfers', function (Blueprint $t): void {
            $t->unsignedBigInteger('destination_pharmacy_id');
            $t->string('inventory_transfer_id', 100)->nullable();
            $t->json('lines');
            $t->text('reason');
            $t->unsignedBigInteger('actor_id');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('dispatched_by')->nullable();
        }, 'draft');
        $this->table('ph_stocktakes', function (Blueprint $t): void {
            $t->string('inventory_stocktake_id', 100);
            $t->string('zone', 100)->nullable();
            $t->text('reason');
            $t->unsignedBigInteger('actor_id');
        }, 'counting');
        $this->table('ph_print_jobs', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id');
            $t->string('kind', 30);
            $t->string('source_id', 100);
            $t->unsignedInteger('copies')->default(1);
            $t->string('path');
            $t->char('checksum', 64);
            $t->timestamp('expires_at');
            $t->unsignedInteger('download_count')->default(0);
        }, 'requested');
        $this->table('ph_import_batches', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id');
            $t->string('kind', 40);
            $t->longText('encrypted_rows');
            $t->char('checksum', 64);
            $t->json('errors');
            $t->unsignedInteger('row_count');
            $t->timestamp('expires_at');
            $t->timestamp('committed_at')->nullable();
        }, 'preview');
        $this->table('ph_saved_filters', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id');
            $t->string('name', 100);
            $t->string('resource', 50);
            $t->json('filters');
            $t->unique(['actor_id', 'pharmacy_id', 'resource', 'name']);
        });
    }

    private function table(string $name, callable $columns, string $status = 'active'): void
    {
        Schema::create($name, function (Blueprint $t) use ($columns, $status): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('chain_id')->constrained('ph_chains')->restrictOnDelete();
            $t->foreignId('pharmacy_id')->constrained('ph_pharmacies')->restrictOnDelete();
            $columns($t);
            $t->string('status', 40)->default($status);
            $t->unsignedBigInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ph_saved_filters', 'ph_import_batches', 'ph_print_jobs', 'ph_stocktakes', 'ph_transfers', 'ph_purchase_links', 'ph_supplier_links', 'ph_receipts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
