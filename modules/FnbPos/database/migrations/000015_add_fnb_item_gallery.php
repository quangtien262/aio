<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fnb_menu_items', fn (Blueprint $table) => $table->json('gallery_images')->nullable());
    }

    public function down(): void
    {
        Schema::table('fnb_menu_items', fn (Blueprint $table) => $table->dropColumn('gallery_images'));
    }
};
