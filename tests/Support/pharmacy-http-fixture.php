<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Creates an isolated, synthetic browser-test installation. Never accepts a DB
// connection or path from .env. The environment manifest is private test data.
$fixtureDirectory = '/private/tmp/aio-pharmacy-http-'.bin2hex(random_bytes(6));
if (! mkdir($fixtureDirectory, 0700, true)) {
    throw new RuntimeException('Cannot create isolated fixture directory.');
}
foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/private'] as $directory) {
    mkdir($fixtureDirectory.'/'.$directory, 0700, true);
}
$database = $fixtureDirectory.'/database.sqlite';
touch($database);
$password = getenv('PHARMACY_TEST_PASSWORD') ?: 'Pharmacy-UAT-'.bin2hex(random_bytes(16)).'!';
$environment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'true', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => 'http://127.0.0.1:8131', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_DOMAIN' => 'null',
    'SESSION_SECURE_COOKIE' => 'false', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'single',
    'AIO_SYSTEM_OWNER_PASSWORD' => $password, 'LARAVEL_STORAGE_PATH' => $fixtureDirectory.'/storage',
    'APP_CONFIG_CACHE' => $fixtureDirectory.'/config.php', 'APP_ROUTES_CACHE' => $fixtureDirectory.'/routes.php',
    'APP_EVENTS_CACHE' => $fixtureDirectory.'/events.php', 'APP_SERVICES_CACHE' => $fixtureDirectory.'/services.php',
    'APP_PACKAGES_CACHE' => $fixtureDirectory.'/packages.php', 'PHARMACY_NETWORK_ENABLED' => 'false',
    'PHARMACY_PRODUCTION_ENABLED' => 'false',
];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection()->getConfig();
if (! $app->environment('testing') || ($connection['driver'] ?? null) !== 'sqlite' || ($connection['database'] ?? null) !== $database || ! str_starts_with($database, '/private/tmp/aio-pharmacy-http-')) {
    throw new RuntimeException('Refusing to prepare any non-scratch database.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
if (Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
$owner = Admin::query()->findOrFail(1);
$owner->forceFill(['username' => 'pharmacy-fixture', 'password' => Hash::make($password), 'must_change_password' => false])->save();
auth('admin')->setUser($owner);
$manager = app(ModuleManager::class);
foreach (['merchandise', 'inventory', 'purchasing', 'pharmacy'] as $key) {
    $manager->install($key);
    $manager->enable($key);
}
$reader = Admin::query()->create(['name' => 'Synthetic report reader', 'username' => 'pharmacy-report-fixture', 'email' => 'pharmacy-report-fixture@example.invalid', 'password' => $password, 'is_active' => true, 'status' => 'active', 'must_change_password' => false]);
$readerRole = Role::query()->create(['key' => 'pharmacy-http-report-reader', 'name' => 'Synthetic Pharmacy report reader', 'status' => 'active', 'is_system' => false, 'is_assignable' => true]);
$readerPermissions = Permission::query()->whereIn('key', ['pharmacy.view', 'pharmacy.report.view'])->pluck('id');
if ($readerPermissions->count() !== 2) {
    throw new RuntimeException('The Pharmacy Core permission catalog was not registered.');
}
$readerRole->permissions()->sync($readerPermissions);
$reader->roles()->attach($readerRole->id, ['scope_type' => 'global', 'scope_value' => null, 'assigned_by' => 1]);
// Native Pharmacy must work even when there is no active website or profile.
DB::table('sites')->delete();
DB::table('site_profiles')->delete();
$manifest = ['directory' => $fixtureDirectory, 'database' => $database, 'environment' => $environment, 'username' => 'pharmacy-fixture', 'password' => $password, 'reader' => ['id' => $reader->id, 'username' => $reader->username, 'password' => $password]];
file_put_contents($fixtureDirectory.'/fixture.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
chmod($fixtureDirectory.'/fixture.json', 0600);
echo $fixtureDirectory."/fixture.json\n";
