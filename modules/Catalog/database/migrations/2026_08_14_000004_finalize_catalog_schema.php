<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->finalizeCategories();
        $this->finalizeProducts();
        $this->addDeferredCoreForeignKey('order_items', 'catalog_product_id', 'null');
        $this->addDeferredCoreForeignKey('customer_favorites', 'catalog_product_id', 'cascade');
    }

    public function down(): void
    {
        $this->dropDeferredCoreForeignKey('customer_favorites', 'catalog_product_id');
        $this->dropDeferredCoreForeignKey('order_items', 'catalog_product_id');

        // Website-scoped uniqueness and localized SEO columns are data-safe
        // schema upgrades. Keep them when rolling back this reconciliation.
    }

    private function finalizeCategories(): void
    {
        if (! Schema::hasTable('catalog_categories')) {
            return;
        }

        Schema::table('catalog_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('catalog_categories', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('description');
            }

            if (! Schema::hasColumn('catalog_categories', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }
        });

        DB::table('catalog_categories')->whereNull('website_key')->update(['website_key' => 'website-main']);

        $this->dropIndexIfExists('catalog_categories', 'catalog_categories_slug_unique', unique: true);
        $this->addIndexIfMissing(
            'catalog_categories',
            ['website_key', 'slug'],
            'catalog_categories_website_slug_unique',
            unique: true,
        );
        $this->addIndexIfMissing(
            'catalog_categories',
            ['website_key', 'parent_id'],
            'catalog_categories_website_parent_idx',
        );
    }

    private function finalizeProducts(): void
    {
        if (! Schema::hasTable('catalog_products')) {
            return;
        }

        Schema::table('catalog_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('catalog_products', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('meta_description');
            }

            if (! Schema::hasColumn('catalog_products', 'is_highlight')) {
                $table->boolean('is_highlight')->default(false)->index();
            }
        });

        DB::table('catalog_products')->whereNull('website_key')->update(['website_key' => 'website-main']);

        $this->dropIndexIfExists('catalog_products', 'catalog_products_sku_unique', unique: true);
        $this->addIndexIfMissing(
            'catalog_products',
            ['website_key', 'sku'],
            'catalog_products_website_sku_unique',
            unique: true,
        );
        $this->addIndexIfMissing(
            'catalog_products',
            ['website_key', 'slug'],
            'catalog_products_website_slug_idx',
        );
        $this->addIndexIfMissing(
            'catalog_products',
            ['website_key', 'catalog_category_id'],
            'catalog_products_website_category_idx',
        );
    }

    private function addDeferredCoreForeignKey(string $tableName, string $column, string $onDelete): void
    {
        $foreignName = "{$tableName}_{$column}_foreign";

        if (
            ! Schema::hasTable($tableName)
            || ! Schema::hasColumn($tableName, $column)
            || $this->hasForeignKey($tableName, $foreignName)
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $foreignName, $onDelete): void {
            $foreign = $table->foreign($column, $foreignName)
                ->references('id')
                ->on('catalog_products');

            $onDelete === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
        });
    }

    private function dropDeferredCoreForeignKey(string $tableName, string $column): void
    {
        $foreignName = "{$tableName}_{$column}_foreign";

        if (! Schema::hasTable($tableName) || ! $this->hasForeignKey($tableName, $foreignName)) {
            return;
        }

        Schema::table(
            $tableName,
            fn (Blueprint $table): mixed => $table->dropForeign($foreignName),
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(
        string $tableName,
        array $columns,
        string $indexName,
        bool $unique = false,
    ): void {
        if ($this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName, $unique): void {
            $unique
                ? $table->unique($columns, $indexName)
                : $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName, bool $unique = false): void
    {
        if (! $this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName, $unique): void {
            $unique ? $table->dropUnique($indexName) : $table->dropIndex($indexName);
        });
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return collect(Schema::getIndexes($tableName))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === $indexName);
    }

    private function hasForeignKey(string $tableName, string $foreignName): bool
    {
        return collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreign): bool => ($foreign['name'] ?? '') === $foreignName);
    }
};
