<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ph_prescription_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('chain_id')->index();
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedBigInteger('target_pharmacy_id')->index();
            $table->unsignedBigInteger('prescription_id')->index();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedInteger('patient_version');
            $table->unsignedBigInteger('actor_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('active');
            $table->dateTime('consented_at');
            $table->dateTime('expires_at');
            $table->longText('encrypted_consent');
            $table->timestamps();
            $table->index(['prescription_id', 'target_pharmacy_id', 'status'], 'ph_rx_transfer_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ph_prescription_transfers');
    }
};
