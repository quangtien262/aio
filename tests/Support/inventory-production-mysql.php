<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Inventory\Services\InventoryValuationService;
use Modules\Merchandise\Services\MerchandiseService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (! in_array('--allow-isolated-mysql', $argv, true)) {
    fwrite(STDERR, "Requires --allow-isolated-mysql; creates and removes only a random local scratch database.\n");
    exit(2);
}
// Read connection settings without booting against the business database.
$environment = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
$setting = fn (string $key, mixed $default = null) => getenv($key) !== false ? getenv($key) : ($environment[$key] ?? $default);
if ($setting('DB_CONNECTION', 'mysql') !== 'mysql' || ! in_array($setting('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true) || $setting('DB_URL')) {
    throw new RuntimeException('Only an unrouted local MySQL source connection is supported.');
}
$worker = in_array('--worker', $argv, true);
$name = $worker ? (string) getenv('INVENTORY_PRODUCTION_GATE_DB') : 'aio_inventory_production_'.bin2hex(random_bytes(8));
if (! preg_match('/^aio_inventory_production_[a-f0-9]{16}$/D', $name) || $name === ($environment['DB_DATABASE'] ?? null)) {
    throw new RuntimeException('Scratch database name rejected.');
}
$admin = new PDO('mysql:host='.$setting('DB_HOST', '127.0.0.1').';port='.$setting('DB_PORT', 3306).';charset=utf8mb4', $setting('DB_USERNAME'), $setting('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false;
$failure = false;
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
    foreach (['APP_ENV' => 'local', 'DB_DATABASE' => $name, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/inventory-production-unused-'.bin2hex(random_bytes(8)).'.php', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connectionName = DB::getDefaultConnection();
    if (DB::connection()->getDatabaseName() !== $name) {
        throw new RuntimeException('Scratch connection was not selected before bootstrap.');
    }
    $stock = app(InventoryV2Service::class);
    $production = app(InventoryProductionService::class);
    if ($worker) {
        $input = json_decode((string) getenv('INVENTORY_PRODUCTION_GATE_INPUT'), true, flags: JSON_THROW_ON_ERROR);
        try {
            $result = $production->reserve($input, (string) getenv('INVENTORY_PRODUCTION_GATE_KEY'), (int) getenv('INVENTORY_PRODUCTION_GATE_ACTOR'));
            echo json_encode(['status' => 'committed', 'public_id' => $result['public_id']], JSON_THROW_ON_ERROR)."\n";
        } catch (HttpExceptionInterface $e) {
            echo json_encode(['status' => 'rejected', 'http_status' => $e->getStatusCode()], JSON_THROW_ON_ERROR)."\n";
        }
        exit(0);
    }
    $check(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Core migrate failed.');
    foreach (['merchandise', 'inventory'] as $module) {
        app(ModuleManager::class)->install($module);
        app(ModuleManager::class)->enable($module);
    }
    $check(! Schema::hasTable('cms_posts') && ! Schema::hasTable('catalog_products'), 'Fresh production stock installed optional schemas.');
    $check(Schema::hasTable('inv_production_issue_layers') && Schema::hasTable('merch_variant_sets'), 'Lifecycle omitted new schema.');
    $actor = Admin::query()->create(['name' => 'Synthetic stock gate', 'username' => 'synthetic-stock-gate', 'email' => 'stock-gate@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'must_change_password' => false]);
    $merch = app(MerchandiseService::class);
    $catalog = $merch->createCatalog(['code' => 'GATE', 'name' => 'Synthetic gate']);
    $sku = $merch->saveSku($catalog['id'], ['sku' => 'RAW', 'name' => 'Fabric', 'base_unit' => 'm', 'quantity_scale' => 6, 'tracking_mode' => 'batch']);
    $fg = $merch->saveSku($catalog['id'], ['sku' => 'FG', 'name' => 'Finished', 'base_unit' => 'pcs', 'tracking_mode' => 'batch']);
    $rawItem = $stock->ensureItem($sku['id']);
    $fgItem = $stock->ensureItem($fg['id']);
    $warehouse = $stock->createWarehouse(['code' => 'GATE', 'name' => 'Factory', 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'synthetic-company']);
    $production->enableWarehouse(['warehouse_id' => $warehouse['id']], 'enable-gate-warehouse', $actor->id);
    $roll = $production->createRoll(['warehouse_id' => $warehouse['id'], 'item_id' => $rawItem, 'quantity' => '100', 'unit_cost' => '50000', 'code' => 'GATE-ROLL', 'secondary_unit' => 'kg', 'secondary_quantity' => '50'], 'receive-gate-roll', $actor->id);
    $production->inspectRoll(['roll_id' => $roll['public_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Synthetic QC'], 'qc-gate-roll', $actor->id);
    echo "PASS fresh production lifecycle without optional modules\n";

    $runPair = function (array $inputs, array $keys) use ($name, $actor): array {
        $processes = [];
        foreach ($inputs as $i => $input) {
            $p = new Process([PHP_BINARY, __FILE__, '--allow-isolated-mysql', '--worker'], base_path(), ['INVENTORY_PRODUCTION_GATE_DB' => $name, 'INVENTORY_PRODUCTION_GATE_ACTOR' => (string) $actor->id, 'INVENTORY_PRODUCTION_GATE_INPUT' => json_encode($input, JSON_THROW_ON_ERROR), 'INVENTORY_PRODUCTION_GATE_KEY' => $keys[$i]]);
            $p->setTimeout(30);
            $p->start();
            $processes[] = $p;
        }
        $results = [];
        foreach ($processes as $p) {
            $p->wait();
            if (! $p->isSuccessful()) {
                throw new RuntimeException('Scratch worker failed: '.$p->getErrorOutput());
            }
            $results[] = json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    };
    $input = ['warehouse_id' => $warehouse['id'], 'item_id' => $rawItem, 'quantity' => '100', 'order_ref' => 'WO-COMPETE'];
    $results = $runPair([$input, array_replace($input, ['order_ref' => 'WO-COMPETE-OTHER'])], ['last-stock-one', 'last-stock-two']);
    $check(count(array_filter($results, fn ($r) => $r['status'] === 'committed')) === 1, 'Last stock committed twice.');
    $hold = array_values(array_filter($results, fn ($r) => $r['status'] === 'committed'))[0];
    $check($stock->availability($warehouse['id'], $rawItem) === '0.000000', 'Reservation did not consume availability.');
    $production->release(['reservation_id' => $hold['public_id'], 'expected_version' => 1, 'quantity' => '100'], 'release-competition', $actor->id);
    echo "PASS independent-process last-stock competition\n";

    $input['order_ref'] = 'WO-SAME-KEY';
    $results = $runPair([$input, $input], ['same-stock-key', 'same-stock-key']);
    $check($results[0]['status'] === 'committed' && $results[1]['status'] === 'committed' && $results[0]['public_id'] === $results[1]['public_id'], 'Same key did not return one hold.');
    echo "PASS independent-process same-key retry returns one production hold\n";
    $issue = $production->issue(['reservation_id' => $results[0]['public_id'], 'expected_version' => 1, 'quantity' => '80'], 'issue-gate-eighty', $actor->id);
    $check($issue['total_cost'] === '4000000.000000', 'Issue valuation differs from FIFO source.');
    $production->returnMaterial(['issue_id' => $issue['public_id'], 'expected_version' => 1, 'quantity' => '5'], 'return-gate-five', $actor->id);
    $first = $production->postOutput(['warehouse_id' => $warehouse['id'], 'order_ref' => 'WO-SAME-KEY', 'materials' => [['issue_id' => $issue['public_id'], 'quantity' => '25']], 'outputs' => [['item_id' => $fgItem, 'quantity' => '10']], 'conversion_cost' => '100000'], 'output-gate-first', $actor->id);
    $second = $production->postOutput(['warehouse_id' => $warehouse['id'], 'order_ref' => 'WO-SAME-KEY', 'materials' => [['issue_id' => $issue['public_id'], 'quantity' => '50']], 'outputs' => [['item_id' => $fgItem, 'quantity' => '20']], 'conversion_cost' => '150000'], 'output-gate-second', $actor->id);
    $check($first['total_cost'] === '1350000.000000' && $second['total_cost'] === '2650000.000000', 'Partial output cost did not conserve 4,000,000.');
    $check((string) DB::table('inv_production_issues')->where('id', $issue['id'])->value('allocated_quantity') === '75.000000', 'Material was not allocated exactly once.');
    $check($stock->availability($warehouse['id'], $fgItem) === '0.000000', 'Uninspected output became available.');
    echo "PASS partial material return and multi-completion valuation\n";
    $outputRoll = $first['outputs'][0]['roll_id'];
    $production->inspectRoll(['roll_id' => $outputRoll, 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Synthetic output QC'], 'qc-gate-output', $actor->id);
    $hold = $production->reserve(['warehouse_id' => $warehouse['id'], 'item_id' => $fgItem, 'roll_id' => $outputRoll, 'quantity' => '3', 'order_ref' => 'shipment:mysql'], 'mysql-shipment-hold', $actor->id);
    $shipment = $production->issue(['reservation_id' => $hold['public_id'], 'expected_version' => 1, 'quantity' => '3'], 'mysql-shipment-issue', $actor->id);
    $check($shipment['total_cost'] === '405000.000000', 'Historical shipment cost differs from original output.');
    $delta = $production->adjustOutputCost(['posting_id' => $first['public_id'], 'amount' => '100000', 'source_id' => 'approved-mysql-late-cost', 'reason' => 'Synthetic approved late conversion'], 'mysql-late-conversion', $actor->id);
    $check($delta['remaining_value'] === '70000.000000' && $delta['realized_value'] === '30000.000000', 'Late value did not split on-hand and issued stock.');
    $hold = $production->reserve(['warehouse_id' => $warehouse['id'], 'item_id' => $fgItem, 'roll_id' => $outputRoll, 'quantity' => '2', 'order_ref' => 'shipment:mysql-next'], 'mysql-shipment-next-hold', $actor->id);
    $next = $production->issue(['reservation_id' => $hold['public_id'], 'expected_version' => 1, 'quantity' => '2'], 'mysql-shipment-next-issue', $actor->id);
    $check($next['total_cost'] === '290000.000000', 'Future issue ignored late valuation.');
    $returned = $production->returnMaterial(['issue_id' => $shipment['public_id'], 'expected_version' => 1, 'quantity' => '1'], 'mysql-return-adjusted-output', $actor->id);
    $check($returned['total_cost'] === '145000.000000', 'Return lost current valuation provenance.');
    $fresh = app(InventoryValuationService::class)->event($delta['id']);
    $check($fresh['remaining_value'] === '60000.000000' && $fresh['realized_value'] === '40000.000000', 'Late cost failed to follow returned physical stock.');
    $check($fresh['included_in_issues_value'] === '20000.000000' && $fresh['realized_variance_value'] === '20000.000000', 'Valuation variance double-counts adjusted future issues.');
    echo "PASS late valuation after shipment, subsequent issue and source-linked return\n";
    $secondRoll = $second['outputs'][0]['roll_id'];
    $production->inspectRoll(['roll_id' => $secondRoll, 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Transfer QC'], 'mysql-transfer-qc', $actor->id);
    $event = $production->adjustOutputCost(['posting_id' => $second['public_id'], 'amount' => '200000', 'source_id' => 'approved-mysql-transfer-cost', 'reason' => 'Late cost before physical split'], 'mysql-transfer-cost-adjust', $actor->id);
    $split = $production->splitRoll(['roll_id' => $secondRoll, 'expected_version' => 2, 'quantity' => '5', 'code' => 'MYSQL-SPLIT'], 'mysql-adjusted-split', $actor->id);
    $target = $stock->createWarehouse(['code' => 'TARGET', 'name' => 'Target factory', 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'synthetic-company']);
    $production->enableWarehouse(['warehouse_id' => $target['id']], 'mysql-enable-target', $actor->id);
    $checker = Admin::query()->create(['name' => 'Synthetic receiver', 'username' => 'synthetic-receiver', 'email' => 'receiver@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'must_change_password' => false]);
    $production->dispatchRoll(['roll_id' => $split['child']['public_id'], 'expected_version' => 1, 'destination_warehouse_id' => $target['id']], 'mysql-adjusted-dispatch', $actor->id);
    $production->receiveRoll(['roll_id' => $split['child']['public_id'], 'expected_version' => 2], 'mysql-adjusted-receive', $checker->id);
    $check(app(InventoryValuationService::class)->warehouse($target['id'])['valuation'] === '712500.000000', 'Physical transfer capitalized late value twice or omitted it.');
    $event = app(InventoryValuationService::class)->event($event['id']);
    $check($event['remaining_value'] === '200000.000000' && $event['realized_value'] === '0.000000' && $event['included_in_issues_value'] === '0.000000', 'Physical split/transfer was counted as COGS variance.');
    echo "PASS adjusted physical split/transfer retains value and source lineage\n";

    $fractional = $production->createRoll(['warehouse_id' => $warehouse['id'], 'item_id' => $rawItem, 'quantity' => '1.125125', 'unit_cost' => '1.234567', 'code' => 'MYSQL-DECIMAL6'], 'mysql-fractional-receipt', $actor->id);
    $production->inspectRoll(['roll_id' => $fractional['public_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Decimal fixture QC'], 'mysql-fractional-qc', $actor->id);
    $hold = $production->reserve(['warehouse_id' => $warehouse['id'], 'item_id' => $rawItem, 'roll_id' => $fractional['public_id'], 'quantity' => '1.125125', 'order_ref' => 'WO-DECIMAL6'], 'mysql-fractional-hold', $actor->id);
    $issue = $production->issue(['reservation_id' => $hold['public_id'], 'expected_version' => 1, 'quantity' => '1.125125'], 'mysql-fractional-issue', $actor->id);
    $expectedCost = BigDecimal::of('1.125125')->multipliedBy('1.234567')->toScale(6, RoundingMode::HalfUp);
    $check($issue['total_cost'] === (string) $expectedCost, 'MySQL lost six-place material quantity/unit cost.');
    $fractionalOutput = $production->postOutput(['warehouse_id' => $warehouse['id'], 'order_ref' => 'WO-DECIMAL6', 'materials' => [['issue_id' => $issue['public_id'], 'quantity' => '1.125125']], 'outputs' => [['item_id' => $fgItem, 'quantity' => '3']]], 'mysql-fractional-output', $actor->id);
    $fractionalOutputRoll = $fractionalOutput['outputs'][0]['roll_id'];
    $production->inspectRoll(['roll_id' => $fractionalOutputRoll, 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Finished fractional-cost QC'], 'mysql-fractional-output-qc', $actor->id);
    $totalIssued = BigDecimal::zero();
    foreach ([1, 2] as $i => $quantity) {
        $hold = $production->reserve(['warehouse_id' => $warehouse['id'], 'item_id' => $fgItem, 'roll_id' => $fractionalOutputRoll, 'quantity' => (string) $quantity, 'order_ref' => 'shipment:decimal6'], 'mysql-rounding-hold-'.$i, $actor->id);
        $issued = $production->issue(['reservation_id' => $hold['public_id'], 'expected_version' => 1, 'quantity' => (string) $quantity], 'mysql-rounding-issue-'.$i, $actor->id);
        $totalIssued = $totalIssued->plus($issued['total_cost']);
    }
    $check($totalIssued->isEqualTo($expectedCost), 'MySQL partial issues lost the closing valuation residual.');
    echo "PASS MySQL DECIMAL6 material cost and last-take rounding conservation\n";
    echo "OK 7 gates / {$assertions} assertions\n";
} catch (Throwable $error) {
    $failure = true;
    fwrite(STDERR, 'FAIL '.get_class($error).': '.$error->getMessage()."\n");
} finally {
    if (isset($connectionName)) {
        DB::disconnect($connectionName);
    }
    if ($created) {
        $admin->exec('DROP DATABASE `'.$name.'`');
        if ($admin->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '.$admin->quote($name))->fetchColumn() !== false) {
            throw new RuntimeException('Scratch cleanup verification failed.');
        }
        echo "Scratch database removed and verified\n";
    }
}

if ($failure) {
    exit(1);
}
