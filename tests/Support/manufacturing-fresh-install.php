<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Commands\ManufacturingCommandService;
use Modules\Manufacturing\Services\Setup\ManufacturingSetupService;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->runningUnitTests() || config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), 'aio_mfg_fresh_')) {
    throw new RuntimeException('This probe only accepts its dedicated temporary SQLite database in production mode.');
}
Artisan::call('migrate', ['--force' => true]);
if (Schema::hasTable('mfg_companies') || Schema::hasTable('inv_trace_units') || Schema::hasTable('cms_pages')) {
    throw new RuntimeException('Core migrate unexpectedly installed a business module.');
}
$owner = Admin::query()->create(['name' => 'Isolated Owner', 'username' => 'fresh-owner', 'email' => 'owner@example.test', 'password' => 'IsolatedPassword123!',
    'is_active' => true, 'status' => 'active', 'auth_version' => 1, 'must_change_password' => false])->fresh();
foreach (['merchandise', 'inventory', 'manufacturing'] as $key) {
    app(ModuleManager::class)->install($key);
    app(ModuleManager::class)->enable($key);
}
$commands = app(ManufacturingCommandService::class);
$setup = app(ManufacturingSetupService::class);
$company = $commands->run($owner, 'company.create', ['code' => 'CLEAN'], 'fresh-company-0001', function (Admin $live): array {
    app(ManufacturingAccessService::class)->permission($live, 'manufacturing.settings');

    return ['permission' => 'manufacturing.settings'];
}, fn (Admin $live, array $scope): array => $setup->dispatch($live, 'company.create', $scope, ['code' => 'CLEAN', 'name' => 'Clean Company']));
$factory = $commands->run($owner, 'factory.create', ['code' => 'ONE', 'company' => $company['public_id']], 'fresh-factory-0001', function (Admin $live) use ($company): array {
    return ['company' => app(ManufacturingAccessService::class)->company($live, $company['public_id'], 'manufacturing.settings'), 'permission' => 'manufacturing.settings'];
}, fn (Admin $live, array $scope): array => $setup->dispatch($live, 'factory.create', $scope, ['code' => 'ONE', 'name' => 'Clean Factory']));
if (! Schema::hasTable('inv_trace_units') || ! DB::table('inv_warehouses')->where('id', $factory['warehouse_id'])->where('production_managed', true)->exists()) {
    throw new RuntimeException('Fresh factory did not receive a production-ready warehouse.');
}
foreach (['cms_pages', 'catalog_products', 'hrm_employees', 'payroll_periods', 'acct_organizations', 'cust_profiles', 'purchase_orders'] as $optionalTable) {
    if (Schema::hasTable($optionalTable)) {
        throw new RuntimeException('Unexpected optional schema: '.$optionalTable);
    }
}
echo json_encode(['ok' => true, 'module_versions' => DB::table('module_installations')->whereIn('key', ['merchandise', 'inventory', 'manufacturing'])->pluck('version', 'key'),
    'commands' => DB::table('mfg_commands')->count(), 'factories' => DB::table('mfg_factories')->count(), 'optional_schema_absent' => true], JSON_THROW_ON_ERROR);
