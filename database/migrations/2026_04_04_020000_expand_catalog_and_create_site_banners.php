<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_categories')) {
            Schema::create('catalog_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image_url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('website_key')->nullable()->index();
                $table->string('owner_key')->nullable()->index();
                $table->string('tenant_key')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_banners')) {
            Schema::create('site_banners', function (Blueprint $table) {
                $table->id();
                $table->string('theme_key')->nullable()->index();
                $table->string('placement')->index();
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->string('image_url');
                $table->string('link_url')->nullable();
                $table->string('badge')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('website_key')->nullable()->index();
                $table->string('owner_key')->nullable()->index();
                $table->string('tenant_key')->nullable()->index();
                $table->timestamps();
            });
        }

        Schema::table('catalog_products', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_products', 'catalog_category_id')) {
                $table->foreignId('catalog_category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('catalog_products', 'slug')) {
                $table->string('slug')->nullable()->index();
            }

            if (! Schema::hasColumn('catalog_products', 'short_description')) {
                $table->text('short_description')->nullable();
            }

            if (! Schema::hasColumn('catalog_products', 'image_url')) {
                $table->string('image_url')->nullable();
            }

            if (! Schema::hasColumn('catalog_products', 'original_price')) {
                $table->decimal('original_price', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('catalog_products', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }

            if (! Schema::hasColumn('catalog_products', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }

            if (! Schema::hasColumn('catalog_products', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_products', 'catalog_category_id')) {
                $table->dropConstrainedForeignId('catalog_category_id');
            }

            if (Schema::hasColumn('catalog_products', 'slug')) {
                $table->dropIndex(['slug']);
            }

            $columns = collect([
                'slug',
                'short_description',
                'image_url',
                'original_price',
                'is_featured',
                'sort_order',
                'is_active',
            ])->filter(fn (string $column): bool => Schema::hasColumn('catalog_products', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('site_banners');
        Schema::dropIfExists('catalog_categories');
    }
};
