<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Manufacturing\Services\Setup\ManufacturingSetupService;

// Creates only a new random SQLite fixture; never reuses an application database.
$root = dirname(__DIR__, 2);
$directory = $root.'/storage/framework/testing';
if (! is_dir($directory)) {
    mkdir($directory, 0770, true);
}
$databasePath = $directory.'/manufacturing-browser-'.bin2hex(random_bytes(8)).'.sqlite';
if (! touch($databasePath)) {
    throw new RuntimeException('Cannot create isolated manufacturing fixture.');
}
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $directory.'/manufacturing-no-cache.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $databasePath, 'DB_URL' => '', 'SESSION_DRIVER' => 'database',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_DEBUG' => 'true'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $databasePath) {
    throw new RuntimeException('Manufacturing fixture isolation check failed.');
}
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
Admin::query()->findOrFail(1)->forceFill(['username' => 'manufacturing-browser', 'email' => 'manufacturing-browser@example.test', 'password' => 'ManufacturingBrowser123!', 'must_change_password' => false])->save();
foreach (['merchandise', 'inventory', 'purchasing', 'manufacturing'] as $module) {
    app(ModuleManager::class)->install($module);
    app(ModuleManager::class)->enable($module);
}
$owner = Admin::query()->findOrFail(1)->fresh();
$setup = app(ManufacturingSetupService::class);
DB::transaction(function () use ($setup, $owner): void {
    $companyRef = $setup->dispatch($owner, 'company.create', [], ['code' => 'NATIVE-OP', 'name' => 'Native operator enterprise']);
    $company = DB::table('mfg_companies')->where('public_id', $companyRef['id'])->first();
    $factoryRef = $setup->dispatch($owner, 'factory.create', ['company' => $company], ['code' => 'NATIVE-OP', 'name' => 'Native operator factory', 'timezone' => 'Asia/Ho_Chi_Minh']);
    $factory = DB::table('mfg_factories')->where('public_id', $factoryRef['id'])->first();
    $setup->dispatch($owner, 'workshop.create', ['company' => $company, 'factory' => $factory], ['code' => 'OP-WORKSHOP', 'name' => 'Native operator workshop']);
    $operator = Admin::query()->create(['name' => 'Native Manufacturing Operator', 'username' => 'manufacturing-operator', 'email' => 'manufacturing-operator@example.test',
        'password' => 'ManufacturingOperator123!', 'must_change_password' => true, 'is_active' => true, 'status' => 'active', 'auth_version' => 1]);
    $role = Role::query()->where('key', 'manufacturing.operator')->firstOrFail();
    AdminRoleAssignment::query()->create(['admin_id' => $operator->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
    D::insert('mfg_memberships', ['company_id' => $company->id, 'factory_id' => $factory->id, 'admin_id' => $operator->id, 'role' => 'operator', 'status' => 'active']);
    if ($operator->roleAssignments()->where('scope_type', 'website')->exists() || $operator->isSuperAdmin()) throw new RuntimeException('Operator fixture must have only Manufacturing permissions and native membership.');
});
echo json_encode(['database_path' => $databasePath, 'username' => 'manufacturing-browser', 'login_secret' => bin2hex(random_bytes(24))], JSON_THROW_ON_ERROR), PHP_EOL;
