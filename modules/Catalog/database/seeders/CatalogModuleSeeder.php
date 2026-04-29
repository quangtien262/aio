<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

if (! class_exists(__NAMESPACE__.'\\CatalogModuleSeeder', false)) {
    class CatalogModuleSeeder extends Seeder
    {
        public function run(): void
        {
            DB::table('catalog_products')->updateOrInsert(
                ['sku' => 'CATALOG-DEMO-001'],
                [
                    'name' => 'Sản phẩm demo',
                    'price' => 100000,
                    'stock' => 10,
                    'website_key' => 'website-main',
                    'owner_key' => 'owner-system',
                    'tenant_key' => 'tenant-a',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
