<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Role;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Commands\ManufacturingCommandService;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Manufacturing\Services\ManufacturingService;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;
use Modules\Manufacturing\Services\Setup\ManufacturingSetupService;
use Modules\Merchandise\Services\MerchandiseService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (! in_array('--allow-isolated-mysql', $argv, true)) {
    fwrite(STDERR, "Requires --allow-isolated-mysql. Only a random scratch database is created and removed.\n");
    exit(2);
}
$environment = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
$setting = fn ($key, $default = null) => getenv($key) !== false ? getenv($key) : ($environment[$key] ?? $default);
if ($setting('DB_CONNECTION', 'mysql') !== 'mysql' || ! in_array($setting('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true) || $setting('DB_URL')) {
    throw new RuntimeException('Only an unrouted local MySQL connection is allowed.');
}
$worker = in_array('--worker', $argv, true);
$name = $worker ? (string) getenv('MFG_GATE_DATABASE') : 'aio_manufacturing_gate_'.bin2hex(random_bytes(8));
if (! preg_match('/^aio_manufacturing_gate_[a-f0-9]{16}$/D', $name) || $name === ($environment['DB_DATABASE'] ?? null)) {
    throw new RuntimeException('Scratch name rejected.');
}
$admin = new PDO('mysql:host='.$setting('DB_HOST', '127.0.0.1').';port='.$setting('DB_PORT', 3306).';charset=utf8mb4', $setting('DB_USERNAME'), $setting('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false;
$failed = false;
$assertions = 0;
$signals = [];
$processes = [];
$check = function (bool $ok, string $message) use (&$assertions): void {
    if (! $ok) {
        throw new RuntimeException($message);
    } $assertions++;
};
$waitSignal = function (string $path): void {
    $until = microtime(true) + 20;
    do {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return;
        } usleep(20000);
    } while (microtime(true) < $until);
    throw new RuntimeException('Scratch process synchronization timed out.');
};
try {
    if (! $worker) {
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created = true;
    }
    foreach (['APP_ENV' => 'local', 'DB_DATABASE' => $name, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/mfg-gate-unused-'.bin2hex(random_bytes(8)).'.php', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (DB::connection()->getDatabaseName() !== $name) {
        throw new RuntimeException('Scratch database was not selected before bootstrap.');
    }
    if ($worker) {
        $input = json_decode((string) getenv('MFG_GATE_INPUT'), true, flags: JSON_THROW_ON_ERROR);
        $actor = Admin::query()->findOrFail($input['actor']);
        $operation = $input['operation'];
        if (isset($input['start_signal'])) {
            echo "READY\n";
            flush();
            $waitSignal($input['start_signal']);
        }
        try {
            if ($operation === 'cut') {
                $result = app(ManufacturingService::class)->command($actor, 'cut.create', ['factory_id' => $input['factory'], 'data' => $input['data']], $input['key']);
            } elseif ($operation === 'disable') {
                app(ModuleManager::class)->disable('manufacturing');
                $result = ['status' => 'disabled'];
            } elseif ($operation === 'revoke') {
                DB::transaction(fn () => DB::table('mfg_memberships')->where('admin_id', $input['target'])->update(['status' => 'inactive', 'updated_at' => now()]));
                $result = ['status' => 'revoked'];
            } elseif ($operation === 'pause') {
                $result = app(ManufacturingRuntimeState::class)->setPaused($actor, true, 'Scratch maintenance');
            } else {
                $result = app(ManufacturingCommandService::class)->run($actor, 'gate.once', ['code' => $input['code']], $input['key'], function (Admin $live) use ($input): array {
                    return ['factory' => app(ManufacturingAccessService::class)->factory($live, $input['factory'], 'manufacturing.execute', true), 'permission' => 'manufacturing.execute'];
                }, function (Admin $live, array $scope) use ($input, $waitSignal): array {
                    if (isset($input['release_signal'])) {
                        echo "LOCKED\n";
                        flush();
                        $waitSignal($input['release_signal']);
                    }
                    $row = D::insert('mfg_styles', ['company_id' => $scope['factory']->company_id, 'code' => $input['code'], 'name' => 'Synthetic gate']);

                    return ['public_id' => $row->public_id, 'version' => 1, 'status' => 'active'];
                });
            }
            echo json_encode(['status' => 'committed', 'resource' => $result], JSON_THROW_ON_ERROR)."\n";
        } catch (HttpExceptionInterface $e) {
            echo json_encode(['status' => 'rejected', 'http_status' => $e->getStatusCode()], JSON_THROW_ON_ERROR)."\n";
        }
        exit(0);
    }
    $check(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Core migrate failed.');
    foreach (['merchandise', 'inventory', 'manufacturing'] as $module) {
        app(ModuleManager::class)->install($module);
        app(ModuleManager::class)->enable($module);
    }
    $owner = Admin::query()->create(['name' => 'Synthetic production gate', 'username' => 'mfg-gate-owner', 'email' => 'mfg-gate@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'status' => 'active', 'auth_version' => 1, 'must_change_password' => false])->fresh();
    $setup = app(ManufacturingSetupService::class);
    $companyResource = DB::transaction(fn () => $setup->dispatch($owner, 'company.create', [], ['code' => 'GATE', 'name' => 'Synthetic Manufacturing']));
    $company = DB::table('mfg_companies')->where('public_id', $companyResource['public_id'])->first();
    $factoryResource = DB::transaction(fn () => $setup->dispatch($owner, 'factory.create', ['company' => $company], ['code' => 'ONE', 'name' => 'Factory One']));
    $factory = DB::table('mfg_factories')->where('public_id', $factoryResource['public_id'])->first();
    $workshop = D::insert('mfg_workshops', ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'SEW', 'name' => 'Sewing', 'status' => 'active']);
    $newSignal = function () use (&$signals): string {
        $path = tempnam(sys_get_temp_dir(), 'aio_mfg_gate_');
        unlink($path);
        $signals[] = $path;

        return $path;
    };
    $start = function (array $input) use ($name, &$processes): Process {
        $p = new Process([PHP_BINARY, __FILE__, '--allow-isolated-mysql', '--worker'], base_path(), ['MFG_GATE_DATABASE' => $name, 'MFG_GATE_INPUT' => json_encode($input, JSON_THROW_ON_ERROR)]);
        $p->setTimeout(40);
        $p->start();
        $processes[] = $p;

        return $p;
    };
    $waitPhase = function (Process $p, string $phase): void {
        $until = microtime(true) + 20;
        do {
            if (str_contains($p->getOutput(), $phase)) {
                return;
            } if (! $p->isRunning()) {
                throw new RuntimeException('Worker exited before phase: '.$p->getErrorOutput().$p->getOutput());
            } usleep(20000);
        } while (microtime(true) < $until);
        throw new RuntimeException('Worker phase timed out.');
    };
    $finish = function (Process $p): array {
        $p->wait();
        if (! $p->isSuccessful()) {
            throw new RuntimeException('Scratch worker failed: '.$p->getErrorOutput().$p->getOutput());
        }
        $lines = array_values(array_filter(explode("\n", trim($p->getOutput())), fn ($line) => str_starts_with($line, '{')));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    };
    $base = ['actor' => $owner->id, 'factory' => $factory->public_id];
    $go = $newSignal();
    $one = $start($base + ['operation' => 'once', 'code' => 'ONCE', 'key' => 'manufacturing-same-key-0001', 'start_signal' => $go]);
    $two = $start($base + ['operation' => 'once', 'code' => 'ONCE', 'key' => 'manufacturing-same-key-0001', 'start_signal' => $go]);
    $waitPhase($one, 'READY');
    $waitPhase($two, 'READY');
    touch($go);
    $r1 = $finish($one);
    $r2 = $finish($two);
    $check($r1['status'] === 'committed' && $r2['status'] === 'committed', 'Same key rejected unexpectedly.');
    $check($r1['resource']['public_id'] === $r2['resource']['public_id'] && DB::table('mfg_styles')->where('code', 'ONCE')->count() === 1, 'Same command had duplicate effects.');
    echo "PASS independent-process command deduplication\n";

    $catalog = (int) D::json($company->settings)['catalog_id'];
    $raw = app(MerchandiseService::class)->saveSku($catalog, ['sku' => 'RAW', 'name' => 'Fabric', 'base_unit' => 'm', 'tracking_mode' => 'batch']);
    $fg = app(MerchandiseService::class)->saveSku($catalog, ['sku' => 'FG', 'name' => 'Garment', 'base_unit' => 'pcs', 'tracking_mode' => 'batch']);
    $roll = app(InventoryProductionService::class)->createRoll(['warehouse_id' => $factory->warehouse_id, 'sku_id' => $raw['id'], 'quantity' => '1', 'unit_cost' => '100', 'code' => 'LAST-METER', 'disposition' => 'saleable'], 'gate-last-meter-receipt', $owner->id);
    $bom = [['sku_id' => $raw['id'], 'quantity' => '1.000000', 'uom' => 'm', 'allowance_percent' => '0.000000']];
    $revision = D::insert('mfg_style_revisions', ['company_id' => $company->id, 'style_id' => DB::table('mfg_styles')->where('code', 'ONCE')->value('id'), 'revision' => 1, 'status' => 'approved', 'bom' => D::encode($bom), 'routing' => D::encode([['code' => 'SEW']])]);
    $cutInputs = [];
    foreach (['CUT-A', 'CUT-B'] as $code) {
        $order = D::insert('mfg_orders', ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'ORDER-'.$code, 'style_revision_id' => $revision->id, 'status' => 'released', 'released_snapshot' => D::encode(['bom' => $bom, 'routing' => [['code' => 'SEW']]])]);
        D::insert('mfg_order_lines', ['company_id' => $company->id, 'factory_id' => $factory->id, 'order_id' => $order->id, 'sku_id' => $fg['id'], 'variant' => D::encode(['color' => 'BLUE', 'size' => 'M']), 'quantity' => '1']);
        D::insert('mfg_material_requirements', ['company_id' => $company->id, 'factory_id' => $factory->id, 'order_id' => $order->id, 'sku_id' => $raw['id'], 'base_unit' => 'm', 'required_quantity' => '1']);
        $cutInputs[] = $base + ['operation' => 'cut', 'key' => 'manufacturing-stock-'.$code, 'data' => ['order_id' => $order->public_id, 'code' => $code, 'color' => 'BLUE', 'size' => 'M', 'quantity' => '1', 'workshop_id' => $workshop->public_id, 'material_id' => $roll['public_id'], 'consumed_quantity' => '1']];
    }
    $go = $newSignal();
    $cutInputs[0]['start_signal'] = $go;
    $cutInputs[1]['start_signal'] = $go;
    $one = $start($cutInputs[0]);
    $two = $start($cutInputs[1]);
    $waitPhase($one, 'READY');
    $waitPhase($two, 'READY');
    touch($go);
    $outcomes = [$finish($one), $finish($two)];
    $check(count(array_filter($outcomes, fn ($r) => $r['status'] === 'committed')) === 1, 'Last meter generated two cutting bundles.');
    $check(DB::table('mfg_bundles')->count() === 1 && DB::table('inv_production_issues')->count() === 1, 'Failed cutting leaked partial writes.');
    $check(! DB::table('inv_stock_balances')->where('quantity_on_hand', '<', 0)->orWhere('quantity_reserved', '<', 0)->exists(), 'Stock became negative.');
    $check(app(InventoryV2Service::class)->availability((int) $factory->warehouse_id, app(InventoryV2Service::class)->ensureItem($raw['id'])) === '0.000000', 'Cutting did not consume the last meter.');
    echo "PASS independent orders compete for last material without partial side effects\n";

    $operator = Admin::query()->create(['name' => 'Synthetic operator', 'username' => 'mfg-gate-worker', 'email' => 'worker@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'status' => 'active', 'auth_version' => 1, 'must_change_password' => false])->fresh();
    AdminRoleAssignment::query()->create(['admin_id' => $operator->id, 'role_id' => Role::query()->where('key', 'manufacturing.operator')->value('id'), 'scope_type' => 'global', 'scope_value' => null]);
    D::insert('mfg_memberships', ['company_id' => $company->id, 'factory_id' => $factory->id, 'admin_id' => $operator->id, 'role' => 'operator', 'status' => 'active']);
    $release = $newSignal();
    $hold = $start(['actor' => $operator->id, 'factory' => $factory->public_id, 'operation' => 'once', 'code' => 'BEFORE-REVOKE', 'key' => 'membership-held-command', 'release_signal' => $release]);
    $waitPhase($hold, 'LOCKED');
    $revoke = $start($base + ['operation' => 'revoke', 'target' => $operator->id]);
    usleep(300000);
    $check($revoke->isRunning(), 'Membership revoke did not wait for the authorized mutation shared lock.');
    touch($release);
    $check($finish($hold)['status'] === 'committed' && $finish($revoke)['status'] === 'committed', 'Revoke/command did not complete.');
    $retry = $finish($start(['actor' => $operator->id, 'factory' => $factory->public_id, 'operation' => 'once', 'code' => 'BEFORE-REVOKE', 'key' => 'membership-held-command']));
    $check($retry['status'] === 'rejected' && $retry['http_status'] === 403, 'Replay ignored the revoked native membership.');
    echo "PASS native revoke waits for mutation then rejects command replay\n";

    $release = $newSignal();
    $hold = $start($base + ['operation' => 'once', 'code' => 'BEFORE-PAUSE', 'key' => 'pause-held-command', 'release_signal' => $release]);
    $waitPhase($hold, 'LOCKED');
    $pause = $start($base + ['operation' => 'pause']);
    usleep(300000);
    $check($pause->isRunning() && ! app(ManufacturingRuntimeState::class)->state()['paused'], 'Pause did not wait for active command.');
    touch($release);
    $finish($hold);
    $finish($pause);
    $check(app(ManufacturingAccessService::class)->bootstrap($owner)['runtime']['paused'], 'Paused native read was unavailable.');
    $blocked = $finish($start($base + ['operation' => 'once', 'code' => 'DURING-PAUSE', 'key' => 'during-pause-command']));
    $check($blocked['status'] === 'rejected' && $blocked['http_status'] === 423 && ! DB::table('mfg_styles')->where('code', 'DURING-PAUSE')->exists(), 'Paused command was written.');
    app(ManufacturingRuntimeState::class)->setPaused($owner, false);
    echo "PASS maintenance pause drains writers, preserves reads and blocks new mutations\n";

    $release = $newSignal();
    $hold = $start($base + ['operation' => 'once', 'code' => 'BEFORE-DRAIN', 'key' => 'drain-held-command', 'release_signal' => $release]);
    $waitPhase($hold, 'LOCKED');
    $drain = $start($base + ['operation' => 'disable']);
    $until = microtime(true) + 10;
    do {
        $active = DB::table('module_lifecycle_operations')->where('module_key', 'manufacturing')->where('active_slot', 'active')->exists();
        if ($active) {
            break;
        } usleep(20000);
    } while (microtime(true) < $until);
    $check($active && $drain->isRunning(), 'Lifecycle drain completed while a mutation still held its shared runtime lock.');
    $check(DB::table('module_installations')->where('key', 'manufacturing')->value('status') === 'enabled', 'Disable committed before draining its writer.');
    touch($release);
    $finish($hold);
    $finish($drain);
    $check(DB::table('module_installations')->where('key', 'manufacturing')->value('status') === 'disabled' && DB::table('mfg_bundles')->count() === 1, 'Drain lost WIP or failed to disable.');
    app(ModuleManager::class)->enable('manufacturing');
    DB::table('module_installations')->where('key', 'manufacturing')->update(['version' => '0.0.1']);
    app(ModuleManager::class)->upgrade('manufacturing');
    $check(DB::table('mfg_bundles')->count() === 1 && app(ManufacturingRuntimeState::class)->state()['status'] === 'active', 'Upgrade required empty WIP or failed to restore runtime.');
    echo "PASS lifecycle drain waits for command; disable/enable/upgrade preserve nonzero WIP\n";
    echo "OK 5 gates / {$assertions} assertions\n";
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL '.get_class($error).': '.$error->getMessage()."\n");
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(0.2);
        }
    }
    if (isset($app)) {
        DB::disconnect();
    }
    foreach ($signals as $signal) {
        if (is_file($signal)) {
            unlink($signal);
        }
    }
    if ($created) {
        $admin->exec('DROP DATABASE `'.$name.'`');
        if ($admin->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '.$admin->quote($name))->fetchColumn() !== false) {
            throw new RuntimeException('Scratch cleanup verification failed.');
        }
        echo "Scratch database removed and verified\n";
    }
}
if ($failed) {
    exit(1);
}
