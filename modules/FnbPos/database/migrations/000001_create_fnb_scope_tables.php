<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_site_settings')) {
            Schema::create('fnb_site_settings', function (Blueprint $table): void {
                $table->string('website_key', 120)->primary();
                $table->boolean('is_active')->default(true);
                $table->string('operational_state', 30)->default('active')->index();
                $table->string('default_currency', 3)->default('VND');
                $table->string('default_timezone', 64)->default('Asia/Ho_Chi_Minh');
                $table->string('order_prefix', 20)->default('ORD');
                $table->string('tax_mode', 30)->default('inclusive');
                $table->json('service_charge_policy')->nullable();
                $table->json('settings')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fnb_outlets')) {
            Schema::create('fnb_outlets', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->uuid('public_id')->unique();
                $table->string('code', 40);
                $table->string('name');
                $table->string('timezone', 64);
                $table->string('currency', 3);
                $table->string('phone', 40)->nullable();
                $table->text('address')->nullable();
                $table->string('status', 30)->default('active');
                $table->json('settings')->nullable();
                $table->unsignedInteger('version')->default(1);
                $this->actor($table, 'created_by');
                $this->actor($table, 'updated_by');
                $table->timestamps();
                $table->unique(['website_key', 'code'], 'fnb_outlet_scope_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_outlet_scope_id_uq');
                $table->index(['website_key', 'status'], 'fnb_outlet_scope_status_idx');
            });
        }

        if (! Schema::hasTable('fnb_business_days')) {
            Schema::create('fnb_business_days', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->date('business_date');
                $table->string('currency', 3);
                $table->string('timezone_snapshot', 64);
                $table->string('status', 30)->default('open');
                $table->string('open_slot', 20)->nullable()->default('open');
                $table->timestamp('opened_at');
                $this->actor($table, 'opened_by');
                $table->timestamp('closed_at')->nullable();
                $this->actor($table, 'closed_by');
                $table->timestamp('reconciled_at')->nullable();
                $this->actor($table, 'reconciled_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'business_date'], 'fnb_day_outlet_date_uq');
                $table->unique(['outlet_id', 'open_slot'], 'fnb_day_outlet_open_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_day_scope_id_uq');
                $this->outletForeign($table, 'fnb_day_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_document_sequences')) {
            Schema::create('fnb_document_sequences', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->date('business_date');
                $table->string('document_type', 30);
                $table->string('prefix', 20);
                $table->unsignedBigInteger('next_number')->default(1);
                $table->unsignedTinyInteger('padding')->default(5);
                $table->timestamps();
                $table->unique(['outlet_id', 'business_date', 'document_type'], 'fnb_seq_day_type_uq');
                $this->outletForeign($table, 'fnb_seq_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_terminals')) {
            Schema::create('fnb_terminals', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->uuid('public_id')->unique();
                $table->string('code', 40);
                $table->string('name');
                $table->string('type', 30)->default('pos');
                $table->string('device_uid')->nullable()->unique();
                $table->string('status', 30)->default('active');
                $table->json('settings')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'code'], 'fnb_terminal_outlet_code_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_terminal_scope_id_uq');
                $this->outletForeign($table, 'fnb_terminal_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_prep_stations')) {
            Schema::create('fnb_prep_stations', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->string('code', 40);
                $table->string('name');
                $table->unsignedInteger('sla_seconds')->default(600);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->json('settings')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'code'], 'fnb_station_outlet_code_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_station_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'status', 'sort_order'], 'fnb_station_hot_idx');
                $this->outletForeign($table, 'fnb_station_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_service_areas')) {
            Schema::create('fnb_service_areas', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->string('code', 40);
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'code'], 'fnb_area_outlet_code_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_area_scope_id_uq');
                $this->outletForeign($table, 'fnb_area_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_dining_tables')) {
            Schema::create('fnb_dining_tables', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->unsignedBigInteger('service_area_id');
                $table->string('code', 40);
                $table->string('name');
                $table->unsignedInteger('capacity')->default(1);
                $table->string('qr_token_hash', 64)->nullable()->unique();
                $table->string('status', 30)->default('available');
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'code'], 'fnb_table_outlet_code_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_table_scope_id_uq');
                $this->outletForeign($table, 'fnb_table_outlet_fk');
                $table->foreign(['website_key', 'outlet_id', 'service_area_id'], 'fnb_table_area_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_service_areas')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_payment_methods')) {
            Schema::create('fnb_payment_methods', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->string('code', 40);
                $table->string('name');
                $table->string('kind', 30);
                $table->boolean('requires_reference')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->json('settings')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'code'], 'fnb_method_outlet_code_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_method_scope_id_uq');
                $this->outletForeign($table, 'fnb_method_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_staff_role_bindings')) {
            Schema::create('fnb_staff_role_bindings', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
                $table->foreignId('preset_role_id')->constrained('roles')->restrictOnDelete();
                $table->unsignedBigInteger('core_assignment_id')->nullable();
                $table->string('status', 30)->default('active');
                $this->actor($table, 'assigned_by');
                $table->timestamp('assigned_at');
                $this->actor($table, 'replaced_by');
                $table->timestamp('replaced_at')->nullable();
                $this->actor($table, 'revoked_by');
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'admin_id'], 'fnb_staff_role_scope_admin_uq');
                $table->unique(['website_key', 'id'], 'fnb_staff_role_scope_id_uq');
                $table->foreign('core_assignment_id', 'fnb_staff_role_assignment_fk')
                    ->references('id')->on('admin_role_assignments')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_staff_candidate_grants')) {
            Schema::create('fnb_staff_candidate_grants', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->foreignId('requester_admin_id')->constrained('admins')->restrictOnDelete();
                $table->string('request_session_hash', 64);
                $table->foreignId('target_admin_id')->constrained('admins')->restrictOnDelete();
                $table->string('identifier_hmac', 64);
                $table->string('token_hash', 64)->unique();
                $table->string('nonce', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
                $table->index(['website_key', 'outlet_id', 'expires_at'], 'fnb_candidate_scope_expiry_idx');
                $this->outletForeign($table, 'fnb_candidate_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_outlet_staff')) {
            Schema::create('fnb_outlet_staff', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $this->actor($table, 'assigned_by');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $this->actor($table, 'revoked_by');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'admin_id'], 'fnb_outlet_staff_admin_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_outlet_staff_scope_id_uq');
                $this->outletForeign($table, 'fnb_outlet_staff_outlet_fk');
            });
        }

        if (! Schema::hasTable('fnb_outlet_staff_terminals')) {
            Schema::create('fnb_outlet_staff_terminals', function (Blueprint $table): void {
                $table->id();
                $this->outletScope($table);
                $table->unsignedBigInteger('outlet_staff_id');
                $table->unsignedBigInteger('terminal_id');
                $table->timestamps();
                $table->unique(['outlet_staff_id', 'terminal_id'], 'fnb_staff_terminal_uq');
                $table->foreign(['website_key', 'outlet_id', 'outlet_staff_id'], 'fnb_staff_terminal_staff_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_outlet_staff')->cascadeOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'terminal_id'], 'fnb_staff_terminal_terminal_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_terminals')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_customer_profiles')) {
            Schema::create('fnb_customer_profiles', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('core_customer_id')->nullable();
                $table->string('code', 40)->nullable();
                $table->string('name');
                $table->string('phone_normalized', 32)->nullable();
                $table->string('email')->nullable();
                $table->date('birthday')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamp('marketing_consented_at')->nullable();
                $table->timestamp('privacy_consented_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'id'], 'fnb_customer_scope_id_uq');
                $table->unique(['website_key', 'phone_normalized'], 'fnb_customer_scope_phone_uq');
                $table->unique(['website_key', 'core_customer_id'], 'fnb_customer_scope_core_uq');
                $table->index(['website_key', 'code'], 'fnb_customer_scope_code_idx');
            });
        }

        if (! Schema::hasTable('fnb_customer_events')) {
            Schema::create('fnb_customer_events', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('customer_profile_id');
                $table->string('event_type', 50);
                $this->actor($table, 'actor_id');
                $table->json('changed_fields')->nullable();
                $table->string('evidence_hash', 64)->nullable();
                $table->text('reason')->nullable();
                $table->string('request_id', 100)->nullable();
                $table->timestamp('occurred_at');
                $table->foreign(['website_key', 'customer_profile_id'], 'fnb_customer_event_profile_fk')
                    ->references(['website_key', 'id'])->on('fnb_customer_profiles')->restrictOnDelete();
                $table->index(['website_key', 'customer_profile_id', 'occurred_at'], 'fnb_customer_event_hot_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fnb_customer_events', 'fnb_customer_profiles', 'fnb_outlet_staff_terminals',
            'fnb_outlet_staff', 'fnb_staff_candidate_grants', 'fnb_staff_role_bindings',
            'fnb_payment_methods', 'fnb_dining_tables', 'fnb_service_areas', 'fnb_prep_stations',
            'fnb_terminals', 'fnb_document_sequences', 'fnb_business_days', 'fnb_outlets', 'fnb_site_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function website(Blueprint $table): void
    {
        $table->string('website_key', 120);
    }

    private function outletScope(Blueprint $table): void
    {
        $this->website($table);
        $table->unsignedBigInteger('outlet_id');
    }

    private function outletForeign(Blueprint $table, string $name): void
    {
        $table->foreign(['website_key', 'outlet_id'], $name)
            ->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
    }

    private function actor(Blueprint $table, string $name): void
    {
        $table->foreignId($name)->nullable()->constrained('admins')->nullOnDelete();
    }
};
