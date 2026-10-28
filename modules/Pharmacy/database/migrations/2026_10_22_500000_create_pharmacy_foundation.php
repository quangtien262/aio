<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ph_runtime_states', function (Blueprint $t): void {
            $t->string('module_key', 80)->primary();
            $t->string('operational_state', 20)->default('disabled');
            $t->uuid('operation_id')->nullable();
            $t->string('resume_state', 20)->default('disabled');
            $t->unsignedBigInteger('version')->default(1);
            $t->timestamps();
        });
        $this->table('ph_chains', function (Blueprint $t): void {
            $t->string('code', 50)->unique();
            $t->string('name');
            $t->unsignedBigInteger('catalog_id');
            $t->string('legal_entity_ref', 100);
            $t->string('timezone', 60)->default('Asia/Ho_Chi_Minh');
            $t->json('settings')->nullable();
        }, chain: false, pharmacy: false);
        $this->table('ph_pharmacies', function (Blueprint $t): void {
            $t->string('code', 50);
            $t->string('name');
            $t->unsignedBigInteger('warehouse_id')->unique();
            $t->string('legal_entity_ref', 100);
            $t->string('timezone', 60)->default('Asia/Ho_Chi_Minh');
            $t->string('facility_type', 30)->default('pharmacy');
            $t->string('license_number')->nullable();
            $t->json('settings')->nullable();
            $t->unique(['chain_id', 'code']);
        }, pharmacy: false);
        $this->table('ph_counters', function (Blueprint $t): void {
            $t->string('code', 50);
            $t->string('name');
            $t->unique(['pharmacy_id', 'code']);
        });
        $this->table('ph_memberships', function (Blueprint $t): void {
            $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $t->json('permissions');
            $t->timestamp('expires_at')->nullable();
            $t->index(['admin_id', 'chain_id', 'pharmacy_id']);
        }, nullablePharmacy: true);
        $this->table('ph_credentials', function (Blueprint $t): void {
            $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $t->string('license_number');
            $t->json('abilities');
            $t->timestamp('valid_from');
            $t->timestamp('valid_until');
            $t->unsignedBigInteger('verified_by')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->longText('encrypted_verification_evidence')->nullable();
            $t->index(['admin_id', 'chain_id', 'pharmacy_id', 'status']);
        }, nullablePharmacy: true);
        $this->table('ph_products', function (Blueprint $t): void {
            $t->unsignedBigInteger('sku_id');
            $t->unsignedBigInteger('inventory_item_id');
            $t->string('code', 60);
            $t->string('name');
            $t->string('registration_number')->nullable();
            $t->json('ingredients')->nullable();
            $t->string('strength')->nullable();
            $t->string('dosage_form')->nullable();
            $t->string('route')->nullable();
            $t->string('base_unit', 30);
            $t->boolean('requires_prescription')->default(false);
            $t->boolean('controlled')->default(false);
            $t->boolean('cold_chain')->default(false);
            $t->boolean('restricted_retail')->default(false);
            $t->decimal('minimum_increment', 20, 6)->default(1);
            $t->json('metadata')->nullable();
            $t->unique(['chain_id', 'sku_id']);
            $t->unique(['chain_id', 'code']);
            $t->index(['chain_id', 'status', 'name']);
        }, pharmacy: false);
        $this->table('ph_prices', function (Blueprint $t): void {
            $t->foreignId('product_id')->constrained('ph_products')->restrictOnDelete();
            $t->unsignedBigInteger('packaging_id')->nullable();
            $t->unsignedBigInteger('packaging_key')->default(0);
            $t->unsignedBigInteger('price_minor');
            $t->timestamp('effective_at')->nullable();
            $t->unique(['pharmacy_id', 'product_id', 'packaging_key']);
        });
        $this->table('ph_commands', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id');
            $t->string('command_key', 120);
            $t->string('operation', 90);
            $t->char('fingerprint', 64);
            $t->json('authorization');
            $t->json('result_ref')->nullable();
            $t->unique(['actor_id', 'command_key']);
        }, nullablePharmacy: true, nullableChain: true, status: 'pending');
        $this->table('ph_outbox', function (Blueprint $t): void {
            $t->uuid('event_id')->unique();
            $t->string('event_type', 100);
            $t->string('aggregate_type', 60);
            $t->string('aggregate_id', 100);
            $t->unsignedBigInteger('aggregate_version');
            $t->unsignedInteger('schema_version')->default(1);
            $t->json('payload');
            $t->string('destination', 60)->default('audit');
            $t->timestamp('available_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('delivered_at')->nullable();
            $t->string('last_error')->nullable();
            $t->uuid('lease_token')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->index(['status', 'available_at']);
        }, nullablePharmacy: true, nullableChain: true, status: 'pending');
        Schema::create('ph_inbox', function (Blueprint $t): void {
            $t->id();
            $t->string('consumer', 100);
            $t->uuid('event_id');
            $t->timestamp('processed_at');
            $t->unique(['consumer', 'event_id']);
        });
        $this->table('ph_documents', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id');
            $t->string('kind', 40);
            $t->string('path');
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size');
            $t->char('checksum', 64);
            $t->text('encrypted_name');
            $t->timestamp('expires_at')->nullable();
        });
        $this->table('ph_quality_cases', function (Blueprint $t): void {
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('batch_id')->nullable();
            $t->string('kind', 40);
            $t->text('reason');
            $t->string('hold_id', 100)->nullable();
            $t->unsignedBigInteger('actor_id');
            $t->json('evidence')->nullable();
            $t->index(['pharmacy_id', 'kind', 'status']);
        });
        $this->table('ph_recalls', function (Blueprint $t): void {
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('batch_id');
            $t->string('reference', 100);
            $t->text('reason');
            $t->unsignedBigInteger('actor_id');
            $t->json('evidence')->nullable();
            $t->index(['chain_id', 'batch_id', 'status']);
        }, pharmacy: false);
        $this->table('ph_recall_tasks', function (Blueprint $t): void {
            $t->foreignId('recall_id')->constrained('ph_recalls')->restrictOnDelete();
            $t->string('hold_id', 100)->nullable();
            $t->decimal('isolated_quantity', 20, 6)->default(0);
            $t->unsignedBigInteger('confirmed_by')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->unique(['recall_id', 'pharmacy_id']);
        });
        $this->table('ph_connections', function (Blueprint $t): void {
            $t->string('kind', 40);
            $t->string('name');
            $t->text('encrypted_credentials')->nullable();
            $t->json('settings')->nullable();
            $t->string('contract_version', 60)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->unsignedBigInteger('verified_by')->nullable();
            $t->unique(['chain_id', 'kind']);
        }, pharmacy: false, status: 'draft');
        $this->table('ph_integration_attempts', function (Blueprint $t): void {
            $t->unsignedBigInteger('connection_id');
            $t->string('source_type', 60);
            $t->string('source_id', 100);
            $t->string('external_id', 150)->nullable();
            $t->string('response_code', 60)->nullable();
            $t->char('request_hash', 64);
            $t->text('error')->nullable();
            $t->index(['connection_id', 'source_id']);
        }, nullablePharmacy: true, status: 'pending');
        $this->table('ph_temperature_readings', function (Blueprint $t): void {
            $t->string('zone', 100);
            $t->decimal('temperature', 8, 3);
            $t->decimal('humidity', 8, 3)->nullable();
            $t->timestamp('measured_at');
            $t->unsignedBigInteger('actor_id');
            $t->string('source', 40)->default('manual');
            $t->json('evidence')->nullable();
            $t->index(['pharmacy_id', 'measured_at']);
        });
        $this->table('ph_knowledge_entries', function (Blueprint $t): void {
            $t->string('title');
            $t->string('source_url', 1000);
            $t->string('source_version', 100);
            $t->text('encrypted_content');
            $t->string('license_reference');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('valid_until');
        }, pharmacy: false, status: 'draft');
        $this->table('ph_price_publications', function (Blueprint $t): void {
            $t->foreignId('product_id')->constrained('ph_products')->restrictOnDelete();
            $t->unsignedBigInteger('price_minor');
            $t->timestamp('effective_at');
            $t->json('targets');
            $t->unsignedBigInteger('actor_id');
            $t->json('acknowledgements')->nullable();
        }, pharmacy: false, status: 'draft');
        DB::table('ph_runtime_states')->insert(['module_key' => 'pharmacy', 'operational_state' => 'disabled', 'resume_state' => 'disabled', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function table(string $name, callable $columns, bool $chain = true, bool $pharmacy = true, bool $nullablePharmacy = false, bool $nullableChain = false, string $status = 'active'): void
    {
        Schema::create($name, function (Blueprint $t) use ($columns, $chain, $pharmacy, $nullableChain, $nullablePharmacy, $status): void {
            $t->id();
            $t->uuid('public_id')->unique();
            if ($chain) {
                $t->foreignId('chain_id')->nullable($nullableChain)->constrained('ph_chains')->restrictOnDelete();
            }
            if ($pharmacy) {
                $t->foreignId('pharmacy_id')->nullable($nullablePharmacy)->constrained('ph_pharmacies')->restrictOnDelete();
            }
            $columns($t);
            $t->string('status', 40)->default($status);
            $t->unsignedBigInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ph_price_publications', 'ph_knowledge_entries', 'ph_temperature_readings', 'ph_integration_attempts', 'ph_connections', 'ph_recall_tasks', 'ph_recalls', 'ph_quality_cases', 'ph_documents', 'ph_inbox', 'ph_outbox', 'ph_commands', 'ph_prices', 'ph_products', 'ph_credentials', 'ph_memberships', 'ph_counters', 'ph_pharmacies', 'ph_chains', 'ph_runtime_states'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
