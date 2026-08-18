<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct_provider_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('provider', 50)->default('minvoice')->index();
            $table->string('channel', 20)->index();
            $table->string('environment', 20)->default('sandbox')->index();
            $table->string('base_url');
            $table->text('credentials')->nullable();
            $table->json('allowed_hosts')->nullable();
            $table->json('settings')->nullable();
            $table->string('readiness_state', 30)->default('installed')->index();
            $table->string('health_status', 30)->default('unknown')->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('kill_switch')->default(false)->index();
            $table->timestamp('configured_at')->nullable();
            $table->timestamp('sandbox_verified_at')->nullable();
            $table->timestamp('healthy_at')->nullable();
            $table->timestamp('production_allowed_at')->nullable();
            $table->timestamp('last_health_checked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'provider', 'channel', 'environment', 'name'],
                'acct_provider_connections_identity_unique',
            );
        });

        Schema::create('acct_provider_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('acct_provider_connections')->cascadeOnDelete();
            $table->string('provider_series_id')->nullable();
            $table->string('series', 80);
            $table->string('invoice_form', 40)->nullable();
            $table->string('invoice_year', 10)->nullable();
            $table->string('invoice_type_name')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'series'], 'acct_provider_series_connection_series_unique');
        });

        Schema::table('acct_einvoice_transmissions', function (Blueprint $table): void {
            $table->foreignId('connection_id')
                ->nullable()
                ->after('document_id')
                ->constrained('acct_provider_connections')
                ->restrictOnDelete();
            $table->string('status', 30)->default('queued')->after('operation_key')->index();
            $table->timestamp('next_attempt_at')->nullable()->after('attempt_count')->index();
            $table->timestamp('completed_at')->nullable()->after('sent_at');
            $table->string('pdf_checksum', 64)->nullable()->after('checksum');
            $table->string('xml_checksum', 64)->nullable()->after('pdf_checksum');
        });

        Schema::table('acct_external_invoices', function (Blueprint $table): void {
            $table->dropUnique('acct_external_provider_id_unique');
            $table->foreignId('connection_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('acct_provider_connections')
                ->restrictOnDelete();
            $table->string('provider_tax_id')->nullable()->after('provider_invoice_id');
            $table->string('provider_type', 30)->nullable()->after('provider_tax_id');
            $table->string('seller_name')->nullable()->after('seller_tax_code');
            $table->string('seller_address')->nullable()->after('seller_name');
            $table->string('buyer_name')->nullable()->after('buyer_tax_code');
            $table->string('template_code', 40)->nullable()->after('buyer_name');
            $table->string('invoice_code', 100)->nullable()->after('invoice_number');
            $table->string('currency', 3)->default('VND')->after('invoice_date');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            $table->decimal('subtotal_ex_vat', 18, 2)->default(0)->after('exchange_rate');
            $table->decimal('non_taxable_amount', 18, 2)->default(0)->after('tax_amount');
            $table->decimal('discount_amount', 18, 2)->default(0)->after('non_taxable_amount');
            $table->decimal('fee_amount', 18, 2)->default(0)->after('discount_amount');
            $table->decimal('other_amount', 18, 2)->default(0)->after('fee_amount');
            $table->string('invoice_status_code', 20)->nullable()->after('provider_status')->index();
            $table->string('processing_status_code', 20)->nullable()->after('invoice_status_code')->index();
            $table->string('illegal_status', 20)->nullable()->after('processing_status_code')->index();
            $table->text('illegal_reason')->nullable()->after('illegal_status');
            $table->string('duplicate_status', 20)->nullable()->after('illegal_reason')->index();
            $table->timestamp('issued_at')->nullable()->after('invoice_date')->index();
            $table->timestamp('tax_authority_code_issued_at')->nullable()->after('issued_at');
            $table->timestamp('tax_authority_received_at')->nullable()->after('tax_authority_code_issued_at');
            $table->timestamp('provider_updated_at')->nullable()->after('tax_authority_received_at');
            $table->string('xml_checksum', 64)->nullable()->after('content_checksum');
            $table->string('html_checksum', 64)->nullable()->after('xml_checksum');
            $table->json('vat_breakdown')->nullable()->after('warnings');
            $table->json('warning_payload')->nullable()->after('vat_breakdown');
            $table->string('sync_status', 30)->default('synced')->after('warning_payload')->index();

            $table->unique(
                ['connection_id', 'provider_invoice_id'],
                'acct_external_connection_provider_id_unique',
            );
        });

        Schema::create('acct_external_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_invoice_id')->constrained('acct_external_invoices')->cascadeOnDelete();
            $table->string('provider_line_id')->nullable();
            $table->string('provider_header_id')->nullable();
            $table->unsignedInteger('line_no')->default(0);
            $table->string('item_name');
            $table->string('unit', 40)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('subtotal_ex_vat', 18, 2)->default(0);
            $table->string('vat_rate', 20)->nullable();
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('discount_rate', 8, 4)->default(0);
            $table->string('line_type', 30)->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamps();

            $table->index(['external_invoice_id', 'line_no'], 'acct_external_lines_invoice_line_idx');
        });

        Schema::create('acct_external_invoice_vat_breakdowns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_invoice_id')->constrained('acct_external_invoices')->cascadeOnDelete();
            $table->string('vat_rate', 20)->nullable();
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->json('payload_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acct_external_invoice_vat_breakdowns');
        Schema::dropIfExists('acct_external_invoice_lines');

        Schema::table('acct_external_invoices', function (Blueprint $table): void {
            $table->dropUnique('acct_external_connection_provider_id_unique');
            $table->dropConstrainedForeignId('connection_id');
            $table->dropColumn([
                'provider_tax_id',
                'provider_type',
                'seller_name',
                'seller_address',
                'buyer_name',
                'template_code',
                'invoice_code',
                'currency',
                'exchange_rate',
                'subtotal_ex_vat',
                'non_taxable_amount',
                'discount_amount',
                'fee_amount',
                'other_amount',
                'invoice_status_code',
                'processing_status_code',
                'illegal_status',
                'illegal_reason',
                'duplicate_status',
                'issued_at',
                'tax_authority_code_issued_at',
                'tax_authority_received_at',
                'provider_updated_at',
                'xml_checksum',
                'html_checksum',
                'vat_breakdown',
                'warning_payload',
                'sync_status',
            ]);
            $table->unique(['provider', 'provider_invoice_id'], 'acct_external_provider_id_unique');
        });

        Schema::table('acct_einvoice_transmissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('connection_id');
            $table->dropColumn(['status', 'next_attempt_at', 'completed_at', 'pdf_checksum', 'xml_checksum']);
        });

        Schema::dropIfExists('acct_provider_series');
        Schema::dropIfExists('acct_provider_connections');
    }
};
