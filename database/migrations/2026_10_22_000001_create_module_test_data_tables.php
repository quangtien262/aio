<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_test_data_scopes', function (Blueprint $table): void {
            $table->string('website_key', 100)->primary();
            $table->timestamps();
        });
        Schema::create('module_test_data_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('website_key', 100);
            $table->string('module_key', 80);
            $table->unsignedBigInteger('actor_admin_id');
            $table->uuid('request_id');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('record_count')->default(0);
            $table->json('preview');
            $table->json('soft_references');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['website_key', 'module_key', 'actor_admin_id', 'request_id'], 'module_test_batch_request_uq');
            $table->index(['website_key', 'status', 'module_key'], 'module_test_batch_scope_idx');
        });
        Schema::create('module_test_data_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->string('table_name', 100);
            $table->json('record_key');
            $table->json('identity');
            $table->char('record_hash', 64);
            $table->unique(['table_name', 'record_hash'], 'module_test_record_owner_uq');
            $table->foreign('batch_id')->references('id')->on('module_test_data_batches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_test_data_records');
        Schema::dropIfExists('module_test_data_batches');
        Schema::dropIfExists('module_test_data_scopes');
    }
};
