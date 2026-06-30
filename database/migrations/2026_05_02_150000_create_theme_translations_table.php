<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('theme_key');
            $table->string('locale', 12);
            $table->string('group')->default('static');
            $table->string('translation_key');
            $table->text('value')->nullable();
            $table->timestamps();

            // $table->unique(['theme_key', 'locale', 'group', 'translation_key'], 'theme_translations_unique_entry');
            // $table->index(['theme_key', 'locale'], 'theme_translations_theme_locale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_translations');
    }
};
