<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_categories', function (Blueprint $table): void {
            $table->string('image_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cms_categories', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};
