<?php

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Customers\Services\ConsumerCustomerService;
use Modules\Customers\Services\CustomerExportService;
use Modules\Customers\Services\CustomerIntegrationService;
use Modules\Customers\Services\CustomerService;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbCustomerDirectory;
use Modules\FnbPos\Services\FnbCustomerService;
use Modules\FnbPos\Services\FnbService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\RetailPos\Services\RetailService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (! in_array('--allow-isolated-mysql', $argv, true)) {
    fwrite(STDERR, "Use --allow-isolated-mysql to create and remove a new local scratch database only.\n");
    exit(2);
}
// Exercise the production lifecycle order, not PHPUnit's aggregate test migration collector.
putenv('APP_ENV=local');
putenv('APP_CONFIG_CACHE='.sys_get_temp_dir().'/aio-customers-gate-unused-'.bin2hex(random_bytes(8)).'.php');
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = DB::getDefaultConnection();
$sourceConfig = DB::connection($source)->getConfig();
if (($sourceConfig['driver'] ?? null) !== 'mysql'
    || ! in_array($sourceConfig['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true)
    || isset($sourceConfig['read']) || isset($sourceConfig['write'])) {
    throw new RuntimeException('This gate requires a local, unrouted MySQL connection.');
}
$worker = in_array('--worker', $argv, true);
$name = $worker ? (string) getenv('CUSTOMERS_GATE_DATABASE') : 'aio_customers_gate_'.bin2hex(random_bytes(8));
$connection = $name;
$storage = sys_get_temp_dir().'/'.$name;
$created = false;
$assertions = 0;
$gates = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};
$pass = static function (string $message) use (&$gates): void {
    $gates++;
    echo "PASS {$gates}: {$message}\n";
};
$expectHttp = static function (callable $action, int $status, string $message) use ($assert): void {
    try {
        $action();
    } catch (HttpExceptionInterface $error) {
        $assert($error->getStatusCode() === $status, $message.' (unexpected HTTP status)');

        return;
    }
    throw new RuntimeException($message);
};
$code = 1;
if (! $worker) {
    try {
        DB::connection($source)->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created = true;
        $nonce = bin2hex(random_bytes(32));
        config(['database.connections.'.$connection => array_replace($sourceConfig, ['database' => $name, 'url' => null])]);
        // Explicit connection methods bypass any facade resolved while booting the source app.
        DB::connection($connection)->statement('CREATE TABLE aio_customers_gate_owner (nonce_hash CHAR(64) PRIMARY KEY)');
        DB::connection($connection)->table('aio_customers_gate_owner')->insert(['nonce_hash' => hash('sha256', $nonce)]);
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', __FILE__, '--allow-isolated-mysql', '--worker'], dirname(__DIR__, 2), [
            'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => (string) $sourceConfig['host'], 'DB_PORT' => (string) ($sourceConfig['port'] ?? 3306),
            'DB_SOCKET' => (string) ($sourceConfig['unix_socket'] ?? ''), 'DB_DATABASE' => $name,
            'DB_USERNAME' => (string) $sourceConfig['username'], 'DB_PASSWORD' => (string) $sourceConfig['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            'CUSTOMERS_GATE_DATABASE' => $name, 'CUSTOMERS_GATE_NONCE' => $nonce,
        ]);
        $process->setTimeout(240);
        $code = $process->run(static function (string $type, string $buffer): void {
            echo $buffer;
        });
    } finally {
        DB::purge($connection);
        if ($created && preg_match('/^aio_customers_gate_[a-f0-9]{16}$/D', $name) === 1) {
            DB::connection($source)->statement('DROP DATABASE `'.$name.'`');
            if (is_dir($storage) && dirname($storage) === sys_get_temp_dir() && basename($storage) === $name) {
                File::deleteDirectory($storage);
            }
            echo "Generated scratch database and export files removed.\n";
        }
    }
    exit($code);
}

try {
    $assert(preg_match('/^aio_customers_gate_[a-f0-9]{16}$/D', $name) === 1 && $sourceConfig['database'] === $name
        && strlen((string) getenv('CUSTOMERS_GATE_NONCE')) === 64, 'Worker may only use its generated scratch database.');
    $assert((string) DB::selectOne('SELECT DATABASE() AS db')->db === $name, 'Scratch connection identity mismatch.');
    $assert(DB::table('aio_customers_gate_owner')->where('nonce_hash', hash('sha256', (string) getenv('CUSTOMERS_GATE_NONCE')))->exists(), 'Scratch ownership marker mismatch.');
    config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array', 'filesystems.disks.local.root' => $storage]);
    $assert(app()->environment('local'), 'Module migration test collector must be disabled.');
    $assert(Artisan::call('migrate', ['--path' => 'database/migrations', '--force' => true, '--no-interaction' => true]) === 0, 'Fresh core migration failed.');
    $assert(Schema::hasTable('admins') && ! Schema::hasTable('fnb_outlets'), 'Core migrations must run before module schemas.');
    $actor = Admin::query()->create(['name' => 'Synthetic Customers gate owner', 'username' => 'customers-gate', 'email' => 'customers-gate@example.invalid',
        'password' => Str::random(40).'Aa1!', 'is_active' => true, 'status' => 'active', 'is_system_owner' => true, 'must_change_password' => false]);
    app(SiteContext::class)->set(null, 'website-main');
    auth('admin')->setUser($actor);
    $manager = app(ModuleManager::class);
    foreach (['customers', 'fnb-pos', 'merchandise', 'inventory', 'retail-pos'] as $module) {
        $manager->install($module);
        $manager->enable($module);
        $assert(DB::table('module_installations')->where('key', $module)->value('status') === 'enabled', 'Lifecycle failed for '.$module);
    }
    $actor = $actor->fresh();
    auth('admin')->setUser($actor);
    $assert(! Schema::hasTable('cms_posts') && ! Schema::hasTable('catalog_products'), 'Optional CMS/Catalog must remain absent.');
    $pass('fresh core migration + five real module lifecycle installations without CMS/Catalog');

    $customers = app(CustomerService::class);
    $book = $customers->createBook($actor, ['name' => 'Synthetic shared customers', 'code' => 'MYSQL-GATE'], 'mysql-book-create')['public_id'];
    $data = ['name' => '=SYNTHETIC_CUSTOMER()', 'phone' => '0901234567', 'email' => 'synthetic@example.invalid'];
    $profile = $customers->save($actor, $book, $data, null, 'mysql-profile-once');
    $replay = $customers->save($actor, $book, $data, null, 'mysql-profile-once');
    $assert($profile['public_id'] === $replay['public_id'] && DB::table('cust_profiles')->count() === 1, 'Create replay duplicated identity.');
    $second = $customers->save($actor, $book, ['name' => 'Separate person sharing phone', 'phone' => '0901234567'], null, 'mysql-shared-phone');
    $assert($profile['public_id'] !== $second['public_id'] && DB::table('cust_profiles')->count() === 2, 'Contact equality must not merge people.');
    $updated = $customers->save($actor, $book, ['name' => '=SYNTHETIC_UPDATED()', 'expected_version' => 1], $profile['public_id'], 'mysql-profile-update');
    $assert($updated['version'] === 2 && $updated['phone_normalized'] === '+84901234567', 'MySQL version/contact normalization failed.');
    $expectHttp(fn () => $customers->save($actor, $book, ['name' => 'Stale', 'expected_version' => 1], $profile['public_id'], 'mysql-stale-update'), 409, 'Stale version was accepted.');
    $journal = DB::table('cust_commands')->get()->toJson();
    $assert(! str_contains($journal, 'synthetic@example.invalid') && ! str_contains($journal, '=SYNTHETIC_'), 'Idempotency journal leaked PII.');
    $pass('native Customers CRUD, version, idempotency, duplicate-phone separation and PII-safe journal');

    $fnb = app(FnbService::class);
    $setup = $fnb->onboard(new FnbContext('website-main', 0, $actor->id), [
        'outlet' => ['code' => 'MYSQL', 'name' => 'Synthetic gate cafe', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
        'terminal' => ['code' => 'POS', 'name' => 'Synthetic POS', 'type' => 'pos'],
        'station' => ['code' => 'BAR', 'name' => 'Synthetic bar'],
        'payment_methods' => [['code' => 'CASH', 'name' => 'Cash', 'kind' => 'cash']], 'table_count' => 0,
    ], 'mysql-fnb-onboard')['resource'];
    $context = new FnbContext('website-main', (int) $setup['outlet']['id'], $actor->id, (int) $setup['terminal']['id']);
    $day = $fnb->openBusinessDay($context, now()->toDateString(), 'mysql-fnb-day')['resource']['id'];
    $legacy = $fnb->createCustomer($context, ['name' => 'Legacy frozen buyer', 'phone' => '0987654321', 'notes' => 'Private FNB note', 'birthday' => '1990-01-02', 'privacy_consent' => true], 'mysql-fnb-legacy')['resource'];
    $session = $fnb->openSession($context, ['business_day_id' => $day, 'service_type' => 'counter', 'customer_profile_id' => $legacy['id']], 'mysql-fnb-legacy-session')['resource'];
    $frozen = DB::table('fnb_service_sessions')->where('id', $session['id'])->value('customer_snapshot');
    $integrations = app(CustomerIntegrationService::class);
    $abilities = ['lookup', 'create', 'view', 'update', 'attach', 'history'];
    $binding = $integrations->bind($actor, $book, ['module_key' => 'fnb-pos', 'scope_type' => 'website', 'scope_key' => 'website-main', 'abilities' => $abilities], 'mysql-fnb-binding')['public_id'];
    $before = DB::table('cust_profiles')->count();
    $dry = $integrations->migrate($actor, $binding, [], 'mysql-fnb-preview');
    $assert($dry['valid'] && $dry['accepted_count'] === 1 && ! $dry['committed'] && DB::table('cust_profiles')->count() === $before, 'Migration preview mutated profiles.');
    $cutover = $integrations->migrate($actor, $binding, ['commit' => true, 'cutover' => true], 'mysql-fnb-cutover');
    $assert($cutover['cutover'] && $cutover['created_count'] === 1, 'FNB cutover did not commit.');
    $assert($integrations->migrate($actor, $binding, ['commit' => true, 'cutover' => true], 'mysql-fnb-cutover')['created_count'] === 1 && DB::table('cust_sources')->count() === 1, 'Cutover replay duplicated mapping.');
    $shared = app(ConsumerCustomerService::class)->resolveLegacy($actor, 'fnb-pos', (string) $context->outletId, (string) $legacy['id']);
    $assert($shared !== null && Str::isUuid($shared['public_id']), 'Legacy source did not resolve to shared UUID.');
    $detail = app(FnbCustomerService::class)->profile($context, $shared['public_id'])['resource'];
    $assert($detail['notes'] === 'Private FNB note' && $detail['birthday'] === '1990-01-02', 'FNB private source fields were lost.');
    $assert(DB::table('cust_profiles')->where('public_id', $shared['public_id'])->value('notes') === null && DB::table('cust_consents')->count() === 0, 'FNB private data became canonical notes/consent.');
    $assert(DB::table('fnb_service_sessions')->where('id', $session['id'])->value('customer_snapshot') === $frozen && (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('customer_profile_id') === (int) $legacy['id'], 'Cutover rewrote historical buyer identity.');
    $newSession = $fnb->openSession($context, ['business_day_id' => $day, 'service_type' => 'counter', 'customer_profile_id' => $shared['public_id']], 'mysql-fnb-shared-session')['resource'];
    $assert($newSession['shared_customer_id'] === $shared['public_id'] && $newSession['customer_profile_id'] === null, 'New FNB session did not use shared lineage.');
    $pass('FNB legacy preview/cutover/replay, source mapping, private fields and immutable old buyer snapshots');

    $retail = app(RetailService::class);
    $command = static function (string $operation, array $data, callable $mutation, string $resource = '', ?string $key = null) use ($retail, $actor): array {
        $key ??= 'mysql-customer-'.Str::uuid();

        return $retail->command($actor, $key, $operation, ['resource' => $resource, 'data' => $data], fn ($live) => $mutation($live, $key));
    };
    $branch = $command('setup', ['name' => 'Synthetic shared Retail'], fn ($live) => $retail->setup($live, ['name' => 'Synthetic shared Retail']));
    $integrations->bind($actor, $book, ['module_key' => 'retail-pos', 'scope_type' => 'chain', 'scope_key' => $branch['chain_id'], 'abilities' => $abilities], 'mysql-retail-binding');
    $retailBinding = DB::table('cust_bindings')->where('module_key', 'retail-pos')->value('public_id');
    $integrations->migrate($actor, $retailBinding, ['commit' => true, 'cutover' => true], 'mysql-retail-cutover');
    $data = ['branch_id' => $branch['id'], 'sku' => 'MYSQL-SHARED', 'name' => 'Synthetic shared goods', 'price_minor' => '10000', 'opening_quantity' => '3', 'unit_cost' => '4000'];
    $product = $command('product.create', $data, fn ($live) => $retail->saveProduct($live, $data));
    $data = ['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id'], 'opening_minor' => '0'];
    $shift = $command('shift.open', $data, fn ($live) => $retail->openShift($live, $data));
    $saleInput = ['branch_id' => $branch['id'], 'terminal_id' => $branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'shared_customer_id' => $shared['public_id'], 'lines' => [['sku_id' => $product['id'], 'quantity' => '1']]];
    $sale = $command('sale.create', $saleInput, fn ($live) => $retail->createSale($live, $saleInput));
    $data = ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => '10000', 'tendered_minor' => '10000']]];
    $completed = $command('sale.complete', $data, fn ($live, $key) => $retail->completeSale($live, $sale['id'], $data, $key), $sale['id'], 'mysql-shared-sale-complete');
    $assert($completed['status'] === 'completed' && $completed['shared_customer_id'] === $shared['public_id'] && $completed['customer_id'] === null, 'Retail shared sale lineage failed.');
    $assert($completed['buyer_snapshot']['name'] === 'Legacy frozen buyer' && DB::table('retail_tenders')->count() === 1, 'Retail buyer/tender snapshot failed.');
    $item = $retail->products($actor, $branch['id'], 'MYSQL-SHARED')[0]['inventory_item_id'];
    $inventory = app(InventoryV2Service::class);
    $assert($inventory->availability((int) $branch['warehouse_id'], $item) === '2.000000', 'Shared Retail sale did not atomically consume one unit.');
    $pass('shared Retail sale completes atomically with shared buyer snapshot, stock and tender');

    $exports = app(CustomerExportService::class);
    $export = $exports->create($actor, $book, 'mysql-private-export');
    $assert($exports->status($actor, $export['public_id'])['status'] === 'ready', 'Synchronous export worker did not finish.');
    $row = DB::table('cust_exports')->where('public_id', $export['public_id'])->first();
    $csv = Storage::disk('local')->get($row->path);
    $assert(str_contains($csv, "'=SYNTHETIC_UPDATED()") && hash_equals($row->checksum, hash('sha256', $csv)), 'Export formula neutralization or checksum failed.');
    $assert(str_starts_with(Storage::disk('local')->path($row->path), $storage.'/'), 'Export escaped scratch storage.');
    $assert($exports->download($actor, $export['public_id'])->getStatusCode() === 200, 'Authorized export download failed.');
    $pass('private export job, checksum, CSV formula neutralization and reauthorized download');

    $pending = $command('sale.create', $saleInput, fn ($live) => $retail->createSale($live, $saleInput));
    $manager->disable('customers');
    $status = app(FnbCustomerDirectory::class)->status($context);
    $assert($status['mode'] === 'shared' && ! $status['ready'], 'Disabled Customers silently reverted binding mode.');
    $expectHttp(fn () => $fnb->searchCustomers($context, ''), 503, 'FNB shared lookup did not fail closed.');
    $expectHttp(fn () => $fnb->openSession($context, ['business_day_id' => $day, 'service_type' => 'counter', 'customer_profile_id' => $shared['public_id']], 'mysql-disabled-named'), 503, 'Named FNB session accepted disabled directory.');
    $anonymous = $fnb->openSession($context, ['business_day_id' => $day, 'service_type' => 'counter'], 'mysql-disabled-anonymous')['resource'];
    $assert($anonymous['shared_customer_id'] === null && $anonymous['customer_profile_id'] === null, 'Anonymous FNB service unexpectedly requires Customers.');
    $data = ['expected_version' => $pending['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => '10000', 'tendered_minor' => '10000']]];
    $expectHttp(fn () => $command('sale.complete', $data, fn ($live, $key) => $retail->completeSale($live, $pending['id'], $data, $key), $pending['id']), 503, 'Retail named collection accepted disabled Customers.');
    $assert(DB::table('retail_tenders')->count() === 1 && DB::table('retail_sales')->where('public_id', $pending['id'])->value('status') === 'priced', 'Blocked shared sale wrote tender or changed state.');
    $assert($inventory->availability((int) $branch['warehouse_id'], $item) === '2.000000', 'Blocked named collection changed stock.');
    $assert(DB::table('fnb_customer_profiles')->count() === 1 && DB::table('retail_customers')->count() === 0, 'Disabled shared operations fell back to local creation.');
    $guestInput = $saleInput;
    unset($guestInput['shared_customer_id']);
    $guest = $command('sale.create', $guestInput, fn ($live) => $retail->createSale($live, $guestInput));
    $data['expected_version'] = $guest['version'];
    $guestComplete = $command('sale.complete', $data, fn ($live, $key) => $retail->completeSale($live, $guest['id'], $data, $key), $guest['id']);
    $assert($guestComplete['status'] === 'completed' && $guestComplete['shared_customer_id'] === null && $guestComplete['customer_id'] === null, 'Anonymous Retail sale unexpectedly requires Customers.');
    $assert($inventory->availability((int) $branch['warehouse_id'], $item) === '1.000000' && DB::table('retail_tenders')->count() === 2, 'Anonymous Retail collection did not settle atomically.');
    $pass('real Customers disable preserves shared mode, blocks named writes and permits anonymous FNB/Retail service');
    echo "OK: {$gates} MySQL gates, {$assertions} assertions.\n";
    $code = 0;
} catch (Throwable $error) {
    // Never emit SQL/bindings or connection credentials.
    $message = $error instanceof QueryException ? 'Database gate failed (SQLSTATE '.$error->getCode().').' : $error->getMessage();
    if ($error instanceof QueryException && preg_match('/Table \'([A-Za-z0-9_]+)\' already exists/', $error->getMessage(), $match)) {
        $message .= ' Duplicate table: '.$match[1];
    }
    fwrite(STDERR, 'FAIL: '.get_class($error).' '.$message."\n");
} finally {
    DB::setDefaultConnection($source);
    DB::purge($connection);
    if ($created && preg_match('/^aio_customers_gate_[a-f0-9]{16}$/D', $name) === 1) {
        DB::connection($source)->statement('DROP DATABASE `'.$name.'`');
        if (is_dir($storage) && dirname($storage) === sys_get_temp_dir() && basename($storage) === $name) {
            File::deleteDirectory($storage);
        }
        echo "Generated scratch database and export files removed.\n";
    }
}
exit($code);
