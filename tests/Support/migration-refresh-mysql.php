<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Use credentials from the local connection, but never migrate its database.
// Every destructive command below targets a newly created random scratch DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.default');
$config = DB::connection($connection)->getConfig();
if (($config['driver'] ?? null) !== 'mysql') {
    throw new RuntimeException('This smoke test requires a local MySQL connection.');
}

$database = 'aio_refresh_test_'.bin2hex(random_bytes(8));
$created = false;
$exitCode = 0;
$environment = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/'.$database.'-config.php',
    'DB_CONNECTION' => 'mysql',
    'DB_URL' => '',
    'DB_HOST' => (string) $config['host'],
    'DB_PORT' => (string) $config['port'],
    'DB_SOCKET' => (string) ($config['unix_socket'] ?? ''),
    'DB_DATABASE' => $database,
    'DB_USERNAME' => (string) $config['username'],
    'DB_PASSWORD' => (string) $config['password'],
    'CACHE_STORE' => 'database',
    'SESSION_DRIVER' => 'database',
    'QUEUE_CONNECTION' => 'database',
    'MAIL_MAILER' => 'array',
    'AIO_SYSTEM_OWNER_PASSWORD' => bin2hex(random_bytes(24)).'Aa1!',
];
$run = function (array $arguments, string $label) use ($environment): void {
    $process = new Process([PHP_BINARY, ...$arguments], base_path(), $environment);
    $process->setTimeout(240);
    $process->run();
    if (! $process->isSuccessful() || str_contains($process->getOutput(), 'Migration not found')) {
        throw new RuntimeException($label." failed:\n".$process->getOutput().$process->getErrorOutput());
    }
    echo $label." PASS\n";
};

try {
    DB::connection($connection)->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $run(['artisan', 'migrate', '--force', '--no-interaction'], 'MySQL fresh core migrate');
    $run(['artisan', 'migrate:refresh', '--force', '--no-interaction'], 'MySQL core refresh');
    $run(['artisan', 'db:seed', '--force', '--no-interaction'], 'MySQL production seed');
    $install = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$manager = app(App\Core\Modules\ModuleManager::class);
foreach (['catalog', 'inventory', 'accounting-tax', 'minvoice-connector', 'hrm', 'payroll', 'project', 'real-estate', 'fnb-pos'] as $key) {
    $manager->install($key);
    $manager->enable($key);
}
PHP;
    $run(['-r', $install], 'MySQL all module installation');
    $run(['artisan', 'migrate:refresh', '--step=1', '--force', '--no-interaction'], 'MySQL partial module refresh');
    $run(['artisan', 'migrate:refresh', '--seed', '--force', '--no-interaction'], 'MySQL all module refresh and seed');
    $run(['-r', $install], 'MySQL all module reinstall');
    $run(['artisan', 'migrate:refresh', '--seed', '--force', '--no-interaction'], 'MySQL repeated refresh and seed');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $exitCode = 1;
} finally {
    if ($created && preg_match('/^aio_refresh_test_[a-f0-9]{16}$/D', $database)) {
        DB::connection($connection)->statement('DROP DATABASE `'.$database.'`');
        echo "Scratch database removed.\n";
    }
}
exit($exitCode);
