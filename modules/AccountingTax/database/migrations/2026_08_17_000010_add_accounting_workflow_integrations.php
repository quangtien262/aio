<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct_document_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->restrictOnDelete();
            $table->string('document_type', 50);
            $table->unsignedSmallInteger('year');
            $table->string('prefix', 30);
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->timestamps();
            $table->unique(['organization_id', 'document_type', 'year'], 'acct_doc_sequences_org_type_year_unique');
        });

        Schema::create('acct_party_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('acct_parties')->cascadeOnDelete();
            $table->string('source_module', 50);
            $table->string('source_type', 80);
            $table->string('source_id', 80);
            $table->string('source_key')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['organization_id', 'source_module', 'source_type', 'source_id'],
                'acct_party_sources_org_source_unique',
            );
        });

        Schema::create('acct_tax_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('period_type', 20)->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('base_currency', 3)->default('VND');
            $table->string('status', 20)->default('open')->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('report_snapshot')->nullable();
            $table->string('snapshot_hash', 64)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->unsignedBigInteger('filed_by')->nullable();
            $table->string('filing_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'acct_tax_periods_org_code_unique');
            $table->index(['organization_id', 'start_date', 'end_date'], 'acct_tax_periods_org_dates_idx');
        });

        Schema::create('acct_inventory_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('acct_documents')->restrictOnDelete();
            $table->string('direction', 20);
            $table->unsignedBigInteger('inventory_document_id')->nullable()->index();
            $table->string('status', 30)->default('proposed')->index();
            $table->string('idempotency_key')->unique();
            $table->json('payload_snapshot');
            $table->text('last_error')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamps();
            $table->unique('document_id', 'acct_inventory_links_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acct_inventory_links');
        Schema::dropIfExists('acct_tax_periods');
        Schema::dropIfExists('acct_party_sources');
        Schema::dropIfExists('acct_document_number_sequences');
    }
};
