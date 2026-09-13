<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_lifecycle_operations')) {
            Schema::create('module_lifecycle_operations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('operation_id')->unique();
                $table->string('module_key')->index();
                $table->string('operation', 30)->index();
                $table->string('status', 30)->index();
                $table->string('active_slot', 20)->nullable();
                $table->string('from_version')->nullable();
                $table->string('target_version')->nullable();
                $table->uuid('owner_token');
                $table->foreignId('initiated_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->json('context')->nullable();
                $table->json('error')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('heartbeat_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                // NULL is terminal/history; only one non-terminal sentinel may exist per module.
                $table->unique(['module_key', 'active_slot'], 'module_lifecycle_active_unique');
            });
        }

        if (! Schema::hasTable('module_role_definitions')) {
            Schema::create('module_role_definitions', function (Blueprint $table): void {
                $table->id();
                $table->string('module_key')->index();
                $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
                $table->string('role_key');
                $table->string('version');
                $table->string('introduced_version');
                $table->string('retired_version')->nullable();
                $table->string('assignment_channel', 40)->default('core');
                $table->json('permission_keys');
                $table->char('definition_hash', 64);
                $table->string('custom_role_policy', 20)->default('allow');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique('role_id', 'module_role_definition_role_unique');
                $table->unique(['module_key', 'role_key'], 'module_role_definition_key_unique');
                $table->index(['module_key', 'version', 'is_active'], 'module_role_definition_version_index');
            });
        }

        if (! Schema::hasTable('admin_reauth_proofs')) {
            Schema::create('admin_reauth_proofs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('module_key')->nullable()->index();
                $table->char('token_hash', 64)->unique();
                $table->uuid('nonce')->unique();
                $table->char('session_id_hash', 64);
                $table->unsignedBigInteger('auth_version');
                $table->json('factor_set');
                $table->string('scope_type', 40)->index();
                $table->text('scope_value');
                $table->string('action')->index();
                $table->string('subject_type')->default('resource');
                $table->string('subject_id');
                $table->char('payload_hash', 64);
                $table->char('ip_hash', 64)->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(
                    ['admin_id', 'session_id_hash', 'expires_at'],
                    'admin_reauth_session_expiry_index',
                );
                $table->index(
                    ['module_key', 'action'],
                    'admin_reauth_scope_action_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_reauth_proofs');
        Schema::dropIfExists('module_role_definitions');
        Schema::dropIfExists('module_lifecycle_operations');
    }
};
