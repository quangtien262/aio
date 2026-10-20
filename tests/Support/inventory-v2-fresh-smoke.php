<?php

use App\Core\Modules\ModuleManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection()->getConfig();
if (($connection['driver'] ?? null) !== 'mysql' || ! preg_match('/^aio_retail_gate_[a-f0-9]{16}$/D', (string) ($connection['database'] ?? ''))) {
    throw new RuntimeException('Run this worker only through retail-mysql-gates.php against its generated scratch database.');
}
Artisan::call('migrate', ['--force' => true]);
$manager = app(ModuleManager::class);
$legacy = in_array('--legacy-upgrade', $argv, true);
if ($legacy) {
    $manager->install('cms');
    $manager->enable('cms');
    $manager->install('catalog');
    $manager->enable('catalog');
    if (Artisan::call('migrate', ['--path' => 'modules/Inventory/database/migrations', '--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    $item = DB::table('inv_items')->insertGetId(['sku' => 'LEGACY', 'name' => 'Legacy', 'unit' => 'pcs', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $warehouse = DB::table('inv_warehouses')->insertGetId(['code' => 'OLD', 'name' => 'Old', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('inv_stock_balances')->insert(['warehouse_id' => $warehouse, 'item_id' => $item, 'location_key' => 0, 'batch_key' => 0, 'quantity_on_hand' => '12.375', 'quantity_reserved' => '0', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('module_installations')->insert(['key' => 'inventory', 'name' => 'Inventory', 'version' => '0.2.0', 'status' => 'enabled', 'website_types' => '[]', 'dependencies' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    if (in_array('--partial-ddl', $argv, true)) {
        // Simulate DDL committed before the remaining upgrade could run.
        Schema::table('inv_warehouses', fn (Blueprint $table) => $table->uuid('public_id')->nullable()->unique());
        Schema::table('inv_items', fn (Blueprint $table) => $table->unsignedBigInteger('merchandise_sku_id')->nullable()->unique());
    }
}
$manager->install('merchandise');
$manager->enable('merchandise');
if ($legacy) {
    $manager->upgrade('inventory');
} else {
    $manager->install('inventory');
    $manager->enable('inventory');
}
if ($legacy) {
    if (bccomp('12.375', (string) DB::table('inv_stock_balances')->where('item_id', $item)->value('quantity_on_hand'), 6) !== 0) {
        throw new RuntimeException('Legacy quantity changed.');
    }
    if (! DB::table('migrations')->where('migration', '2026_07_09_000001_create_inventory_module_tables')->exists()) {
        throw new RuntimeException('Legacy migration lineage lost.');
    }
    echo "inventory-v2 legacy upgrade OK\n";
    exit(0);
}
if (Schema::hasTable('catalog_products') || Schema::hasTable('cms_posts')) {
    throw new RuntimeException('Inventory installed optional schema.');
}
if (! Schema::hasTable('inv_reservations')) {
    throw new RuntimeException('Missing v2 schema.');
}
if (DB::table('migrations')->where('migration', '2026_07_09_000001_create_inventory_module_tables')->exists()) {
    throw new RuntimeException('Legacy migration falsely marked applied.');
}
echo "inventory-v2 standalone fresh OK\n";
