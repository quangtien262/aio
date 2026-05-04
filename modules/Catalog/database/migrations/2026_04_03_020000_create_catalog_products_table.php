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

            $table->longText('detail_content')->nullable();
            $table->longText('highlights')->nullable();
            $table->longText('usage_terms')->nullable();
            $table->text('usage_location')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->timestamp('deal_end_at')->nullable();

            $table->text('meta_description')->nullable();
            $table->text('meta_title')->nullable();


            $table->string('slug')->nullable()->index();
            $table->foreignId('catalog_category_id')->nullable();
            $table->text('short_description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);


            $table->timestamps();
        });

        Schema::create('catalog_product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->string('image_url');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_product_images');
    }
};
