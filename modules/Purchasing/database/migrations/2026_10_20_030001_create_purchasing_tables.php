<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_suppliers', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('code', 60)->unique();
            $t->string('name');
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('code', 80)->unique();
            $t->foreignId('supplier_id')->constrained('purchase_suppliers')->restrictOnDelete();
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('catalog_id');
            $t->string('legal_entity_ref', 100);
            $t->string('status', 30)->default('draft')->index();
            $t->string('currency', 3)->default('VND');
            $t->unsignedBigInteger('created_by_admin_id');
            $t->unsignedBigInteger('approved_by_admin_id')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->date('expected_at')->nullable();
            $t->date('due_at')->nullable();
            $t->text('note')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['legal_entity_ref', 'status']);
        });
        Schema::create('purchase_order_lines', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('order_id')->constrained('purchase_orders')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id');
            $t->unsignedBigInteger('inventory_item_id');
            $t->string('sku_snapshot', 80);
            $t->string('name_snapshot');
            $t->string('unit_snapshot', 24);
            $t->decimal('quantity_ordered', 24, 6);
            $t->decimal('quantity_received', 24, 6)->default(0);
            $t->decimal('quantity_returned', 24, 6)->default(0);
            $t->decimal('unit_cost', 24, 6);
            $t->timestamps();
            $t->unique(['order_id', 'sku_id']);
        });
        Schema::create('purchase_receipts', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('order_id')->constrained('purchase_orders')->restrictOnDelete();
            $t->unsignedBigInteger('actor_id');
            $t->string('reference', 100)->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_receipt_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('receipt_id')->constrained('purchase_receipts')->restrictOnDelete();
            $t->foreignId('order_line_id')->constrained('purchase_order_lines')->restrictOnDelete();
            $t->decimal('quantity', 24, 6);
            $t->decimal('quantity_returned', 24, 6)->default(0);
            $t->decimal('unit_cost', 24, 6);
            $t->bigInteger('amount_minor');
            $t->string('stock_operation_id', 100);
            $t->unsignedBigInteger('batch_id')->nullable();
            $t->string('batch_code')->nullable();
            $t->date('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_returns', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('order_id')->constrained('purchase_orders')->restrictOnDelete();
            $t->foreignId('receipt_line_id')->constrained('purchase_receipt_lines')->restrictOnDelete();
            $t->decimal('quantity', 24, 6);
            $t->bigInteger('amount_minor');
            $t->unsignedBigInteger('actor_id');
            $t->string('stock_operation_id', 100);
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('purchase_payments', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('order_id')->constrained('purchase_orders')->restrictOnDelete();
            $t->bigInteger('amount_minor');
            $t->string('method', 30);
            $t->string('reference', 120)->nullable();
            $t->unsignedBigInteger('actor_id');
            $t->timestamps();
        });
        Schema::create('purchase_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $t->string('action', 60);
            $t->unsignedBigInteger('actor_id');
            $t->json('payload');
            $t->timestamp('created_at');
        });
        Schema::create('purchase_commands', function (Blueprint $t): void {
            $t->id();
            $t->string('command_key', 120)->unique();
            $t->string('fingerprint', 64);
            $t->string('action', 60);
            $t->unsignedBigInteger('actor_id');
            $t->json('response')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['purchase_commands', 'purchase_events', 'purchase_payments', 'purchase_returns', 'purchase_receipt_lines', 'purchase_receipts', 'purchase_order_lines', 'purchase_orders', 'purchase_suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
