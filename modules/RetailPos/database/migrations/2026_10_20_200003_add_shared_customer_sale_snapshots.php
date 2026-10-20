<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_sales', function (Blueprint $table): void {
            $table->uuid('shared_customer_id')->nullable()->index();
            $table->json('buyer_snapshot')->nullable();
        });
        Schema::create('retail_customer_accounting_syncs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('command_key', 120);
            $table->string('fingerprint', 64);
            $table->json('response');
            $table->timestamps();
            $table->unique(['actor_id', 'command_key'], 'retail_customer_acct_command_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Customer sale snapshots are retained; use a forward migration.');
    }
};
