<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_legal_entities', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('name');
            $t->string('tax_code')->nullable();
            $t->timestamps();
        });
        Schema::create('retail_chains', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('name');
            $t->unsignedBigInteger('catalog_id')->index();
            $t->string('website_key')->index();
            $t->timestamps();
        });
        Schema::create('retail_branches', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('chain_id')->constrained('retail_chains')->restrictOnDelete();
            $t->foreignId('legal_entity_id')->constrained('retail_legal_entities')->restrictOnDelete();
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->string('code', 60);
            $t->string('name');
            $t->string('timezone')->default('Asia/Ho_Chi_Minh');
            $t->string('status')->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['chain_id', 'code']);
        });
        Schema::create('retail_memberships', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $t->foreignId('chain_id')->constrained('retail_chains')->restrictOnDelete();
            $t->unsignedBigInteger('branch_id')->default(0);
            $t->string('preset')->default('cashier');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['admin_id', 'chain_id', 'branch_id']);
        });
        Schema::create('retail_terminals', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('branch_id')->constrained('retail_branches')->restrictOnDelete();
            $t->string('code', 60);
            $t->string('name');
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['branch_id', 'code']);
        });
        Schema::create('retail_assortments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('branch_id')->constrained('retail_branches')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id');
            $t->bigInteger('price_minor');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['branch_id', 'sku_id']);
        });
        Schema::create('retail_customers', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('chain_id')->constrained('retail_chains')->restrictOnDelete();
            $t->string('name');
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('status')->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('retail_shifts', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('branch_id')->constrained('retail_branches')->restrictOnDelete();
            $t->foreignId('terminal_id')->constrained('retail_terminals')->restrictOnDelete();
            $t->unsignedBigInteger('admin_id');
            $t->date('business_date');
            $t->string('status')->default('open');
            $t->bigInteger('opening_minor');
            $t->bigInteger('expected_minor');
            $t->bigInteger('counted_minor')->nullable();
            $t->bigInteger('variance_minor')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('reconciled_at')->nullable();
            $t->timestamps();
            $t->index(['terminal_id', 'status']);
        });
        Schema::create('retail_sales', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('branch_id')->constrained('retail_branches')->restrictOnDelete();
            $t->unsignedBigInteger('legal_entity_id');
            $t->foreignId('terminal_id')->constrained('retail_terminals')->restrictOnDelete();
            $t->foreignId('shift_id')->constrained('retail_shifts')->restrictOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('actor_id');
            $t->string('status')->default('priced');
            $t->string('currency', 3)->default('VND');
            $t->bigInteger('subtotal_minor');
            $t->bigInteger('discount_minor')->default(0);
            $t->bigInteger('total_minor');
            $t->bigInteger('refunded_minor')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['branch_id', 'status', 'created_at']);
        });
        Schema::create('retail_sale_lines', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('sale_id')->constrained('retail_sales')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id');
            $t->unsignedBigInteger('inventory_item_id');
            $t->string('sku');
            $t->string('name');
            $t->string('unit');
            $t->decimal('quantity', 20, 6);
            $t->decimal('returned_quantity', 20, 6)->default(0);
            $t->bigInteger('price_minor');
            $t->bigInteger('gross_minor');
            $t->bigInteger('discount_minor')->default(0);
            $t->bigInteger('total_minor');
            $t->bigInteger('refunded_minor')->default(0);
            $t->decimal('cost', 24, 6)->nullable();
            $t->json('stock_allocations')->nullable();
            $t->timestamps();
        });
        Schema::create('retail_tenders', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('sale_id')->constrained('retail_sales')->restrictOnDelete();
            $t->string('method');
            $t->string('status');
            $t->bigInteger('amount_minor');
            $t->bigInteger('tendered_minor')->nullable();
            $t->bigInteger('change_minor')->default(0);
            $t->string('reference')->nullable();
            $t->timestamps();
        });
        Schema::create('retail_returns', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('sale_id')->constrained('retail_sales')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained('retail_branches')->restrictOnDelete();
            $t->unsignedBigInteger('actor_id');
            $t->string('status')->default('posted');
            $t->bigInteger('amount_minor');
            $t->text('reason');
            $t->string('refund_status')->default('pending');
            $t->timestamps();
        });
        Schema::create('retail_return_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('return_id')->constrained('retail_returns')->restrictOnDelete();
            $t->foreignId('sale_line_id')->constrained('retail_sale_lines')->restrictOnDelete();
            $t->decimal('quantity', 20, 6);
            $t->bigInteger('amount_minor');
            $t->string('disposition');
            $t->timestamps();
        });
        Schema::create('retail_cash_movements', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('shift_id')->constrained('retail_shifts')->restrictOnDelete();
            $t->string('kind');
            $t->bigInteger('amount_minor');
            $t->string('source_id')->unique();
            $t->text('reason')->nullable();
            $t->unsignedBigInteger('actor_id');
            $t->timestamps();
        });
        Schema::create('retail_commands', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('actor_id');
            $t->string('website_key')->index();
            $t->string('command_key', 120);
            $t->string('operation', 80);
            $t->string('fingerprint', 64);
            $t->json('intent');
            $t->json('response')->nullable();
            $t->timestamps();
            $t->unique(['actor_id', 'command_key']);
        });
        Schema::create('retail_outbox', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('event_type');
            $t->string('source_id');
            $t->unsignedBigInteger('branch_id');
            $t->json('payload');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('processed_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['event_type', 'source_id']);
        });
    }

    public function down(): void
    {
        foreach (['retail_outbox', 'retail_commands', 'retail_cash_movements', 'retail_return_lines', 'retail_returns', 'retail_tenders', 'retail_sale_lines', 'retail_sales', 'retail_shifts', 'retail_customers', 'retail_assortments', 'retail_terminals', 'retail_memberships', 'retail_branches', 'retail_chains', 'retail_legal_entities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
