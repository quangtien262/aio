<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ph_patients', function (Blueprint $table): void {
            $table->char('phone_lookup', 64)->nullable();
            $table->index(['chain_id', 'pharmacy_id', 'phone_lookup'], 'ph_patient_phone_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('ph_patients', function (Blueprint $table): void {
            $table->dropIndex('ph_patient_phone_lookup');
            $table->dropColumn('phone_lookup');
        });
    }
};
