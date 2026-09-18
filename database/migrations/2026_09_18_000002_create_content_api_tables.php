<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('source_key', 80)->default('tech-content');
            $table->string('website_key');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['website_key', 'revoked_at']);
            $table->index(['source_key', 'website_key']);
        });

        Schema::create('content_api_resource_links', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 80);
            $table->string('website_key');
            $table->string('resource_type', 80);
            $table->string('external_id', 191);
            $table->unsignedBigInteger('resource_id');
            $table->char('payload_hash', 64)->nullable();
            $table->foreignId('last_token_id')->nullable()->constrained('content_api_tokens')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['source_key', 'website_key', 'resource_type', 'external_id'],
                'content_api_resource_external_unique',
            );
            $table->index(['website_key', 'resource_type', 'resource_id'], 'content_api_resource_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_api_resource_links');
        Schema::dropIfExists('content_api_tokens');
    }
};
