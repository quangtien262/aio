<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Customers\Services\CustomerRuntimeState;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cust_books', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('website_key', 100)->index();
            $t->string('code', 40);
            $t->string('name');
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['website_key', 'code']);
        });
        Schema::create('cust_book_grants', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $t->string('role', 20);
            $t->timestamps();
            $t->unique(['book_id', 'admin_id']);
        });
        Schema::create('cust_profiles', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->string('code', 60);
            $t->string('type', 20)->default('person');
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('tax_code', 60)->nullable();
            $t->date('birthday')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('active');
            $t->unsignedBigInteger('merged_into_id')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('admins')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['book_id', 'code']);
            $t->index(['book_id', 'status', 'id']);
            $t->index(['book_id', 'name']);
        });
        Schema::create('cust_contacts', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('kind', 20);
            $t->string('value');
            $t->string('normalized');
            $t->string('label', 60)->nullable();
            $t->string('country_code', 2)->default('VN');
            $t->boolean('is_primary')->default(false);
            $t->timestamp('verified_at')->nullable();
            $t->string('source', 60)->default('customers');
            $t->timestamps();
            $t->index(['book_id', 'kind', 'normalized']);
            $t->unique(['profile_id', 'kind', 'normalized']);
        });
        Schema::create('cust_addresses', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('label', 60)->nullable();
            $t->string('kind', 20)->default('both');
            $t->string('line1');
            $t->string('line2')->nullable();
            $t->string('city')->nullable();
            $t->string('region')->nullable();
            $t->string('postal_code', 30)->nullable();
            $t->string('country_code', 2)->default('VN');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::create('cust_profile_tags', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('tag', 80);
            $t->unique(['profile_id', 'tag']);
            $t->index(['book_id', 'tag']);
        });
        Schema::create('cust_bindings', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('website_key', 100);
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->string('module_key', 60);
            $t->string('scope_type', 30);
            $t->string('scope_key', 100);
            $t->json('abilities');
            $t->string('mode', 20)->default('legacy');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['website_key', 'module_key', 'scope_type', 'scope_key'], 'cust_binding_scope_uq');
        });
        Schema::create('cust_sources', function (Blueprint $t): void {
            $t->id();
            $t->string('website_key', 100);
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('module_key', 60);
            $t->string('scope_type', 30);
            $t->string('scope_key', 100);
            $t->string('source_id', 100);
            $t->string('source_hash', 64)->nullable();
            $t->timestamps();
            $t->unique(['website_key', 'module_key', 'scope_type', 'scope_key', 'source_id'], 'cust_source_identity_uq');
        });
        Schema::create('cust_consents', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('purpose', 80);
            $t->string('channel', 30);
            $t->string('brand', 100);
            $t->string('state', 20);
            $t->string('policy_version', 80)->nullable();
            $t->text('evidence')->nullable();
            $t->string('source', 60);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamp('created_at');
            $t->index(['profile_id', 'purpose', 'channel', 'brand', 'id'], 'cust_consent_current_idx');
        });
        Schema::create('cust_field_definitions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->string('namespace', 60);
            $t->string('key', 60);
            $t->string('label', 120);
            $t->string('type', 20);
            $t->json('options')->nullable();
            $t->boolean('required')->default(false);
            $t->boolean('sensitive')->default(false);
            $t->boolean('searchable')->default(false);
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['book_id', 'namespace', 'key']);
        });
        Schema::create('cust_field_values', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->foreignId('field_id')->constrained('cust_field_definitions')->restrictOnDelete();
            $t->text('value');
            $t->unsignedInteger('schema_version');
            $t->timestamps();
            $t->unique(['profile_id', 'field_id']);
        });
        Schema::create('cust_relationships', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->foreignId('related_profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('role', 60);
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->timestamps();
            $t->unique(['profile_id', 'related_profile_id', 'role'], 'cust_relationship_uq');
        });
        Schema::create('cust_account_links', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->foreignId('account_id')->constrained('customers')->restrictOnDelete();
            $t->string('verification_method', 40);
            $t->timestamp('verified_at');
            $t->timestamps();
            $t->unique(['book_id', 'account_id']);
        });
        Schema::create('cust_account_link_requests', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('profile_id')->constrained('cust_profiles')->restrictOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->unsignedBigInteger('issued_by');
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('cust_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->unsignedBigInteger('profile_id')->nullable()->index();
            $t->string('event_type', 80);
            $t->unsignedInteger('aggregate_version');
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->json('changed_fields');
            $t->json('metadata');
            $t->timestamp('occurred_at');
            $t->index(['book_id', 'id']);
        });
        Schema::create('cust_commands', function (Blueprint $t): void {
            $t->id();
            $t->string('website_key', 100);
            $t->unsignedBigInteger('actor_id');
            $t->string('command_key', 100);
            $t->string('fingerprint', 64);
            $t->string('operation', 80);
            $t->json('result_ref');
            $t->timestamp('created_at');
            $t->unique(['website_key', 'actor_id', 'command_key'], 'cust_command_actor_key_uq');
        });
        Schema::create('cust_outbox_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('event_id')->constrained('cust_events')->restrictOnDelete();
            $t->string('destination', 60);
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'destination']);
            $t->index(['status', 'available_at']);
        });
        Schema::create('cust_exports', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->unsignedBigInteger('actor_id');
            $t->unsignedInteger('auth_version');
            $t->string('status', 20)->default('pending');
            $t->string('path')->nullable();
            $t->string('checksum', 64)->nullable();
            $t->text('error')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        app(CustomerRuntimeState::class)->install();
    }

    public function down(): void
    {
        foreach (['cust_runtime_states', 'cust_exports', 'cust_outbox_deliveries', 'cust_commands', 'cust_events', 'cust_account_link_requests', 'cust_account_links', 'cust_relationships', 'cust_field_values', 'cust_field_definitions', 'cust_consents', 'cust_sources', 'cust_bindings', 'cust_profile_tags', 'cust_addresses', 'cust_contacts', 'cust_profiles', 'cust_book_grants', 'cust_books'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
