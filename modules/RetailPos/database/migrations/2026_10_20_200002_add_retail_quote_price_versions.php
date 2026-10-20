<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_sale_lines', function (Blueprint $table): void {
            $table->unsignedInteger('price_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('retail_sale_lines', function (Blueprint $table): void {
            $table->dropColumn('price_version');
        });
    }
};
