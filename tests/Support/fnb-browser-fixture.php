<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/** Creates a new isolated SQLite fixture. Never points migrations at the local application database. */
$root = dirname(__DIR__, 2);
$directory = $root.'/storage/framework/testing';
if (! is_dir($directory)) {
    mkdir($directory, 0770, true);
}
$databasePath = $directory.'/fnb-browser-'.bin2hex(random_bytes(6)).'.sqlite';
if (! touch($databasePath)) {
    throw new RuntimeException('Cannot create isolated browser fixture.');
}
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $directory.'/fnb-browser-no-cache.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $databasePath, 'SESSION_DRIVER' => 'database',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $databasePath) {
    throw new RuntimeException('Fixture database isolation check failed.');
}
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
Admin::query()->findOrFail(1)->forceFill([
    'username' => 'fnb-browser', 'email' => 'fnb-browser@example.test', 'password' => 'FnbBrowser123!', 'must_change_password' => false,
])->save();
$manager = app(ModuleManager::class);
$manager->install('fnb-pos');
$manager->enable('fnb-pos');
echo json_encode(['database_path' => $databasePath, 'website_key' => 'website-main', 'username' => 'fnb-browser'], JSON_THROW_ON_ERROR), PHP_EOL;
