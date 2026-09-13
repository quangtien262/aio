<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_shifts')) {
            Schema::create('fnb_shifts', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('terminal_id');
                $table->string('currency', 3);
                $table->string('status', 40)->default('open');
                $table->string('open_slot', 20)->nullable()->default('open');
                $table->bigInteger('opening_float_minor')->default(0);
                $table->bigInteger('expected_cash_minor')->default(0);
                $table->bigInteger('counted_cash_minor')->nullable();
                $table->bigInteger('variance_minor')->nullable();
                $table->json('closing_snapshot')->nullable();
                $table->string('closing_hash', 64)->nullable();
                $table->timestamp('opened_at');
                $this->actor($table, 'opened_by');
                $table->timestamp('closed_at')->nullable();
                $this->actor($table, 'closed_by');
                $table->timestamp('reconciled_at')->nullable();
                $this->actor($table, 'reconciled_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['terminal_id', 'open_slot'], 'fnb_shift_terminal_open_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'terminal_id', 'id'], 'fnb_shift_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_shift_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'business_day_id', 'status'], 'fnb_shift_day_status_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id'], 'fnb_shift_day_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_business_days')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'terminal_id'], 'fnb_shift_terminal_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_terminals')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_service_sessions')) {
            Schema::create('fnb_service_sessions', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->string('creation_key', 120);
                $table->unsignedBigInteger('business_day_id');
                $table->string('currency', 3);
                $table->string('timezone_snapshot', 64);
                $table->string('service_type', 30);
                $table->string('source_channel', 30)->default('pos');
                $table->unsignedBigInteger('customer_profile_id')->nullable();
                $table->string('status', 30)->default('open');
                $table->unsignedInteger('guest_count')->default(1);
                $table->unsignedBigInteger('merged_into_session_id')->nullable();
                $table->json('customer_snapshot')->nullable();
                $table->timestamp('opened_at');
                $this->actor($table, 'opened_by');
                $table->timestamp('settling_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $this->actor($table, 'closed_by');
                $table->timestamp('cancelled_at')->nullable();
                $this->actor($table, 'cancelled_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'creation_key'], 'fnb_session_creation_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'currency', 'id'], 'fnb_session_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_session_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'status', 'opened_at'], 'fnb_session_hot_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id'], 'fnb_session_day_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_business_days')->restrictOnDelete();
                $table->foreign(['website_key', 'customer_profile_id'], 'fnb_session_customer_fk')
                    ->references(['website_key', 'id'])->on('fnb_customer_profiles')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'currency', 'merged_into_session_id'], 'fnb_session_merge_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'currency', 'id'])->on('fnb_service_sessions')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_service_session_tables')) {
            Schema::create('fnb_service_session_tables', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('table_id');
                $table->timestamp('joined_at');
                $table->timestamp('left_at')->nullable();
                $table->string('active_slot', 20)->nullable()->default('active');
                $this->actor($table, 'changed_by');
                $table->string('idempotency_key', 120);
                $table->timestamps();
                $table->unique(['table_id', 'active_slot'], 'fnb_session_table_active_uq');
                $table->unique(['session_id', 'idempotency_key'], 'fnb_session_table_idem_uq');
                $table->foreign(['website_key', 'outlet_id', 'session_id'], 'fnb_session_table_session_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_service_sessions')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'table_id'], 'fnb_session_table_table_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_dining_tables')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_approvals')) {
            Schema::create('fnb_approvals', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->string('policy_key', 80);
                $table->unsignedInteger('policy_version');
                $table->string('installed_module_version', 40);
                $table->string('action', 80);
                $table->string('subject_type', 60);
                $table->string('subject_id', 100);
                $table->foreignId('requester_id')->constrained('admins')->restrictOnDelete();
                $table->string('requester_session_hash', 64);
                $table->string('requester_authority_hash', 64);
                $table->unsignedInteger('requester_authority_revision');
                $table->foreignId('approver_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('approver_authority_hash', 64)->nullable();
                $table->unsignedInteger('approver_authority_revision')->nullable();
                $table->json('required_permissions');
                $table->string('status', 30)->default('pending');
                $table->text('reason');
                $table->text('note')->nullable();
                $table->json('policy_snapshot');
                $table->string('payload_hash', 64);
                $table->string('token_hash', 64)->nullable()->unique();
                $table->string('request_id', 100)->nullable();
                $table->string('idempotency_key', 120);
                $table->timestamp('expires_at');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'idempotency_key'], 'fnb_approval_idempotency_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_approval_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'status', 'expires_at'], 'fnb_approval_hot_idx');
                $table->foreign(['website_key', 'outlet_id'], 'fnb_approval_outlet_fk')
                    ->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_approval_events')) {
            Schema::create('fnb_approval_events', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('approval_id');
                $table->string('event_type', 50);
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $this->actor($table, 'actor_id');
                $table->json('payload')->nullable();
                $table->string('idempotency_key', 120)->nullable();
                $table->timestamp('occurred_at');
                $table->unique(['approval_id', 'event_type', 'idempotency_key'], 'fnb_approval_event_idem_uq');
                $table->foreign(['website_key', 'outlet_id', 'approval_id'], 'fnb_approval_event_approval_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_approvals')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['fnb_approval_events', 'fnb_approvals', 'fnb_service_session_tables', 'fnb_service_sessions', 'fnb_shifts'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function scope(Blueprint $table): void
    {
        $table->string('website_key', 120);
        $table->unsignedBigInteger('outlet_id');
    }

    private function actor(Blueprint $table, string $name): void
    {
        $table->foreignId($name)->nullable()->constrained('admins')->nullOnDelete();
    }
};
