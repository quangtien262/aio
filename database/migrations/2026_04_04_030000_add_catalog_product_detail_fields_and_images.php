<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('catalog_products', 'detail_content')) {
                $table->longText('detail_content')->nullable()->after('short_description');
            }

            if (! Schema::hasColumn('catalog_products', 'highlights')) {
                $table->longText('highlights')->nullable()->after('detail_content');
            }

            if (! Schema::hasColumn('catalog_products', 'usage_terms')) {
                $table->longText('usage_terms')->nullable()->after('highlights');
            }

            if (! Schema::hasColumn('catalog_products', 'usage_location')) {
                $table->text('usage_location')->nullable()->after('usage_terms');
            }

            if (! Schema::hasColumn('catalog_products', 'sold_count')) {
                $table->unsignedInteger('sold_count')->default(0)->after('image_url');
            }

            if (! Schema::hasColumn('catalog_products', 'deal_end_at')) {
                $table->timestamp('deal_end_at')->nullable()->after('sold_count');
            }
        });

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

        Schema::table('catalog_products', function (Blueprint $table): void {
            $columns = collect([
                'detail_content',
                'highlights',
                'usage_terms',
                'usage_location',
                'sold_count',
                'deal_end_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('catalog_products', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
