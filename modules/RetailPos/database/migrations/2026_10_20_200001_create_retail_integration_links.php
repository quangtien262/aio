<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_legal_accounting_links', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legal_entity_id')->unique()->constrained('retail_legal_entities')->restrictOnDelete();
            $t->unsignedBigInteger('organization_id')->unique();
            $t->timestamps();
        });
        Schema::create('retail_accounting_mappings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('branch_id')->unique()->constrained('retail_branches')->restrictOnDelete();
            $t->unsignedBigInteger('legal_entity_id');
            $t->unsignedBigInteger('organization_id');
            $t->boolean('enabled')->default(false);
            $t->unsignedBigInteger('authorized_by');
            $t->unsignedInteger('authorized_auth_version');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('retail_accounting_item_links', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('mapping_id')->constrained('retail_accounting_mappings')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id');
            $t->unsignedBigInteger('accounting_item_id');
            $t->timestamps();
            $t->unique(['mapping_id', 'sku_id']);
        });
        Schema::create('retail_integration_links', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('source_id', 100);
            $t->string('destination_module', 60);
            $t->string('operation_type', 60);
            $t->unsignedBigInteger('branch_id');
            $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('destination_id');
            $t->string('source_hash', 64);
            $t->string('contract_version', 40);
            $t->json('payload_snapshot');
            $t->timestamps();
            $t->unique(['source_id', 'destination_module', 'operation_type'], 'retail_integration_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_integration_links');
        Schema::dropIfExists('retail_accounting_item_links');
        Schema::dropIfExists('retail_accounting_mappings');
        Schema::dropIfExists('retail_legal_accounting_links');
    }
};
