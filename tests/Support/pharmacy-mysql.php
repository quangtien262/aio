<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\PharmacyService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (! in_array('--allow-isolated-mysql', $argv, true)) {
    fwrite(STDERR, "Requires --allow-isolated-mysql; creates/drops only a guarded random local scratch database.\n");
    exit(2);
}
$environment = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
$setting = fn (string $key, mixed $default = null) => getenv($key) !== false ? getenv($key) : ($environment[$key] ?? $default);
if ($setting('DB_CONNECTION', 'mysql') !== 'mysql' || ! in_array($setting('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true) || $setting('DB_URL')) {
    throw new RuntimeException('Only unrouted local MySQL is supported.');
}
$worker = in_array('--worker', $argv, true);
$name = $worker ? (string) getenv('PHARMACY_MYSQL_SCRATCH_DB') : 'aio_pharmacy_'.bin2hex(random_bytes(8));
if (! preg_match('/^aio_pharmacy_[a-f0-9]{16}$/D', $name) || $name === ($environment['DB_DATABASE'] ?? null)) {
    throw new RuntimeException('Scratch database name rejected.');
}
$admin = new PDO('mysql:host='.$setting('DB_HOST', '127.0.0.1').';port='.$setting('DB_PORT', 3306).';charset=utf8mb4', $setting('DB_USERNAME'), $setting('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false;
$failed = false;
$assertions = 0;
$check = function (bool $ok, string $message) use (&$assertions): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
    $assertions++;
};
try {
    if (! $worker) {
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created = true;
    }
    // Exercise production module-owned migration order rather than the aggregate PHPUnit schema.
    foreach (['APP_ENV' => 'local', 'DB_DATABASE' => $name, 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/pharmacy-scratch-unused-'.bin2hex(random_bytes(8)).'.php', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $check(DB::connection()->getDatabaseName() === $name, 'Scratch connection not selected before bootstrap.');
    $service = app(PharmacyService::class);
    if ($worker) {
        $input = json_decode((string) getenv('PHARMACY_MYSQL_INPUT'), true, flags: JSON_THROW_ON_ERROR);
        $actor = Admin::query()->findOrFail($input['actor_id']);
        $delay = (float) getenv('PHARMACY_MYSQL_START_AT') - microtime(true);
        if ($delay > 0 && $delay < 5) {
            usleep((int) ($delay * 1000000));
        }
        try {
            if ($input['operation'] === 'checkout') {
                $d = $service->command($actor, 'dispensing.prepare', ['dispensing_id' => $input['dispensing_id'], 'expected_version' => 1, 'allocations' => [['line_id' => $input['line_id'], 'batch_id' => $input['batch_id'], 'quantity' => '1']]], $input['prepare_key']);
                $d = $service->command($actor, 'dispensing.review', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic concurrent professional review'], $input['review_key']);
                $payload = ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $input['shift_id'], 'tenders' => [['method' => 'cash', 'amount_minor' => $input['amount_minor']]]];
            } else {
                $payload = $input['payload'];
            }
            $d = $service->command($actor, 'dispensing.finalize', $payload, $input['finalize_key']);
            echo json_encode(['status' => 'committed', 'id' => $d['id'], 'replayed' => $d['replayed'], 'payload' => $payload, 'actor_id' => $actor->id, 'finalize_key' => $input['finalize_key']], JSON_THROW_ON_ERROR)."\n";
        } catch (HttpExceptionInterface $e) {
            echo json_encode(['status' => 'rejected', 'http_status' => $e->getStatusCode()], JSON_THROW_ON_ERROR)."\n";
        }
        exit(0);
    }
    $check(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Core migrations failed.');
    $check(Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]) === 0, 'Scratch seed failed.');
    foreach (['merchandise', 'inventory', 'pharmacy'] as $module) {
        app(ModuleManager::class)->install($module);
        app(ModuleManager::class)->enable($module);
    }
    $owner = Admin::query()->findOrFail(1);
    $owner->update(['must_change_password' => false]);
    $command = fn (Admin $actor, string $action, array $payload) => $service->command($actor, $action, $payload, (string) Str::uuid());
    $setup = $command($owner, 'setup', ['chain_name' => 'Synthetic MySQL chain', 'pharmacy_name' => 'Synthetic MySQL origin', 'code' => 'MYSQL']);
    $origin = $setup['pharmacy'];
    $chain = $setup['chain'];
    $target = $command($owner, 'pharmacy.create', ['chain_id' => $chain['id'], 'name' => 'Synthetic MySQL target', 'code' => 'TARGET']);
    $other = Admin::query()->create(['name' => 'Synthetic concurrent pharmacist', 'username' => 'pharmacy-concurrent', 'email' => 'pharmacy-concurrent@example.invalid', 'password' => Str::random(32).'Aa1!', 'is_active' => true, 'must_change_password' => false]);
    $role = Role::query()->create(['name' => 'Synthetic pharmacy test', 'key' => 'synthetic-pharmacy-mysql']);
    $role->permissions()->sync(Permission::query()->whereIn('key', PharmacyAccessService::PERMISSIONS)->pluck('id'));
    AdminRoleAssignment::query()->create(['admin_id' => $other->id, 'role_id' => $role->id, 'scope_type' => 'global']);
    DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'chain_id' => DB::table('ph_chains')->where('public_id', $chain['id'])->value('id'), 'pharmacy_id' => null, 'admin_id' => $other->id, 'permissions' => json_encode(PharmacyAccessService::PERMISSIONS), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $other = $other->fresh();
    foreach ([$origin, $target] as $site) {
        foreach ([$owner, $other] as $actor) {
            $command($owner, 'credential.save', ['pharmacy_id' => $site['id'], 'admin_id' => $actor->id, 'license_number' => 'SYNTHETIC-'.$actor->id, 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic qualification']);
        }
    }
    $counter = $command($owner, 'counter.save', ['pharmacy_id' => $origin['id'], 'code' => 'SECOND', 'name' => 'Synthetic second counter']);
    $shiftA = $command($owner, 'shift.open', ['pharmacy_id' => $origin['id'], 'counter_id' => $origin['counter']['id'], 'opening_minor' => '0']);
    $shiftB = $command($other, 'shift.open', ['pharmacy_id' => $origin['id'], 'counter_id' => $counter['id'], 'opening_minor' => '0']);
    $targetShift = $command($other, 'shift.open', ['pharmacy_id' => $target['id'], 'counter_id' => $target['counter']['id'], 'opening_minor' => '0']);
    $receive = function (array $site, array $product, string $lot) use ($owner, $command): array {
        $receipt = $command($owner, 'receipt.create', ['pharmacy_id' => $site['id'], 'product_id' => $product['id'], 'quantity' => '1', 'unit_cost' => '27', 'batch_code' => $lot, 'expires_at' => now()->addYear()->toDateString()]);
        $command($owner, 'receipt.inspect', ['pharmacy_id' => $site['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspected lot']);

        return $receipt;
    };
    $pair = function (array $inputs) use ($name): array {
        $processes = [];
        $start = (string) (microtime(true) + 1.0);
        foreach ($inputs as $input) {
            $process = new Process([PHP_BINARY, __FILE__, '--allow-isolated-mysql', '--worker'], base_path(), ['PHARMACY_MYSQL_SCRATCH_DB' => $name, 'PHARMACY_MYSQL_INPUT' => json_encode($input, JSON_THROW_ON_ERROR), 'PHARMACY_MYSQL_START_AT' => $start]);
            $process->setTimeout(45);
            $process->start();
            $processes[] = $process;
        }

        return array_map(function (Process $process): array {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Pharmacy worker failed: '.$process->getErrorOutput());
            }

            return json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    };
    $checkout = fn (Admin $actor, array $d, array $shift, array $receipt, string $amount) => ['operation' => 'checkout', 'actor_id' => $actor->id, 'dispensing_id' => $d['id'], 'line_id' => $d['lines'][0]['id'], 'batch_id' => $receipt['batch_id'], 'shift_id' => $shift['id'], 'amount_minor' => $amount, 'prepare_key' => (string) Str::uuid(), 'review_key' => (string) Str::uuid(), 'finalize_key' => (string) Str::uuid()];
    $product = $command($owner, 'product.save', ['pharmacy_id' => $origin['id'], 'name' => 'Synthetic last-lot medicine', 'code' => 'LASTLOT', 'base_unit' => 'tablet', 'price_minor' => '1000']);
    $receipt = $receive($origin, $product, 'LAST-LOT');
    $draft = fn (Admin $actor, array $site, array $product, ?array $rx = null) => $command($actor, 'dispensing.create', ['pharmacy_id' => $site['id'], 'mode' => $rx ? 'rx' : 'otc', 'prescription_id' => $rx['id'] ?? null, 'lines' => [['product_id' => $product['id'], 'quantity' => '1']]]);
    $dA = $draft($owner, $origin, $product);
    $dB = $draft($other, $origin, $product);
    $results = $pair([$checkout($owner, $dA, $shiftA, $receipt, '1000'), $checkout($other, $dB, $shiftB, $receipt, '1000')]);
    $winners = array_values(array_filter($results, fn ($r) => $r['status'] === 'committed'));
    $check(count($winners) === 1, 'Last exact lot finalized twice or neither checkout completed.');
    $check(DB::table('ph_sales')->count() === 1 && DB::table('ph_tenders')->count() === 1, 'Last-lot race duplicated financial records.');
    $warehouse = DB::table('ph_pharmacies')->where('public_id', $origin['id'])->value('warehouse_id');
    $check(app(InventoryV2Service::class)->availability($warehouse, $product['inventory_item_id']) === '0.000000', 'Last-lot race produced wrong stock.');
    echo "PASS independent-process last-lot complete checkout\n";
    $winner = $winners[0];
    $replay = ['operation' => 'finalize', 'actor_id' => $winner['actor_id'], 'payload' => $winner['payload'], 'finalize_key' => $winner['finalize_key']];
    $results = $pair([$replay, $replay]);
    $check($results[0]['replayed'] && $results[1]['replayed'] && $results[0]['id'] === $results[1]['id'], 'Concurrent finalization replay did not reuse the command result.');
    $check(DB::table('ph_sales')->count() === 1 && DB::table('ph_tenders')->count() === 1, 'Same-key replay duplicated money.');
    echo "PASS independent-process same-key financial replay\n";
    $rxProduct = $command($owner, 'product.save', ['pharmacy_id' => $origin['id'], 'name' => 'Synthetic shared Rx medicine', 'code' => 'CHAINRX', 'base_unit' => 'tablet', 'price_minor' => '1500', 'requires_prescription' => true]);
    $command($owner, 'price.save', ['pharmacy_id' => $target['id'], 'product_id' => $rxProduct['id'], 'price_minor' => '1500']);
    $receiptA = $receive($origin, $rxProduct, 'RX-A');
    $receiptB = $receive($target, $rxProduct, 'RX-B');
    $patient = $command($owner, 'patient.save', ['pharmacy_id' => $origin['id'], 'name' => 'Synthetic scratch patient', 'phone' => '0912345678', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic test only']);
    $lookup = $command($owner, 'patient.lookup', ['pharmacy_id' => $origin['id'], 'phone' => '+84912345678']);
    $check(($lookup['records'][0]['id'] ?? null) === $patient['id'], 'Indexed exact phone lookup failed on MySQL.');
    $rx = $command($owner, 'prescription.save', ['pharmacy_id' => $origin['id'], 'patient_id' => $patient['id'], 'reference' => 'SYNTHETIC-ONE-RX', 'issued_at' => now()->toDateString(), 'prescriber_name' => 'Synthetic prescriber', 'facility_name' => 'Synthetic facility', 'original_reference' => 'paper:synthetic', 'lines' => [['product_id' => $rxProduct['id'], 'quantity' => '1', 'instructions' => 'Synthetic instruction']]]);
    $rx = $command($owner, 'prescription.verify', ['prescription_id' => $rx['id'], 'expected_version' => $rx['version'], 'verification_method' => 'manual_review', 'reason' => 'Synthetic original check']);
    $command($owner, 'prescription.transfer', ['prescription_id' => $rx['id'], 'expected_version' => $rx['version'], 'target_pharmacy_id' => $target['id'], 'consented_at' => now()->toIso8601String(), 'consent_evidence' => 'Synthetic target consent']);
    $dA = $draft($owner, $origin, $rxProduct, $rx);
    $dB = $draft($other, $target, $rxProduct, $rx);
    $results = $pair([$checkout($owner, $dA, $shiftA, $receiptA, '1500'), $checkout($other, $dB, $targetShift, $receiptB, '1500')]);
    $winners = array_values(array_filter($results, fn ($r) => $r['status'] === 'committed'));
    $check(count($winners) === 1, 'Same canonical Rx was overfilled across branches.');
    $check(DB::table('ph_prescriptions')->count() === 1 && DB::table('ph_patients')->count() === 1, 'Cross-branch fill cloned clinical records.');
    $check((string) DB::table('ph_dispense_claims')->where('status', 'committed_pending_handover')->sum('quantity') === '1.000000', 'Rx committed entitlement differs from allowed quantity.');
    $check(DB::table('ph_sales')->count() === 2 && DB::table('ph_tenders')->count() === 2 && (int) DB::table('ph_sales')->sum('total_minor') === 2500, 'Cross-branch claim race duplicated money.');
    echo "PASS independent-process same Rx claim across branches\n";
    echo 'OK 3 race gates / '.$assertions." assertions; this is not a capacity benchmark.\n";
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, 'FAIL '.get_class($e).': '.$e->getMessage()."\n");
} finally {
    if ($created) {
        if (isset($app)) {
            DB::disconnect();
        }
        $admin->exec('DROP DATABASE `'.$name.'`');
        $check($admin->query('SHOW DATABASES LIKE '.$admin->quote($name))->fetchColumn() === false, 'Scratch cleanup not verified.');
        echo "Scratch database removed and verified\n";
    }
}
exit($failed ? 1 : 0);
