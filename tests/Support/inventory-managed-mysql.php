<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Contracts\ManagedStockPolicy;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (! in_array('--allow-isolated-mysql', $argv, true)) {
    fwrite(STDERR, "Requires --allow-isolated-mysql; only a guarded random scratch database is created/dropped.\n");
    exit(2);
}
class InventoryManagedGatePolicy implements ManagedStockPolicy
{
    public function authorize(int $actorId, object $warehouse, string $operation, array $context): void
    {
        abort_unless($context['action'] === 'synthetic-gate', 403);
    }
}
$environment = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
$setting = fn (string $key, mixed $default = null) => getenv($key) !== false ? getenv($key) : ($environment[$key] ?? $default);
if ($setting('DB_CONNECTION', 'mysql') !== 'mysql' || ! in_array($setting('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true) || $setting('DB_URL')) {
    throw new RuntimeException('Only unrouted local MySQL is supported.');
}
$worker = in_array('--worker', $argv, true);
$name = $worker ? (string) getenv('INVENTORY_MANAGED_GATE_DB') : 'aio_inventory_managed_'.bin2hex(random_bytes(8));
if (! preg_match('/^aio_inventory_managed_[a-f0-9]{16}$/D', $name) || $name === ($environment['DB_DATABASE'] ?? null)) {
    throw new RuntimeException('Scratch database name rejected.');
}
$admin = new PDO('mysql:host='.$setting('DB_HOST', '127.0.0.1').';port='.$setting('DB_PORT', 3306).';charset=utf8mb4', $setting('DB_USERNAME'), $setting('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false;
$assertions = 0;
$check = function (bool $ok, string $message) use (&$assertions): void {
    if (! $ok) {
        throw new RuntimeException($message);
    } $assertions++;
};
try {
    if (! $worker) {
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created = true;
    }
    foreach (['APP_ENV' => 'local', 'DB_DATABASE' => $name, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/managed-stock-unused-'.bin2hex(random_bytes(8)).'.php', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $check(DB::connection()->getDatabaseName() === $name, 'Scratch connection not selected before bootstrap.');
    config(['inventory.managed_policies.pharmacy' => InventoryManagedGatePolicy::class]);
    $stock = app(InventoryV2Service::class);
    $managed = app(InventoryManagedStockService::class);
    if ($worker) {
        $input = json_decode((string) getenv('INVENTORY_MANAGED_GATE_INPUT'), true, flags: JSON_THROW_ON_ERROR);
        $actor = (int) getenv('INVENTORY_MANAGED_GATE_ACTOR');
        try {
            $result = $managed->withinOwner('pharmacy', 'synthetic-store', $actor, 'synthetic-gate', fn () => match ($input['operation']) {
                'reserve' => $stock->reserve($input['warehouse'], $input['item'], '1', 'synthetic-dispense', $input['key'], $actor, $input['batch']),
                'commit' => $stock->commitReservation($input['reservation'], $input['key'], $actor),
            });
            echo json_encode(['status' => 'committed', 'public_id' => $result['public_id']], JSON_THROW_ON_ERROR)."\n";
        } catch (HttpExceptionInterface $e) {
            echo json_encode(['status' => 'rejected', 'http_status' => $e->getStatusCode()], JSON_THROW_ON_ERROR)."\n";
        }
        exit(0);
    }
    $check(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Core migrations failed.');
    foreach (['merchandise', 'inventory'] as $module) {
        app(ModuleManager::class)->install($module);
        app(ModuleManager::class)->enable($module);
    }
    DB::table('module_installations')->insert(['key' => 'pharmacy', 'name' => 'Synthetic policy owner', 'version' => '0.1.0-dev.1', 'status' => 'enabled', 'website_types' => '[]', 'dependencies' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    $actor = Admin::query()->create(['name' => 'Synthetic managed stock gate', 'username' => 'synthetic-managed-stock', 'email' => 'managed-stock@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'must_change_password' => false]);
    $owner = fn (callable $callback) => $managed->withinOwner('pharmacy', 'synthetic-store', $actor->id, 'synthetic-gate', $callback);
    $merch = app(MerchandiseService::class);
    $catalog = $merch->createCatalog(['code' => 'PHGATE', 'name' => 'Synthetic managed stock']);
    $sku = $merch->saveSku($catalog['id'], ['sku' => 'MED', 'name' => 'Synthetic medicine', 'base_unit' => 'tablet', 'tracking_mode' => 'batch', 'requires_expiry' => true]);
    $item = $stock->ensureItem($sku['id']);
    $warehouse = $stock->createWarehouse(['code' => 'PHGATE', 'name' => 'Synthetic pharmacy', 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'synthetic-owner', 'timezone' => 'Asia/Ho_Chi_Minh'])['id'];
    $owner(fn () => $managed->tagWarehouse($warehouse, 'pharmacy', 'synthetic-store', 'tag-managed-gate', $actor->id));
    $receipt = $owner(fn () => $stock->receive(['warehouse_id' => $warehouse, 'item_id' => $item, 'quantity' => '1', 'unit_cost' => '27', 'batch_code' => 'LOT', 'expires_at' => '2030-01-01'], 'managed-receipt', $actor->id));
    $check($stock->availability($warehouse, $item) === '0.000000', 'Managed receipt did not start quarantined.');
    $owner(fn () => $managed->changeDisposition(['warehouse_id' => $warehouse, 'item_id' => $item, 'batch_id' => $receipt['batch_id'], 'disposition' => 'saleable', 'reason' => 'Synthetic inspection'], 'managed-inspection', $actor->id));
    echo "PASS fresh managed lifecycle and quarantine disposition\n";
    $pair = function (array $inputs) use ($name, $actor): array {
        $processes = [];
        foreach ($inputs as $input) {
            $process = new Process([PHP_BINARY, __FILE__, '--allow-isolated-mysql', '--worker'], base_path(), ['INVENTORY_MANAGED_GATE_DB' => $name, 'INVENTORY_MANAGED_GATE_ACTOR' => (string) $actor->id, 'INVENTORY_MANAGED_GATE_INPUT' => json_encode($input, JSON_THROW_ON_ERROR)]);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }

        return array_map(function (Process $process): array {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Managed worker failed: '.$process->getErrorOutput());
            }

            return json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    };
    $input = ['operation' => 'reserve', 'warehouse' => $warehouse, 'item' => $item, 'batch' => $receipt['batch_id']];
    $results = $pair([$input + ['key' => 'last-managed-one'], $input + ['key' => 'last-managed-two']]);
    $committed = array_values(array_filter($results, fn ($r) => $r['status'] === 'committed'));
    $check(count($committed) === 1, 'Managed last stock committed twice.');
    $owner(fn () => $stock->releaseReservation($committed[0]['public_id'], 'release-last-managed', $actor->id));
    echo "PASS independent-process exact-lot last stock\n";
    $results = $pair([$input + ['key' => 'same-managed-key'], $input + ['key' => 'same-managed-key']]);
    $check($results[0]['status'] === 'committed' && $results[1]['status'] === 'committed' && $results[0]['public_id'] === $results[1]['public_id'], 'Same key created multiple managed reservations.');
    echo "PASS independent-process same-key replay\n";
    $hold = $owner(fn () => $managed->placeHold(['warehouse_id' => $warehouse, 'item_id' => $item, 'batch_id' => $receipt['batch_id'], 'reason_code' => 'recall', 'reason' => 'Synthetic recall'], 'managed-recall', $actor->id));
    $results = $pair([['operation' => 'commit', 'reservation' => $results[0]['public_id'], 'key' => 'commit-after-recall']]);
    $check($results[0]['status'] === 'rejected' && $results[0]['http_status'] === 409, 'New recall did not block reserved lot commit.');
    $check((string) DB::table('inv_stock_balances')->where('warehouse_id', $warehouse)->sum('quantity_on_hand') === '1.000000', 'Rejected commit modified stock.');
    echo "PASS new hold invalidates reserved stock before commit\n";
    DB::table('module_installations')->where('key', 'pharmacy')->update(['status' => 'disabled']);
    try {
        $owner(fn () => $managed->releaseHold($hold['public_id'], 1, 'Synthetic release', 'owner-disabled-release', $actor->id));
        throw new RuntimeException('Disabled owner opened managed stock.');
    } catch (HttpExceptionInterface $e) {
        $check($e->getStatusCode() === 503, 'Disabled owner failed with wrong status.');
    }
    echo "PASS durable owner tag remains closed while disabled\n";
    echo 'OK 5 gates / '.$assertions." assertions\n";
} finally {
    if ($created) {
        if (isset($app)) {
            DB::disconnect();
        }
        $admin->exec('DROP DATABASE `'.$name.'`');
        $check($admin->query('SHOW DATABASES LIKE '.$admin->quote($name))->fetchColumn() === false, 'Scratch database cleanup not verified.');
        echo "Scratch database removed and verified\n";
    }
}
