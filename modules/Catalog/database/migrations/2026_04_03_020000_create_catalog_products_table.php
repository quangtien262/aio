<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_categories')) {
            Schema::create('catalog_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('image_url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('website_key')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('sku')->unique();
                $table->decimal('price', 12, 2)->default(0);
                $table->unsignedInteger('stock')->default(0);
                $table->string('website_key')->nullable()->index();
                $table->longText('detail_content')->nullable();
                $table->longText('highlights')->nullable();
                $table->longText('usage_terms')->nullable();
                $table->text('usage_location')->nullable();
                $table->unsignedInteger('sold_count')->default(0);
                $table->timestamp('deal_end_at')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_title')->nullable();
                $table->text('meta_keywords')->nullable();
                $table->string('slug')->nullable()->index();
                $table->foreignId('catalog_category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
                $table->text('short_description')->nullable();
                $table->string('image_url')->nullable();
                $table->decimal('original_price', 12, 2)->nullable();
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_highlight')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalog_product_images')) {
            Schema::create('catalog_product_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('catalog_product_id')->constrained('catalog_products')->cascadeOnDelete();
                $table->string('image_url');
                $table->string('alt_text')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_images');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_categories');
    }
};
