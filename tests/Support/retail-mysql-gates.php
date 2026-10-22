<?php

use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Core\Modules\ModuleManager;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Hooks\InventoryLifecycleHook;
use Modules\Inventory\Services\InventoryRuntimeState;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\RetailPos\Hooks\RetailLifecycleHook;
use Modules\RetailPos\Services\RetailRuntimeState;
use Modules\RetailPos\Services\RetailService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? 'orchestrate';
$emit = static function (array $result): void {
    echo 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n";
};
$require = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

if ($mode !== 'orchestrate') {
    try {
        $connection = DB::connection()->getConfig();
        $require(($connection['driver'] ?? null) === 'mysql' && preg_match('/^aio_retail_gate_[a-f0-9]{16}$/D', (string) ($connection['database'] ?? '')) === 1, 'Workers may only mutate a generated Retail scratch database.');
        $inventory = app(InventoryV2Service::class);
        if ($mode === 'setup') {
            $require(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Scratch core migration failed.');
            $manager = app(ModuleManager::class);
            foreach (['merchandise', 'inventory'] as $module) {
                $manager->install($module);
                $manager->enable($module);
            }
            $require(! Schema::hasTable('catalog_products') && ! Schema::hasTable('cms_posts'), 'Fresh Inventory created optional schemas.');
            $require(! DB::table('migrations')->where('migration', '2026_07_09_000001_create_inventory_module_tables')->exists(), 'Fresh baseline forged legacy migration history.');
            $actor = Admin::query()->create(['name' => 'Synthetic gate cashier', 'username' => 'gate-cashier', 'email' => 'gate@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true]);
            $catalog = DB::table('merch_catalogs')->insertGetId(['public_id' => (string) Str::uuid(), 'code' => 'GATE', 'name' => 'Synthetic retail gates', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $sku = DB::table('merch_skus')->insertGetId(['public_id' => (string) Str::uuid(), 'catalog_id' => $catalog, 'sku' => 'GATE-SKU', 'name' => 'Synthetic measured goods', 'base_unit' => 'kg', 'is_active' => true, 'tracking_mode' => 'none', 'created_at' => now(), 'updated_at' => now()]);
            $warehouse = $inventory->createWarehouse(['code' => 'GATE', 'name' => 'Synthetic gate warehouse', 'catalog_id' => $catalog, 'legal_entity_ref' => 'synthetic-legal-owner', 'timezone' => 'Asia/Ho_Chi_Minh']);
            $item = $inventory->ensureItem($sku);
            $inventory->receive(['warehouse_id' => $warehouse['id'], 'item_id' => $item, 'quantity' => '1', 'unit_cost' => '1.234567'], 'gate-opening', $actor->id);
            $emit(['actor' => $actor->id, 'warehouse' => $warehouse['id'], 'item' => $item]);
        } elseif ($mode === 'retail-drain-setup') {
            $manager = app(ModuleManager::class);
            $manager->install('retail-pos');
            $manager->enable('retail-pos');
            $actor = Admin::query()->findOrFail((int) getenv('RETAIL_GATE_ACTOR'));
            $retail = app(RetailService::class);
            $data = ['name' => 'Synthetic Retail drain gate'];
            $branch = $retail->command($actor, 'drain-retail-setup', 'setup', ['data' => $data], fn ($live) => $retail->setup($live, $data));
            $emit(['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id']]);
        } elseif ($mode === 'drain-command-worker') {
            $module = getenv('RETAIL_GATE_DRAIN_MODULE');
            $result = DB::transaction(function () use ($module, $inventory, $require): array {
                $actor = Admin::query()->findOrFail((int) getenv('RETAIL_GATE_ACTOR'));
                if ($module === 'inventory') {
                    $result = $inventory->reserve((int) getenv('RETAIL_GATE_WAREHOUSE'), (int) getenv('RETAIL_GATE_ITEM'), '1', 'drain-gate', 'drain-last-item-hold', $actor->id);
                } else {
                    $retail = app(RetailService::class);
                    $data = ['branch_id' => getenv('RETAIL_GATE_BRANCH'), 'terminal_id' => getenv('RETAIL_GATE_TERMINAL'), 'opening_minor' => '0'];
                    $result = $retail->command($actor, 'drain-open-shift', 'shift.open', ['data' => $data], fn ($live) => $retail->openShift($live, $data));
                }
                // The command has written its resource, but its transaction and shared guard remain open.
                echo "HELD\n";
                flush();
                $deadline = microtime(true) + 30;
                while (! is_file(getenv('RETAIL_GATE_BARRIER'))) {
                    $require(microtime(true) < $deadline, 'Drain command release barrier timed out.');
                    usleep(10000);
                }

                return $result;
            });
            $emit(['status' => 'committed', 'resource_id' => $result['public_id'] ?? $result['id']]);
        } elseif ($mode === 'drain-lifecycle-worker') {
            $module = getenv('RETAIL_GATE_DRAIN_MODULE');
            $state = app($module === 'inventory' ? InventoryRuntimeState::class : RetailRuntimeState::class);
            $coordinator = app(ModuleLifecycleCoordinator::class);
            $version = DB::table('module_installations')->where('key', $module)->value('version');
            $lease = $coordinator->begin($module, 'disable', $version, $version);
            echo "LEASE\n";
            flush();
            $state->prepare($lease);
            echo "PREPARED\n";
            flush();
            $blocked = false;
            try {
                if ($module === 'inventory') {
                    $require(DB::table('inv_reservations')->where('source_id', 'drain-gate')->where('status', 'active')->count() === 1, 'Drain did not observe the previously uncommitted reservation.');
                    $count = DB::table('inv_v2_operations')->count();
                    try {
                        $inventory->reserve((int) getenv('RETAIL_GATE_WAREHOUSE'), (int) getenv('RETAIL_GATE_ITEM'), '1', 'blocked-after-drain', 'blocked-drain-hold', (int) getenv('RETAIL_GATE_ACTOR'));
                        throw new RuntimeException('Prepared Inventory drain accepted a new command.');
                    } catch (HttpExceptionInterface $exception) {
                        $require($exception->getStatusCode() === 503, 'Prepared Inventory drain returned the wrong conflict.');
                    }
                    $require(DB::table('inv_v2_operations')->count() === $count, 'Blocked Inventory command wrote an idempotency record.');
                    try {
                        app(InventoryLifecycleHook::class)->preDisable(new ModuleLifecycleContext('disable', ['key' => $module]));
                    } catch (RuntimeException $exception) {
                        $require(str_contains($exception->getMessage(), 'giữ hàng'), 'Unexpected Inventory drain failure.');
                        $blocked = true;
                    }
                } else {
                    $require(DB::table('retail_shifts')->where('status', 'open')->count() === 1, 'Drain did not observe the previously uncommitted shift.');
                    $count = DB::table('retail_commands')->count();
                    $actor = Admin::query()->findOrFail((int) getenv('RETAIL_GATE_ACTOR'));
                    $retail = app(RetailService::class);
                    $data = ['branch_id' => getenv('RETAIL_GATE_BRANCH'), 'terminal_id' => getenv('RETAIL_GATE_TERMINAL'), 'opening_minor' => '0'];
                    try {
                        $retail->command($actor, 'blocked-drain-shift', 'shift.open', ['data' => $data], fn ($live) => $retail->openShift($live, $data));
                        throw new RuntimeException('Prepared Retail drain accepted a new command.');
                    } catch (HttpExceptionInterface $exception) {
                        $require($exception->getStatusCode() === 503, 'Prepared Retail drain returned the wrong conflict.');
                    }
                    $require(DB::table('retail_commands')->count() === $count, 'Blocked Retail command wrote an idempotency record.');
                    try {
                        app(RetailLifecycleHook::class)->preDisable(new ModuleLifecycleContext('disable', ['key' => $module]));
                    } catch (ValidationException $exception) {
                        $require(str_contains(implode(' ', $exception->errors()['module'] ?? []), 'đối soát ca'), 'Unexpected Retail drain failure.');
                        $blocked = true;
                    }
                }
                $require($blocked, 'Lifecycle drain incorrectly reported an empty committed scope.');
            } finally {
                $state->compensate($lease);
                $coordinator->fail($lease, new RuntimeException('Expected committed in-flight blocker'), false);
            }
            $table = $module === 'inventory' ? 'inv_runtime_states' : 'retail_runtime_states';
            $require(DB::table($table)->where('module_key', $module)->value('operational_state') === 'active', 'Failed disable did not restore the active runtime.');
            $emit(['status' => 'drain-waited-and-blocked', 'module' => $module]);
        } elseif ($mode === 'retail-e2e') {
            $manager = app(ModuleManager::class);
            $manager->install('retail-pos');
            $manager->enable('retail-pos');
            $actor = Admin::query()->findOrFail((int) getenv('RETAIL_GATE_ACTOR'));
            $retail = app(RetailService::class);
            $command = static function (string $operation, array $data, callable $mutation, string $resource = '', ?string $key = null) use ($retail, $actor): array {
                $key ??= 'mysql-retail-'.str_replace('.', '-', $operation).'-'.Str::random(8);

                return $retail->command($actor, $key, $operation, ['resource' => $resource, 'data' => $data], fn ($live) => $mutation($live, $key));
            };
            $data = ['name' => 'Synthetic MySQL retail chain'];
            $branch = $command('setup', $data, fn ($live) => $retail->setup($live, $data));
            $data = ['branch_id' => $branch['id'], 'sku' => 'MYSQL-COLA', 'name' => 'Synthetic Cola', 'base_unit' => 'pcs', 'price_minor' => '15000', 'opening_quantity' => '24', 'unit_cost' => '5000'];
            $product = $command('product.create', $data, fn ($live) => $retail->saveProduct($live, $data));
            $products = $retail->products($actor, $branch['id'], 'MYSQL-COLA');
            $item = $products[0]['inventory_item_id'];
            $data = ['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id'], 'opening_minor' => '0'];
            $shift = $command('shift.open', $data, fn ($live) => $retail->openShift($live, $data));
            $data = ['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $product['id'], 'quantity' => '6']]];
            $sale = $command('sale.create', $data, fn ($live) => $retail->createSale($live, $data));
            $data = ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => '90000', 'tendered_minor' => '100000']]];
            $key = 'mysql-retail-complete-once';
            $complete = $command('sale.complete', $data, fn ($live, $key) => $retail->completeSale($live, $sale['id'], $data, $key), $sale['id'], $key);
            $replay = $command('sale.complete', $data, fn ($live, $key) => $retail->completeSale($live, $sale['id'], $data, $key), $sale['id'], $key);
            $require($complete == $replay && $complete['status'] === 'completed', 'Retail MySQL sale replay changed semantic values.');
            $require($inventory->availability($branch['warehouse_id'], $item) === '18.000000', 'Retail sale did not atomically consume six units.');
            $require(DB::table('retail_tenders')->count() === 1 && (int) DB::table('retail_tenders')->value('change_minor') === 10000, 'Retail sale tender or change duplicated.');
            $data = ['expected_version' => $complete['version'], 'reason' => 'Synthetic MySQL return', 'lines' => [['sale_line_id' => $complete['lines'][0]['id'], 'quantity' => '1', 'disposition' => 'saleable']]];
            $returned = $command('sale.return', $data, fn ($live, $key) => $retail->returnSale($live, $sale['id'], $data, $key), $sale['id']);
            $require((int) $returned['amount_minor'] === 15000, 'Retail return did not preserve original allocation.');
            $data = ['shift_id' => $shift['id'], 'method' => 'cash'];
            $key = 'mysql-retail-refund-once';
            $refunded = $command('return.refund', $data, fn ($live) => $retail->refund($live, $returned['id'], $data), $returned['id'], $key);
            $command('return.refund', $data, fn ($live) => $retail->refund($live, $returned['id'], $data), $returned['id'], $key);
            $require($refunded['status'] === 'completed' && $inventory->availability($branch['warehouse_id'], $item) === '19.000000', 'Retail return/refund stock did not reconcile.');
            $require((int) DB::table('retail_shifts')->where('public_id', $shift['id'])->value('expected_minor') === 75000 && DB::table('retail_cash_movements')->count() === 2, 'Cash refund was applied more than once.');
            $data = ['expected_version' => $shift['version'], 'counted_minor' => '75000'];
            $closed = $command('shift.close', $data, fn ($live) => $retail->closeShift($live, $shift['id'], $data), $shift['id']);
            $data = ['expected_version' => $closed['version']];
            $reconciled = $command('shift.reconcile', $data, fn ($live) => $retail->closeShift($live, $shift['id'], $data, true), $shift['id']);
            $require($reconciled['status'] === 'reconciled' && (int) $reconciled['variance_minor'] === 0, 'Retail shift did not reconcile.');
            // Two already priced sales compete to purchase one remaining unit.
            $data = ['branch_id' => $branch['id'], 'sku' => 'MYSQL-LAST', 'name' => 'Synthetic last item', 'base_unit' => 'pcs', 'price_minor' => '1000', 'opening_quantity' => '1', 'unit_cost' => '500'];
            $last = $command('product.create', $data, fn ($live) => $retail->saveProduct($live, $data));
            $data = ['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id'], 'opening_minor' => '0'];
            $shift = $command('shift.open', $data, fn ($live) => $retail->openShift($live, $data));
            $sales = [];
            for ($i = 0; $i < 2; $i++) {
                $data = ['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $last['id'], 'quantity' => '1']]];
                $sales[] = $command('sale.create', $data, fn ($live) => $retail->createSale($live, $data))['id'];
            }
            $emit(['status' => 'retail-sale-return-refund-reconciled', 'sales' => $sales, 'last_item_id' => $retail->products($actor, $branch['id'], 'MYSQL-LAST')[0]['inventory_item_id'], 'warehouse_id' => $branch['warehouse_id']]);
        } elseif ($mode === 'retail-worker') {
            echo "READY\n";
            flush();
            $deadline = microtime(true) + 30;
            while (! is_file(getenv('RETAIL_GATE_BARRIER'))) {
                $require(microtime(true) < $deadline, 'Retail worker barrier timed out.');
                usleep(10000);
            }
            $actor = Admin::query()->findOrFail((int) getenv('RETAIL_GATE_ACTOR'));
            $retail = app(RetailService::class);
            $sale = getenv('RETAIL_GATE_SALE');
            $data = ['expected_version' => 1, 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]];
            $key = getenv('RETAIL_GATE_KEY');
            try {
                $result = $retail->command($actor, $key, 'sale.complete', ['resource' => $sale, 'data' => $data], fn ($live) => $retail->completeSale($live, $sale, $data, $key));
                $emit(['status' => $result['status'], 'sale_id' => $result['id']]);
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }
                $emit(['status' => 'conflict']);
            }
        } elseif ($mode === 'retail-inspect') {
            $require(DB::table('retail_sales')->where('status', 'completed')->count() === 2 && DB::table('retail_sales')->where('status', 'priced')->count() === 1, 'Retail competing completions did not preserve losing sale state.');
            $require(DB::table('retail_tenders')->count() === 2, 'Retail losing sale recorded a tender.');
            $require($inventory->availability((int) getenv('RETAIL_GATE_RETAIL_WAREHOUSE'), (int) getenv('RETAIL_GATE_RETAIL_ITEM')) === '0.000000', 'Retail last-item inventory did not reconcile.');
            $emit(['status' => 'retail-race-reconciled']);
        } elseif ($mode === 'reserve-worker') {
            echo "READY\n";
            flush();
            $deadline = microtime(true) + 30;
            while (! is_file(getenv('RETAIL_GATE_BARRIER'))) {
                $require(microtime(true) < $deadline, 'Worker barrier timed out.');
                usleep(10000);
            }
            try {
                $hold = $inventory->reserve((int) getenv('RETAIL_GATE_WAREHOUSE'), (int) getenv('RETAIL_GATE_ITEM'), '1', getenv('RETAIL_GATE_SOURCE'), getenv('RETAIL_GATE_KEY'), (int) getenv('RETAIL_GATE_ACTOR'));
                $emit(['status' => 'reserved', 'public_id' => $hold['public_id'], 'replayed' => $hold['replayed'] ?? false]);
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }
                $emit(['status' => 'conflict']);
            }
        } elseif ($mode === 'inspect-release') {
            $require($inventory->availability((int) getenv('RETAIL_GATE_WAREHOUSE'), (int) getenv('RETAIL_GATE_ITEM')) === '0.000000', 'Reserved item remains sellable.');
            $holds = DB::table('inv_reservations')->where('status', 'active')->get();
            $require($holds->count() === 1, 'Competing workers created multiple active holds for the last item.');
            foreach ($holds as $hold) {
                $inventory->releaseReservation($hold->public_id, 'release:'.$hold->public_id, (int) getenv('RETAIL_GATE_ACTOR'));
            }
            $require($inventory->availability((int) getenv('RETAIL_GATE_WAREHOUSE'), (int) getenv('RETAIL_GATE_ITEM')) === '1.000000', 'Releasing hold did not restore exact availability.');
            $emit(['status' => 'released']);
        } elseif ($mode === 'decimal') {
            $warehouse = (int) getenv('RETAIL_GATE_WAREHOUSE');
            $item = (int) getenv('RETAIL_GATE_ITEM');
            $actor = (int) getenv('RETAIL_GATE_ACTOR');
            $inventory->receive(['warehouse_id' => $warehouse, 'item_id' => $item, 'quantity' => '10.123456', 'unit_cost' => '1.234567'], 'gate-decimal-receipt', $actor);
            $hold = $inventory->reserve($warehouse, $item, '0.000001', 'decimal-sale', 'gate-decimal-hold', $actor);
            $first = $inventory->commitReservation($hold['public_id'], 'gate-decimal-commit', $actor);
            $second = $inventory->commitReservation($hold['public_id'], 'gate-decimal-commit', $actor);
            $require($inventory->availability($warehouse, $item) === '11.123455', 'MySQL decimal quantity lost precision.');
            $require($first['document_id'] === $second['document_id'] && $second['replayed'], 'Commit replay created another stock document.');
            $require((string) DB::table('inv_cost_layers')->where('warehouse_id', $warehouse)->orderByDesc('id')->value('unit_cost') === '1.234567', 'MySQL unit cost lost precision.');
            $emit(['status' => 'decimal-and-replay-ok']);
        } else {
            throw new RuntimeException('Unknown gate mode.');
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Gate failure: '.get_class($exception).': '.$exception->getMessage()."\n");
        exit(1);
    }
    exit(0);
}

// Only connection credentials are read from local config. No local business data is copied.
$config = DB::connection()->getConfig();
$require(($config['driver'] ?? null) === 'mysql', 'Retail competing-process gate requires MySQL.');
$base = [
    'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
    'DB_HOST' => (string) $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_SOCKET' => (string) ($config['unix_socket'] ?? ''),
    'DB_USERNAME' => (string) $config['username'], 'DB_PASSWORD' => (string) $config['password'],
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
    'AIO_SYSTEM_OWNER_PASSWORD' => bin2hex(random_bytes(24)).'Aa1!',
];
$created = [];
$barriers = [];
$children = [];
$exit = 0;
$create = static function () use (&$created): string {
    $database = 'aio_retail_gate_'.bin2hex(random_bytes(8));
    DB::connection()->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created[] = $database;

    return $database;
};
$parse = static function (Process $process): array {
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Scratch worker failed: '.$process->getOutput().$process->getErrorOutput());
    }
    if (! preg_match_all('/^RESULT:(.+)$/m', $process->getOutput(), $matches) || empty($matches[1])) {
        throw new RuntimeException('Scratch worker did not return gate evidence.');
    }

    return json_decode(end($matches[1]), true, flags: JSON_THROW_ON_ERROR);
};
$run = static function (string $mode, array $environment) use ($parse): array {
    $process = new Process([PHP_BINARY, __FILE__, $mode], base_path(), $environment);
    $process->setTimeout(180);
    $process->run();

    return $parse($process);
};
$race = static function (array $environment, bool $sameKey, string $mode = 'reserve-worker') use (&$barriers, &$children, $parse, $require): array {
    $barrier = sys_get_temp_dir().'/retail-barrier-'.bin2hex(random_bytes(12));
    $barriers[] = $barrier;
    $workers = [];
    for ($index = 1; $index <= 2; $index++) {
        $env = $environment + ['RETAIL_GATE_BARRIER' => $barrier, 'RETAIL_GATE_KEY' => $sameKey ? 'gate-same-command' : 'gate-race-'.$index, 'RETAIL_GATE_SOURCE' => $sameKey ? 'same-source' : 'race-'.$index];
        $env['RETAIL_GATE_SALE'] = $environment['RETAIL_GATE_SALE_'.$index] ?? '';
        $process = new Process([PHP_BINARY, __FILE__, $mode], base_path(), $env);
        $process->setTimeout(60);
        $process->start();
        $workers[] = $process;
        $children[] = $process;
    }
    $deadline = microtime(true) + 30;
    while (! str_contains($workers[0]->getOutput(), 'READY') || ! str_contains($workers[1]->getOutput(), 'READY')) {
        $require(microtime(true) < $deadline && $workers[0]->isRunning() && $workers[1]->isRunning(), 'Competing workers did not reach the shared barrier.');
        usleep(10000);
    }
    file_put_contents($barrier, 'GO');
    foreach ($workers as $worker) {
        $worker->wait();
    }

    return array_map($parse, $workers);
};
$drain = static function (array $environment, string $module) use (&$barriers, &$children, $parse, $require): void {
    $barrier = sys_get_temp_dir().'/retail-drain-'.bin2hex(random_bytes(12));
    $barriers[] = $barrier;
    $env = $environment + ['RETAIL_GATE_BARRIER' => $barrier, 'RETAIL_GATE_DRAIN_MODULE' => $module];
    $writer = new Process([PHP_BINARY, __FILE__, 'drain-command-worker'], base_path(), $env);
    $writer->setTimeout(60);
    $writer->start();
    $children[] = $writer;
    $deadline = microtime(true) + 30;
    while (! str_contains($writer->getOutput(), 'HELD')) {
        $require(microtime(true) < $deadline && $writer->isRunning(), 'In-flight writer did not hold the runtime barrier: '.$writer->getErrorOutput());
        usleep(10000);
    }
    $lifecycle = new Process([PHP_BINARY, __FILE__, 'drain-lifecycle-worker'], base_path(), $env);
    $lifecycle->setTimeout(60);
    $lifecycle->start();
    $children[] = $lifecycle;
    while (! str_contains($lifecycle->getOutput(), 'LEASE')) {
        $require(microtime(true) < $deadline && $lifecycle->isRunning(), 'Lifecycle worker did not start its durable lease: '.$lifecycle->getErrorOutput());
        usleep(10000);
    }
    usleep(250000);
    $require($lifecycle->isRunning() && ! str_contains($lifecycle->getOutput(), 'PREPARED'), 'Lifecycle passed the shared runtime barrier before the in-flight writer committed.');
    file_put_contents($barrier, 'COMMIT');
    $writer->wait();
    $lifecycle->wait();
    $require($parse($writer)['status'] === 'committed' && $parse($lifecycle)['status'] === 'drain-waited-and-blocked', 'In-flight lifecycle barrier did not preserve committed blockers.');
};

try {
    $database = $create();
    $env = $base + ['DB_DATABASE' => $database, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/'.$database.'-config.php'];
    $fixture = $run('setup', $env);
    echo "Fresh Inventory without CMS/Catalog PASS\n";
    $env += ['RETAIL_GATE_WAREHOUSE' => (string) $fixture['warehouse'], 'RETAIL_GATE_ITEM' => (string) $fixture['item'], 'RETAIL_GATE_ACTOR' => (string) $fixture['actor']];
    $results = $race($env, false);
    $require(count(array_filter($results, fn ($r) => $r['status'] === 'reserved')) === 1 && count(array_filter($results, fn ($r) => $r['status'] === 'conflict')) === 1, 'Last-item race did not yield exactly one winner.');
    $run('inspect-release', $env);
    echo "Competing-process last-item reservation PASS\n";
    $results = $race($env, true);
    $require($results[0]['status'] === 'reserved' && $results[1]['status'] === 'reserved' && $results[0]['public_id'] === $results[1]['public_id'], 'Idempotent competing workers did not receive the same reservation.');
    $run('inspect-release', $env);
    echo "Competing-process identical command replay PASS\n";
    $run('decimal', $env);
    echo "MySQL decimal and stock commit replay PASS\n";
    $drain($env, 'inventory');
    echo "MySQL Inventory v2 in-flight drain, new-command rejection and compensation PASS\n";
    $retail = $run('retail-e2e', $env);
    echo "MySQL Retail setup, sale, return, refund and shift reconciliation PASS\n";
    $env += ['RETAIL_GATE_SALE_1' => $retail['sales'][0], 'RETAIL_GATE_SALE_2' => $retail['sales'][1], 'RETAIL_GATE_RETAIL_ITEM' => (string) $retail['last_item_id'], 'RETAIL_GATE_RETAIL_WAREHOUSE' => (string) $retail['warehouse_id']];
    $results = $race($env, false, 'retail-worker');
    $require(count(array_filter($results, fn ($r) => $r['status'] === 'completed')) === 1 && count(array_filter($results, fn ($r) => $r['status'] === 'conflict')) === 1, 'Retail last-item race did not produce one completed sale.');
    $run('retail-inspect', $env);
    echo "MySQL competing-process Retail sale and tender atomicity PASS\n";
    $legacy = $create();
    $legacyEnv = array_replace($env, ['DB_DATABASE' => $legacy, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/'.$legacy.'-config.php']);
    $process = new Process([PHP_BINARY, base_path('tests/Support/inventory-v2-fresh-smoke.php'), '--legacy-upgrade', '--partial-ddl'], base_path(), $legacyEnv);
    $process->setTimeout(180);
    $process->run();
    $require($process->isSuccessful() && str_contains($process->getOutput(), 'inventory-v2 legacy upgrade OK'), 'MySQL legacy migration gate failed: '.$process->getOutput().$process->getErrorOutput());
    echo "MySQL legacy upgrade, partial DDL recovery and preserved lineage PASS\n";
    $retailDrainDatabase = $create();
    $retailDrainEnv = array_replace($base, ['DB_DATABASE' => $retailDrainDatabase, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/'.$retailDrainDatabase.'-config.php']);
    $drainFixture = $run('setup', $retailDrainEnv);
    $retailDrainEnv += ['RETAIL_GATE_ACTOR' => (string) $drainFixture['actor']];
    $drainBranch = $run('retail-drain-setup', $retailDrainEnv);
    $retailDrainEnv += ['RETAIL_GATE_BRANCH' => $drainBranch['branch_id'], 'RETAIL_GATE_TERMINAL' => $drainBranch['terminal_id']];
    $drain($retailDrainEnv, 'retail-pos');
    echo "MySQL Retail in-flight drain, new-command rejection and compensation PASS\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $exit = 1;
} finally {
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->stop(1);
        }
    }
    foreach ($barriers as $barrier) {
        if (is_file($barrier)) {
            unlink($barrier);
        }
    }
    foreach ($created as $database) {
        if (preg_match('/^aio_retail_gate_[a-f0-9]{16}$/D', $database)) {
            DB::connection()->statement('DROP DATABASE `'.$database.'`');
        }
    }
    echo "Only generated scratch databases removed.\n";
}
exit($exit);
