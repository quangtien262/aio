<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_pages')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                if (! Schema::hasColumn('cms_pages', 'website_key')) {
                    $table->string('website_key')->nullable()->after('body')->index();
                }

                if (! Schema::hasColumn('cms_pages', 'owner_key')) {
                    $table->string('owner_key')->nullable()->after('website_key')->index();
                }

                if (! Schema::hasColumn('cms_pages', 'tenant_key')) {
                    $table->string('tenant_key')->nullable()->after('owner_key')->index();
                }
            });
        }

        if (Schema::hasTable('catalog_products')) {
            Schema::table('catalog_products', function (Blueprint $table): void {
                if (! Schema::hasColumn('catalog_products', 'website_key')) {
                    $table->string('website_key')->nullable()->after('stock')->index();
                }

                if (! Schema::hasColumn('catalog_products', 'owner_key')) {
                    $table->string('owner_key')->nullable()->after('website_key')->index();
                }

                if (! Schema::hasColumn('catalog_products', 'tenant_key')) {
                    $table->string('tenant_key')->nullable()->after('owner_key')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('catalog_products')) {
            Schema::table('catalog_products', function (Blueprint $table): void {
                $table->dropColumn(['website_key', 'owner_key', 'tenant_key']);
            });
        }

        if (Schema::hasTable('cms_pages')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->dropColumn(['website_key', 'owner_key', 'tenant_key']);
            });
        }
    }
};
