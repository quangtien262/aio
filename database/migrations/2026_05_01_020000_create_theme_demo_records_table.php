<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_demo_records', function (Blueprint $table): void {
            $table->id();
            $table->string('theme_key')->index();
            $table->string('preset_key')->nullable()->index();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->unique(['model_type', 'model_id'], 'theme_demo_records_model_unique');
            $table->index(['theme_key', 'model_type'], 'theme_demo_records_theme_model_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_demo_records');
    }
};
