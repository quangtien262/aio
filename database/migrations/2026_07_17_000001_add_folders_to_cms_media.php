<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_media_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('path')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('cms_media', function (Blueprint $table): void {
            $table->string('folder_path')->nullable()->after('alt_text')->index();
        });
    }

    public function down(): void
    {
        Schema::table('cms_media', function (Blueprint $table): void {
            $table->dropIndex(['folder_path']);
            $table->dropColumn('folder_path');
        });

        Schema::dropIfExists('cms_media_folders');
    }
};
