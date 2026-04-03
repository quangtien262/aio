<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
