<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_idempotency_requests')) {
            Schema::create('fnb_idempotency_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                // Zero is reserved for a pre-outlet onboarding command.
                $table->unsignedBigInteger('outlet_id')->default(0);
                $table->unsignedBigInteger('terminal_id')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('operation', 100);
                $table->string('idempotency_key', 120);
                $table->string('request_hash', 64);
                $table->string('state', 30)->default('processing');
                $table->uuid('owner_token');
                $table->timestamp('heartbeat_at');
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->json('response_body')->nullable();
                $table->string('resource_type', 80)->nullable();
                $table->string('resource_id', 100)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'operation', 'idempotency_key'], 'fnb_idempotency_scope_operation_uq');
                $table->index(['state', 'expires_at'], 'fnb_idempotency_state_expiry_idx');
                $table->foreign('actor_id', 'fnb_idempotency_actor_fk')->references('id')->on('admins')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_outbox_events')) {
            Schema::create('fnb_outbox_events', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('event_id')->unique();
                $table->string('aggregate_type', 80);
                $table->string('aggregate_id', 100);
                $table->unsignedInteger('aggregate_version');
                $table->string('event_type', 100);
                $table->unsignedInteger('schema_version')->default(1);
                $table->json('payload');
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'aggregate_type', 'aggregate_id', 'aggregate_version', 'event_type'], 'fnb_outbox_semantic_event_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_outbox_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'occurred_at'], 'fnb_outbox_scope_time_idx');
                $this->outletForeign($table, 'fnb_outbox_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_outbox_deliveries')) {
            Schema::create('fnb_outbox_deliveries', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('outbox_event_id');
                $table->string('destination', 100);
                $table->string('idempotency_key', 180);
                $table->string('status', 30)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedInteger('max_attempts')->default(10);
                $table->timestamp('available_at');
                $table->timestamp('lease_expires_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('lock_token', 100)->nullable();
                $table->string('external_reference', 180)->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'outbox_event_id', 'destination'], 'fnb_outbox_delivery_event_uq');
                $table->unique(['website_key', 'outlet_id', 'destination', 'idempotency_key'], 'fnb_outbox_delivery_semantic_uq');
                $table->index(['status', 'available_at', 'id'], 'fnb_outbox_delivery_claim_idx');
                $table->foreign(['website_key', 'outlet_id', 'outbox_event_id'], 'fnb_outbox_delivery_event_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_outbox_events')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_stock_consumptions')) {
            Schema::create('fnb_stock_consumptions', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->string('source_type', 60);
                $table->string('source_id', 100);
                $table->unsignedInteger('source_version');
                $table->string('source_event_id', 160);
                $table->unsignedBigInteger('reversal_of_id')->nullable();
                $table->string('kind', 30);
                $table->string('status', 30)->default('pending');
                $table->string('external_document_reference', 180)->nullable();
                $table->string('idempotency_key', 160);
                $table->json('payload_snapshot');
                $table->string('payload_hash', 64);
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'kind', 'source_event_id'], 'fnb_consumption_semantic_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_consumption_scope_id_uq');
                $table->foreign(['website_key', 'outlet_id', 'reversal_of_id'], 'fnb_consumption_reversal_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_stock_consumptions')->restrictOnDelete();
                $this->outletForeign($table, 'fnb_consumption_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_stock_consumption_lines')) {
            Schema::create('fnb_stock_consumption_lines', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('consumption_id');
                $table->unsignedBigInteger('source_consumption_line_id')->nullable();
                $table->unsignedBigInteger('order_line_id')->nullable();
                $table->unsignedBigInteger('ingredient_id')->nullable();
                $table->string('external_item_reference', 180)->nullable();
                $table->decimal('quantity', 18, 6);
                $table->string('base_unit', 30);
                $table->bigInteger('cost_minor')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'outlet_id', 'consumption_id', 'id'], 'fnb_consumption_line_scope_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_consumption_line_id_uq');
                $table->foreign(['website_key', 'outlet_id', 'consumption_id'], 'fnb_consumption_line_parent_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_stock_consumptions')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'source_consumption_line_id'], 'fnb_consumption_line_source_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_stock_consumption_lines')->restrictOnDelete();
                $table->foreign(['website_key', 'ingredient_id'], 'fnb_consumption_line_ingredient_fk')
                    ->references(['website_key', 'id'])->on('fnb_ingredients')->restrictOnDelete();
                $table->index(['website_key', 'outlet_id', 'order_line_id'], 'fnb_consumption_line_order_idx');
            });
        }

        if (! Schema::hasTable('fnb_audit_scopes')) {
            Schema::create('fnb_audit_scopes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('audit_log_id');
                $table->string('website_key', 120);
                $table->string('scope_type', 30);
                $table->string('scope_value', 120);
                $table->timestamp('created_at');
                $table->unique(['audit_log_id', 'scope_type', 'scope_value'], 'fnb_audit_scope_uq');
                $table->index(['website_key', 'scope_type', 'scope_value', 'audit_log_id'], 'fnb_audit_scope_lookup_idx');
                $table->foreign('audit_log_id', 'fnb_audit_scope_log_fk')->references('id')->on('audit_logs')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_report_exports')) {
            Schema::create('fnb_report_exports', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->foreignId('requested_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('report_type', 60);
                $table->json('filter_snapshot');
                $table->string('filter_hash', 64);
                $table->string('format', 20);
                $table->string('status', 30)->default('queued');
                $table->text('private_path')->nullable();
                $table->string('checksum', 128)->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->string('idempotency_key', 120);
                $table->timestamp('queued_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['outlet_id', 'idempotency_key'], 'fnb_report_export_idempotency_uq');
                $table->index(['website_key', 'outlet_id', 'status', 'queued_at'], 'fnb_report_export_hot_idx');
                $this->outletForeign($table, 'fnb_report_export_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_print_jobs')) {
            Schema::create('fnb_print_jobs', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('terminal_id')->nullable();
                $table->unsignedBigInteger('prep_station_id')->nullable();
                $table->string('document_type', 50);
                $table->string('document_id', 100);
                $table->string('template_key', 80);
                $table->unsignedInteger('template_version')->default(1);
                $table->json('payload_snapshot');
                $table->string('payload_hash', 64);
                $table->string('status', 30)->default('generated');
                $table->string('idempotency_key', 120);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedInteger('request_count')->default(0);
                $table->unsignedInteger('reprint_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('user_confirmed_at')->nullable();
                $table->timestamps();
                $table->unique(['outlet_id', 'idempotency_key'], 'fnb_print_job_idempotency_uq');
                $this->outletForeign($table, 'fnb_print_job_outlet_fk');
                $table->foreign(['website_key', 'outlet_id', 'terminal_id'], 'fnb_print_job_terminal_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_terminals')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'prep_station_id'], 'fnb_print_job_station_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_prep_stations')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fnb_print_jobs', 'fnb_report_exports', 'fnb_audit_scopes',
            'fnb_stock_consumption_lines', 'fnb_stock_consumptions',
            'fnb_outbox_deliveries', 'fnb_outbox_events', 'fnb_idempotency_requests',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function scope(Blueprint $table): void
    {
        $table->string('website_key', 120);
        $table->unsignedBigInteger('outlet_id');
    }

    private function outletForeign(Blueprint $table, string $name): void
    {
        $table->foreign(['website_key', 'outlet_id'], $name)
            ->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
    }
};
