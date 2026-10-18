<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_integration_mappings')) {
            Schema::create('fnb_integration_mappings', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->unsignedBigInteger('outlet_id');
                $table->string('destination', 40);
                $table->boolean('enabled')->default(false);
                $table->json('config');
                $table->boolean('automatic')->default(false);
                $table->foreignId('authorized_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->unsignedInteger('authorized_auth_version')->nullable();
                $table->string('automatic_paused_reason', 80)->nullable();
                $table->timestamp('last_dispatched_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'destination'], 'fnb_integration_mapping_uq');
                $table->foreign(['website_key', 'outlet_id'])->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('fnb_integration_stock_lines')) {
            Schema::create('fnb_integration_stock_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consumption_line_id')->unique();
                $table->unsignedBigInteger('inventory_document_id');
                $table->unsignedBigInteger('inventory_document_line_id');
                $table->unsignedBigInteger('inventory_item_id');
                $table->unsignedBigInteger('warehouse_id');
                $table->string('unit', 30);
                $table->decimal('factor', 18, 6);
                $table->decimal('quantity', 18, 6);
                $table->decimal('unit_cost', 18, 2);
                $table->timestamp('created_at');
                $table->foreign('consumption_line_id')->references('id')->on('fnb_stock_consumption_lines')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('fnb_integration_item_mappings')) {
            Schema::create('fnb_integration_item_mappings', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->unsignedBigInteger('outlet_id');
                $table->string('destination', 40);
                $table->string('source_type', 30);
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('destination_id');
                $table->string('unit', 30)->nullable();
                $table->decimal('factor', 18, 6)->default(1);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'destination', 'source_type', 'source_id'], 'fnb_integration_item_uq');
                $table->foreign(['website_key', 'outlet_id'])->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
            });
        }
        if (Schema::hasTable('fnb_outbox_deliveries') && ! Schema::hasIndex('fnb_outbox_deliveries', 'fnb_delivery_scope_claim_idx')) {
            Schema::table('fnb_outbox_deliveries', fn (Blueprint $table) => $table->index(['website_key', 'outlet_id', 'destination', 'status', 'available_at', 'id'], 'fnb_delivery_scope_claim_idx'));
        }
        if (! Schema::hasTable('fnb_bank_accounts')) {
            Schema::create('fnb_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->unsignedBigInteger('outlet_id');
                $table->string('bank_code', 20)->default('TPB');
                $table->text('account_number');
                $table->text('account_name');
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id'], 'fnb_bank_account_uq');
                $table->foreign(['website_key', 'outlet_id'])->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('fnb_bank_payment_intents')) {
            Schema::create('fnb_bank_payment_intents', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->unsignedBigInteger('outlet_id');
                $table->unsignedBigInteger('check_id');
                $table->unsignedBigInteger('account_id');
                $table->string('reference', 40)->unique();
                $table->string('idempotency_key', 120);
                $table->char('fingerprint', 64);
                $table->bigInteger('amount_minor');
                $table->string('currency', 3);
                $table->string('status', 30)->default('pending');
                $table->text('qr_payload');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'idempotency_key'], 'fnb_bank_intent_key_uq');
                $table->foreign(['website_key', 'outlet_id', 'check_id'])->references(['website_key', 'outlet_id', 'id'])->on('fnb_checks')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fnb_outbox_deliveries') && Schema::hasIndex('fnb_outbox_deliveries', 'fnb_delivery_scope_claim_idx')) {
            Schema::table('fnb_outbox_deliveries', fn (Blueprint $table) => $table->dropIndex('fnb_delivery_scope_claim_idx'));
        }
        foreach (['fnb_bank_payment_intents', 'fnb_bank_accounts', 'fnb_integration_stock_lines', 'fnb_integration_item_mappings', 'fnb_integration_mappings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
