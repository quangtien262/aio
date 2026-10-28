<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_receipt_line_id', 'source_movement_id'] as $column) {
            if (! Schema::hasColumn('ph_receipts', $column)) {
                Schema::table('ph_receipts', fn (Blueprint $t) => $t->unsignedBigInteger($column)->nullable()->index());
            }
        }
        $columns = [
            'connection_version' => fn (Blueprint $t) => $t->unsignedBigInteger('connection_version')->nullable(),
            'reconcile_requested' => fn (Blueprint $t) => $t->boolean('reconcile_requested')->default(false),
            'event_id' => fn (Blueprint $t) => $t->uuid('event_id')->nullable()->unique(),
            'actor_id' => fn (Blueprint $t) => $t->unsignedBigInteger('actor_id')->nullable(),
            'actor_auth_version' => fn (Blueprint $t) => $t->unsignedBigInteger('actor_auth_version')->nullable(),
            'encrypted_request' => fn (Blueprint $t) => $t->longText('encrypted_request')->nullable(),
            'result_reference' => fn (Blueprint $t) => $t->json('result_reference')->nullable(),
            'lease_token' => fn (Blueprint $t) => $t->uuid('lease_token')->nullable(),
            'lease_until' => fn (Blueprint $t) => $t->timestamp('lease_until')->nullable(),
            'available_at' => fn (Blueprint $t) => $t->timestamp('available_at')->nullable(),
            'attempts' => fn (Blueprint $t) => $t->unsignedInteger('attempts')->default(0),
            'last_polled_at' => fn (Blueprint $t) => $t->timestamp('last_polled_at')->nullable(),
        ];
        foreach ($columns as $name => $column) {
            if (! Schema::hasColumn('ph_integration_attempts', $name)) {
                Schema::table('ph_integration_attempts', $column);
            }
        }
        if (! Schema::hasIndex('ph_integration_attempts', ['status', 'available_at'])) {
            Schema::table('ph_integration_attempts', fn (Blueprint $t) => $t->index(['status', 'available_at']));
        }
        if (! Schema::hasColumn('ph_dispensings', 'created_auth_version')) {
            Schema::table('ph_dispensings', fn (Blueprint $t) => $t->unsignedBigInteger('created_auth_version')->nullable());
        }
        if (! Schema::hasTable('ph_accounting_mappings')) {
            Schema::create('ph_accounting_mappings', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('chain_id');
                $t->string('legal_entity_ref', 100)->unique();
                $t->unsignedBigInteger('organization_id')->unique();
                $t->json('item_mappings');
                $t->unsignedBigInteger('authorized_by');
                $t->unsignedBigInteger('authorized_auth_version');
                $t->string('status', 30)->default('active');
                $t->unsignedBigInteger('version')->default(1);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('ph_accounting_source_links')) {
            Schema::create('ph_accounting_source_links', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('chain_id');
                $t->unsignedBigInteger('pharmacy_id');
                $t->string('source_type', 30);
                $t->string('source_id', 100);
                $t->unsignedBigInteger('organization_id');
                $t->unsignedBigInteger('document_id');
                $t->char('source_hash', 64);
                $t->unsignedBigInteger('actor_id');
                $t->timestamps();
                $t->unique(['source_type', 'source_id']);
            });
        }
        if (! Schema::hasTable('ph_customer_mappings')) {
            Schema::create('ph_customer_mappings', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('chain_id')->unique();
                $t->string('website_key', 100);
                $t->unsignedBigInteger('book_id');
                $t->unsignedBigInteger('authorized_by');
                $t->unsignedBigInteger('authorized_auth_version');
                $t->string('status', 30)->default('active');
                $t->unsignedBigInteger('version')->default(1);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('ph_patient_customer_links')) {
            Schema::create('ph_patient_customer_links', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('chain_id');
                $t->unsignedBigInteger('pharmacy_id');
                $t->unsignedBigInteger('patient_id')->unique();
                $t->unsignedBigInteger('mapping_id');
                $t->unsignedBigInteger('customer_id');
                $t->unsignedBigInteger('actor_id');
                $t->string('status', 30)->default('active');
                $t->unsignedBigInteger('version')->default(1);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('ph_recovery_cases')) {
            Schema::create('ph_recovery_cases', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('chain_id');
                $t->unsignedBigInteger('pharmacy_id');
                $t->unsignedBigInteger('dispensing_id')->unique();
                $t->unsignedBigInteger('actor_id');
                $t->unsignedBigInteger('actor_auth_version');
                $t->string('status', 30)->default('pending');
                $t->string('error_code', 80)->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->uuid('lease_token')->nullable();
                $t->timestamp('lease_until')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Integration/recovery history uses forward repair migrations.');
    }
};
