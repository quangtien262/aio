<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'catalog_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_favorites');
    }
};