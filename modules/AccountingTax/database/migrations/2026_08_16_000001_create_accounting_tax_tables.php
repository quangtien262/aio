<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tax_code', 32)->nullable()->index();
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('default_currency', 3)->default('VND');
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('acct_organization_websites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->cascadeOnDelete();
            $table->string('website_key')->index();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'website_key'], 'acct_org_websites_org_website_unique');
        });

        Schema::create('acct_parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->cascadeOnDelete();
            $table->string('type', 30)->default('customer')->index();
            $table->string('name');
            $table->string('tax_code', 32)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'type'], 'acct_parties_org_type_idx');
        });

        Schema::create('acct_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->cascadeOnDelete();
            $table->string('kind', 30)->default('goods')->index();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit', 40)->default('pcs');
            $table->decimal('default_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('tax_category', 50)->default('vat');
            $table->string('revenue_account', 50)->nullable();
            $table->string('expense_account', 50)->nullable();
            $table->boolean('is_stock_tracked')->default(false)->index();
            $table->string('status', 30)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'sku'], 'acct_items_org_sku_idx');
        });

        Schema::create('acct_item_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_item_id')->constrained('acct_items')->cascadeOnDelete();
            $table->string('source_module', 50);
            $table->string('source_type', 80);
            $table->string('source_id', 80);
            $table->string('source_key')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_hash', 64);
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_status', 30)->default('synced')->index();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->unique(['source_module', 'source_type', 'source_id'], 'acct_item_sources_source_unique');
        });

        Schema::create('acct_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('acct_parties')->nullOnDelete();
            $table->string('direction', 20)->index();
            $table->string('document_type', 50)->default('internal_invoice')->index();
            $table->string('document_no')->nullable();
            $table->date('document_date')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('VND');
            $table->string('workflow_status', 30)->default('draft')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('legal_status', 30)->default('not_due')->index();
            $table->string('inventory_status', 30)->default('not_applicable')->index();
            $table->string('mail_status', 30)->default('not_sent')->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->string('website_key')->nullable()->index();
            $table->string('source_module', 50)->nullable();
            $table->string('source_type', 80)->nullable();
            $table->string('source_id', 80)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'document_no'], 'acct_documents_org_no_unique');
        });

        Schema::create('acct_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('acct_documents')->cascadeOnDelete();
            $table->foreignId('accounting_item_id')->nullable()->constrained('acct_items')->nullOnDelete();
            $table->string('line_type', 30)->default('item')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('item_kind', 30)->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit', 40)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('acct_external_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('acct_documents')->nullOnDelete();
            $table->string('provider', 50)->index();
            $table->string('direction', 20)->index();
            $table->string('provider_invoice_id')->index();
            $table->string('seller_tax_code', 32)->nullable()->index();
            $table->string('buyer_tax_code', 32)->nullable()->index();
            $table->string('invoice_series', 40)->nullable();
            $table->string('invoice_number', 80)->nullable();
            $table->date('invoice_date')->nullable()->index();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->string('provider_status', 50)->nullable()->index();
            $table->string('reconciliation_status', 30)->default('unmatched')->index();
            $table->string('xml_path')->nullable();
            $table->string('html_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('content_checksum', 64)->nullable();
            $table->json('warnings')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_invoice_id'], 'acct_external_provider_id_unique');
        });

        Schema::create('acct_einvoice_transmissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('acct_documents')->cascadeOnDelete();
            $table->string('provider', 50)->index();
            $table->string('operation', 50)->index();
            $table->string('operation_key')->unique();
            $table->string('provider_document_id')->nullable()->index();
            $table->string('provider_status', 50)->nullable()->index();
            $table->string('legal_status', 50)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->json('request_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('acct_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->cascadeOnDelete();
            $table->string('report_type', 80)->index();
            $table->string('format', 20)->default('csv')->index();
            $table->string('status', 30)->default('queued')->index();
            $table->json('filters')->nullable();
            $table->string('artifact_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('acct_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('acct_documents')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('template_key')->nullable();
            $table->string('status', 30)->default('queued')->index();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acct_email_deliveries');
        Schema::dropIfExists('acct_exports');
        Schema::dropIfExists('acct_einvoice_transmissions');
        Schema::dropIfExists('acct_external_invoices');
        Schema::dropIfExists('acct_document_lines');
        Schema::dropIfExists('acct_documents');
        Schema::dropIfExists('acct_item_sources');
        Schema::dropIfExists('acct_items');
        Schema::dropIfExists('acct_parties');
        Schema::dropIfExists('acct_organization_websites');
        Schema::dropIfExists('acct_organizations');
    }
};
