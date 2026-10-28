<?php

/**
 * Local service microbenchmark, never an HTTP/WAN/production capacity certification.
 * php tests/Support/pharmacy-load.php --allow-isolated-mysql --points=10 --workers=4 --sales-per-point=2
 */
use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pharmacy\Services\PharmacyService;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$options = ['points' => 10, 'workers' => 4, 'sales-per-point' => 2, 'worker' => false, 'allow-isolated-mysql' => false];
foreach (array_slice($argv, 1) as $argument) {
    if (in_array($argument, ['--allow-isolated-mysql', '--worker'], true)) {
        $options[substr($argument, 2)] = true;
    } elseif (preg_match('/^--(points|workers|sales-per-point)=(\d+)$/D', $argument, $match)) {
        $options[$match[1]] = (int) $match[2];
    } else {
        fwrite(STDERR, "Unknown argument; use --allow-isolated-mysql --points=10|50|100 --workers=1..8 --sales-per-point=1..20.\n");
        exit(2);
    }
}
if (! $options['allow-isolated-mysql'] || ! in_array($options['points'], [10, 50, 100], true) || $options['workers'] < 1 || $options['workers'] > 8 || $options['sales-per-point'] < 1 || $options['sales-per-point'] > 20) {
    fwrite(STDERR, "Requires explicit scratch authorization and bounded workload options. No business database is used.\n");
    exit(2);
}
$environment = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
$setting = fn (string $key, mixed $default = null) => getenv($key) !== false ? getenv($key) : ($environment[$key] ?? $default);
if ($setting('DB_CONNECTION', 'mysql') !== 'mysql' || ! in_array($setting('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true) || $setting('DB_URL')) {
    throw new RuntimeException('Only unrouted local MySQL is supported.');
}
$isWorker = $options['worker'];
$name = $isWorker ? (string) getenv('PHARMACY_LOAD_SCRATCH_DB') : 'aio_pharmacy_load_'.bin2hex(random_bytes(8));
$nonce = $isWorker ? (string) getenv('PHARMACY_LOAD_NONCE') : bin2hex(random_bytes(32));
if (! preg_match('/^aio_pharmacy_load_[a-f0-9]{16}$/D', $name) || $name === ($environment['DB_DATABASE'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
    throw new RuntimeException('Scratch database or run identity rejected.');
}
$control = new PDO('mysql:host='.$setting('DB_HOST', '127.0.0.1').';port='.$setting('DB_PORT', 3306).';charset=utf8mb4', $setting('DB_USERNAME'), $setting('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false;
$processes = [];
$report = ['kind' => 'local_pharmacy_service_microbenchmark', 'status' => 'failed', 'workload' => ['points' => $options['points'], 'workers' => $options['workers'], 'sales_per_point' => $options['sales-per-point'], 'expected_sales' => $options['points'] * $options['sales-per-point'], 'one_line_per_sale' => true, 'units_per_sale' => 1, 'price_minor' => 1000, 'shared_chain_and_sku' => true, 'distinct_actor_counter_shift_warehouse_per_point' => true], 'cleanup_verified' => false];
$assertions = 0;
$check = function (bool $condition, string $message) use (&$assertions): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};
$percentiles = static function (array $samples): array {
    sort($samples, SORT_NUMERIC);
    $count = count($samples);
    if (! $count) {
        return ['samples' => 0, 'p50_ms' => null, 'p95_ms' => null, 'max_ms' => null];
    }

    return ['samples' => $count, 'p50_ms' => round($samples[max(0, (int) ceil($count * .50) - 1)], 3), 'p95_ms' => round($samples[max(0, (int) ceil($count * .95) - 1)], 3), 'max_ms' => round($samples[$count - 1], 3)];
};
if (! $isWorker && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, function (): void {
            throw new RuntimeException('Workload interrupted; cleaning scratch resources.');
        });
    }
}
try {
    if (! $isWorker) {
        $control->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created = true;
        $control->exec('CREATE TABLE `'.$name.'`.`pharmacy_load_guard` (id INT PRIMARY KEY, run_nonce CHAR(64) NOT NULL)');
        $guard = $control->prepare('INSERT INTO `'.$name.'`.`pharmacy_load_guard` (id,run_nonce) VALUES (1,?)');
        $guard->execute([$nonce]);
    } else {
        $guard = $control->query('SELECT run_nonce FROM `'.$name.'`.`pharmacy_load_guard` WHERE id=1')->fetchColumn();
        $check(is_string($guard) && hash_equals($guard, $nonce), 'Worker does not belong to this isolated run.');
    }
    foreach (['APP_ENV' => 'local', 'DB_DATABASE' => $name, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/pharmacy-load-unused-'.bin2hex(random_bytes(8)).'.php', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $check(DB::connection()->getDatabaseName() === $name, 'Scratch database must be selected before application bootstrap.');
    config(['pharmacy.network_enabled' => false, 'pharmacy.production_enabled' => false]);
    $service = app(PharmacyService::class);
    $command = fn (Admin $actor, string $action, array $input) => $service->command($actor, $action, $input, 'synthetic-load:'.Str::uuid());
    if ($isWorker) {
        $input = json_decode((string) getenv('PHARMACY_LOAD_INPUT'), true, flags: JSON_THROW_ON_ERROR);
        $check(is_array($input['points'] ?? null) && count($input['points']) <= 100, 'Worker payload is not bounded.');
        $delay = (float) ($input['start_at'] ?? 0) - microtime(true);
        if ($delay > 0 && $delay < 10) {
            usleep((int) ($delay * 1000000));
        }
        $samples = ['sale' => [], 'dispensing.create' => [], 'dispensing.prepare' => [], 'dispensing.review' => [], 'dispensing.finalize' => [], 'dispensing.handover' => []];
        $completed = 0;
        $started = microtime(true);
        for ($sale = 0; $sale < $options['sales-per-point']; $sale++) {
            foreach ($input['points'] as $point) {
                $actor = Admin::query()->findOrFail($point['actor_id']);
                $saleStarted = hrtime(true);
                $timed = function (string $action, array $payload) use ($actor, $command, &$samples): array {
                    $start = hrtime(true);
                    $result = $command($actor, $action, $payload);
                    $samples[$action][] = (hrtime(true) - $start) / 1000000;

                    return $result;
                };
                $d = $timed('dispensing.create', ['pharmacy_id' => $point['pharmacy_id'], 'mode' => 'otc', 'lines' => [['product_id' => $point['product_id'], 'quantity' => '1']]]);
                $d = $timed('dispensing.prepare', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $point['batch_id'], 'quantity' => '1']]]);
                $d = $timed('dispensing.review', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic workload professional review']);
                $d = $timed('dispensing.finalize', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $point['shift_id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]]);
                $d = $timed('dispensing.handover', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Synthetic scratch recipient']);
                $check($d['status'] === 'handed_over', 'Full OTC lifecycle did not finish.');
                $samples['sale'][] = (hrtime(true) - $saleStarted) / 1000000;
                $completed++;
            }
        }
        echo json_encode(['status' => 'ok', 'completed' => $completed, 'started_at' => $started, 'finished_at' => microtime(true), 'peak_memory_bytes' => memory_get_peak_usage(true), 'samples_ms' => $samples], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $setupStarted = hrtime(true);
    $check(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Scratch core migrations failed.');
    $check(Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]) === 0, 'Scratch core fixtures failed.');
    foreach (['merchandise', 'inventory', 'pharmacy'] as $module) {
        app(ModuleManager::class)->install($module);
        app(ModuleManager::class)->enable($module);
    }
    $owner = Admin::query()->findOrFail(1);
    $owner->update(['must_change_password' => false]);
    $setup = $command($owner, 'setup', ['chain_name' => 'Synthetic workload chain', 'pharmacy_name' => 'Synthetic point 1', 'code' => 'LOAD']);
    $product = $command($owner, 'product.save', ['pharmacy_id' => $setup['pharmacy']['id'], 'name' => 'Synthetic common OTC medicine', 'code' => 'SYNTHETIC-LOAD', 'registration_number' => 'SYNTHETIC-NOT-A-REGISTRATION', 'base_unit' => 'vien', 'price_minor' => '1000']);
    $permissions = ['pharmacy.view', 'pharmacy.patient.view', 'pharmacy.dispensing.view', 'pharmacy.dispensing.manage', 'pharmacy.dispensing.review', 'pharmacy.dispensing.handover', 'pharmacy.sale.create', 'pharmacy.sale.view', 'pharmacy.payment.collect', 'pharmacy.shift.manage'];
    $role = Role::query()->create(['name' => 'Synthetic point worker', 'key' => 'synthetic-pharmacy-load']);
    $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
    $points = [];
    $openingStock = $options['sales-per-point'] + 5;
    for ($i = 0; $i < $options['points']; $i++) {
        $point = $i === 0 ? $setup['pharmacy'] : $command($owner, 'pharmacy.create', ['chain_id' => $setup['chain']['id'], 'name' => 'Synthetic point '.($i + 1), 'code' => 'LOAD-'.($i + 1)]);
        if ($i > 0) {
            $command($owner, 'price.save', ['pharmacy_id' => $point['id'], 'product_id' => $product['id'], 'price_minor' => '1000']);
        }
        $actor = Admin::query()->create(['name' => 'Synthetic pharmacist '.($i + 1), 'username' => 'synthetic-load-'.($i + 1), 'email' => 'synthetic-load-'.($i + 1).'@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'must_change_password' => false]);
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        $command($owner, 'membership.save', ['pharmacy_id' => $point['id'], 'admin_id' => $actor->id, 'permissions' => $permissions]);
        foreach ([$owner, $actor] as $professional) {
            $command($owner, 'credential.save', ['pharmacy_id' => $point['id'], 'admin_id' => $professional->id, 'license_number' => 'SYNTHETIC-NOT-A-LICENSE-'.$professional->id, 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic scratch qualification only']);
        }
        $receipt = $command($owner, 'receipt.create', ['pharmacy_id' => $point['id'], 'product_id' => $product['id'], 'quantity' => (string) $openingStock, 'unit_cost' => '400', 'batch_code' => 'SYNTHETIC-LOT-'.($i + 1), 'expires_at' => now()->addYear()->toDateString()]);
        $command($owner, 'receipt.inspect', ['pharmacy_id' => $point['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspected workload lot']);
        $actor = $actor->fresh();
        $shift = $command($actor, 'shift.open', ['pharmacy_id' => $point['id'], 'counter_id' => $point['counter']['id'], 'opening_minor' => '0']);
        $points[] = ['pharmacy_id' => $point['id'], 'warehouse_id' => (int) DB::table('ph_pharmacies')->where('public_id', $point['id'])->value('warehouse_id'), 'product_id' => $product['id'], 'batch_id' => $receipt['batch_id'], 'actor_id' => $actor->id, 'shift_id' => $shift['id']];
    }
    $report['setup_seconds'] = round((hrtime(true) - $setupStarted) / 1e9, 3);
    $hardware = ['os' => PHP_OS_FAMILY, 'kernel' => php_uname('r'), 'architecture' => php_uname('m'), 'php_version' => PHP_VERSION, 'php_memory_limit' => ini_get('memory_limit')];
    if (PHP_OS_FAMILY === 'Darwin') {
        foreach (['cpu' => 'machdep.cpu.brand_string', 'physical_cores' => 'hw.physicalcpu', 'logical_cores' => 'hw.logicalcpu', 'memory_bytes' => 'hw.memsize'] as $field => $sysctl) {
            $probe = new Process(['sysctl', '-n', $sysctl]);
            $probe->setTimeout(2);
            $probe->run();
            $hardware[$field] = $probe->isSuccessful() ? trim($probe->getOutput()) : null;
        }
    }
    $report['environment'] = ['hardware' => $hardware, 'database' => (array) DB::selectOne('SELECT VERSION() AS version, @@transaction_isolation AS transaction_isolation, @@innodb_buffer_pool_size AS innodb_buffer_pool_bytes, @@max_connections AS max_connections'), 'module_versions' => DB::table('module_installations')->whereIn('key', ['merchandise', 'inventory', 'pharmacy'])->pluck('version', 'key')->all()];
    $chunks = array_fill(0, $options['workers'], []);
    foreach ($points as $i => $point) {
        $chunks[$i % $options['workers']][] = $point;
    }
    $startAt = microtime(true) + 2;
    $measureStarted = hrtime(true);
    foreach ($chunks as $chunk) {
        $process = new Process([PHP_BINARY, __FILE__, '--allow-isolated-mysql', '--worker', '--points='.$options['points'], '--workers='.$options['workers'], '--sales-per-point='.$options['sales-per-point']], base_path(), ['PHARMACY_LOAD_SCRATCH_DB' => $name, 'PHARMACY_LOAD_NONCE' => $nonce, 'PHARMACY_LOAD_INPUT' => json_encode(['points' => $chunk, 'start_at' => $startAt], JSON_THROW_ON_ERROR)]);
        $process->setTimeout(max(120, count($chunk) * $options['sales-per-point'] * 20));
        $process->start();
        $processes[] = $process;
    }
    $samples = [];
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        $check($process->isSuccessful(), 'Worker failed (see sanitized worker error): '.substr($process->getErrorOutput(), 0, 500));
        $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $check(($result['status'] ?? null) === 'ok', 'Worker did not complete.');
        $results[] = $result;
        foreach ($result['samples_ms'] as $stage => $values) {
            $samples[$stage] = array_merge($samples[$stage] ?? [], $values);
        }
    }
    $elapsed = (hrtime(true) - $measureStarted) / 1e9;
    $completed = array_sum(array_column($results, 'completed'));
    $active = max(array_column($results, 'finished_at')) - min(array_column($results, 'started_at'));
    $expected = $options['points'] * $options['sales-per-point'];
    $check($completed === $expected, 'Completed sale count differs from workload.');
    $check(DB::table('ph_sales')->where('status', 'paid')->count() === $expected && DB::table('ph_sales')->count() === $expected, 'Sale count/status mismatch.');
    $check(DB::table('ph_tenders')->where('status', 'paid')->where('method', 'cash')->count() === $expected && DB::table('ph_tenders')->count() === $expected, 'Cash tender count/status mismatch.');
    $check((int) DB::table('ph_sales')->sum('total_minor') === $expected * 1000 && (int) DB::table('ph_tenders')->sum('amount_minor') === $expected * 1000, 'Money totals do not reconcile.');
    $check(DB::table('ph_dispensings')->where('status', 'handed_over')->count() === $expected && DB::table('ph_dispensings')->count() === $expected, 'Custody lifecycle incomplete.');
    $check(DB::table('ph_custody_events')->where('action', 'handed_over')->count() === $expected, 'Handover evidence count mismatch.');
    $check(DB::table('inv_reservations')->where('status', 'committed')->count() === $expected && DB::table('inv_reservations')->where('status', 'active')->count() === 0, 'Reservations were lost or leaked.');
    foreach ($points as $point) {
        $pharmacy = DB::table('ph_pharmacies')->where('public_id', $point['pharmacy_id'])->first();
        $check(DB::table('ph_sales')->where('pharmacy_id', $pharmacy->id)->count() === $options['sales-per-point'], 'Per-point sale count mismatch.');
        $check((string) DB::table('inv_stock_balances')->where('warehouse_id', $point['warehouse_id'])->sum('quantity_on_hand') === '5.000000', 'Per-point stock mismatch.');
        $check((string) DB::table('inv_stock_balances')->where('warehouse_id', $point['warehouse_id'])->sum('quantity_reserved') === '0.000000', 'Per-point reserved stock leaked.');
        $check(DB::table('ph_dispensing_allocations as a')->join('ph_dispensing_lines as l', 'l.id', '=', 'a.dispensing_line_id')->join('ph_dispensings as d', 'd.id', '=', 'l.dispensing_id')->where('d.pharmacy_id', $pharmacy->id)->where('a.batch_id', '!=', $point['batch_id'])->count() === 0, 'Exact lot selection drifted.');
        $closed = $command(Admin::query()->findOrFail($point['actor_id']), 'shift.close', ['shift_id' => $point['shift_id'], 'expected_version' => 1, 'closing_minor' => (string) ($options['sales-per-point'] * 1000)]);
        $check($closed['status'] === 'closed' && $closed['difference_minor'] === 0 && $closed['expected_minor'] === $options['sales-per-point'] * 1000, 'Shift money verification failed.');
    }
    $report['status'] = 'passed';
    $report['measurement'] = ['elapsed_seconds_including_worker_boot_and_barrier' => round($elapsed, 6), 'active_service_window_seconds' => round($active, 6), 'completed_sales' => $completed, 'sales_per_second_including_worker_boot_and_barrier' => round($completed / $elapsed, 3), 'sales_per_second_active_window' => round($completed / max(.000001, $active), 3), 'latency' => array_map($percentiles, $samples), 'worker_peak_memory_bytes' => array_column($results, 'peak_memory_bytes')];
    $report['correctness'] = ['assertions' => $assertions, 'expected_sales' => $expected, 'actual_sales' => DB::table('ph_sales')->count(), 'total_minor' => $expected * 1000, 'remaining_units_per_point' => 5, 'reserved_units' => 0, 'all_exact_lots' => true, 'all_handed_over' => true, 'all_shifts_reconciled' => true];
    $report['limitations'] = ['Service calls only; no HTTP, browser, WAN, human think time, provider integrations, or queue consumer throughput.', 'One independent warehouse/actor/counter/shift per point; no competing tills at the same warehouse or same actor. Same-warehouse hotspots require a separate workload.', 'One shared SKU across all points can expose existing cross-warehouse item locks. Existing production locks are unchanged.', 'Synthetic one-line OTC sales with ample stock; no Rx/partial fills, returns/refunds, holds, expiry/recall/cold chain or mixed baskets.', 'Latency uses nearest-rank p50/p95 and includes five service commands from create through handover; no warmup. Small samples do not estimate production tail latency.', 'Setup/migrations/receipts and post-run shift close correctness checks are excluded from measured sale latency. No claim about 100-point production capacity.'];
} catch (Throwable $e) {
    if ($isWorker) {
        fwrite(STDERR, json_encode(['error' => get_class($e), 'message' => substr($e->getMessage(), 0, 600)], JSON_THROW_ON_ERROR)."\n");
        exit(1);
    }
    $report['error'] = ['type' => get_class($e), 'message' => substr($e->getMessage(), 0, 1200)];
} finally {
    if ($created) {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        if (isset($app)) {
            DB::disconnect();
        }
        $control->exec('DROP DATABASE `'.$name.'`');
        $report['cleanup_verified'] = $control->query('SHOW DATABASES LIKE '.$control->quote($name))->fetchColumn() === false;
        if (! $report['cleanup_verified']) {
            $report['status'] = 'failed';
            $report['error'] = ['message' => 'Scratch cleanup was not verified.'];
        }
    }
}
if (! $isWorker) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}
exit(($report['status'] ?? '') === 'passed' ? 0 : 1);
