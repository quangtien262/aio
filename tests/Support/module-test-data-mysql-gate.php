<?php

use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class MysqlOwnedFixture implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Isolated MySQL gate'];
    }

    public function references(): array
    {
        return [['table' => 'fixture_refs', 'column' => 'payload->items->*->id', 'foreign_table' => 'fixture_roots']];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $root = $context->insert('fixture_roots', ['public_id' => (string) Str::uuid(), 'code' => $context->code('ROOT')]);
        $context->insert('fixture_children', ['root_id' => $root]);
    }
}

$original = config('database.connections.mysql');
$scratch = 'aio_fixture_'.bin2hex(random_bytes(8));
config(['database.connections.fixture_control' => $original]);
$control = DB::connection('fixture_control');
$created = false;
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
try {
    $check((bool) preg_match('/^aio_fixture_[a-f0-9]{16}$/', $scratch), 'Unsafe scratch identity.');
    $control->statement('CREATE DATABASE `'.$scratch.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $scratch, 'database.connections.fixture_writer' => [...$original, 'database' => $scratch]]);
    DB::purge('mysql');
    Schema::clearResolvedInstance('db.schema');
    $check(DB::connection()->getDatabaseName() === $scratch && Schema::getConnection()->getDatabaseName() === $scratch, 'Scratch connection was not isolated.');
    $migration = require $projectRoot.'/database/migrations/2026_10_22_000001_create_module_test_data_tables.php';
    $migration->up();
    Schema::create('module_installations', function (Blueprint $t): void {
        $t->string('key')->primary();
        $t->string('status');
    });
    Schema::create('module_lifecycle_operations', function (Blueprint $t): void {
        $t->id();
        $t->string('module_key');
        $t->string('active_slot')->nullable();
    });
    Schema::create('fixture_roots', function (Blueprint $t): void {
        $t->id();
        $t->uuid('public_id');
        $t->string('website_key');
        $t->string('code');
        $t->timestamps();
    });
    Schema::create('fixture_children', function (Blueprint $t): void {
        $t->id();
        $t->foreignId('root_id')->constrained('fixture_roots')->cascadeOnDelete();
        $t->timestamps();
    });
    Schema::create('fixture_refs', function (Blueprint $t): void {
        $t->id();
        $t->json('payload');
    });
    $module = ['key' => 'mysql-fixture', 'name' => 'MySQL gate', 'dependencies' => [], 'test_data_provider' => MysqlOwnedFixture::class];
    $registry = Mockery::mock(ModuleRegistry::class)->makePartial();
    $registry->shouldReceive('all')->andReturn(collect([$module]));
    $registry->shouldReceive('find')->with('mysql-fixture')->andReturn($module);
    $audit = Mockery::mock(AuditLogger::class);
    $audit->shouldReceive('record')->andReturn(new AuditLog);
    DB::table('module_installations')->insert(['key' => 'mysql-fixture', 'status' => 'enabled']);
    $actor = (new Admin)->forceFill(['id' => 1]);
    $service = new ModuleTestDataService($registry, new ModuleCapabilityChecker($registry), app(ModuleLifecycleCoordinator::class), $audit);
    $request = (string) Str::uuid();
    $batch = $service->create('mysql-fixture', 'fixture-site', $actor, $request);
    $check($batch === $service->create('mysql-fixture', 'fixture-site', $actor, $request), 'Request replay duplicated a batch.');
    $root = DB::table('fixture_roots')->value('id');
    $writer = DB::connection('fixture_writer');
    DB::beginTransaction();
    DB::table('fixture_refs')->get(); // Establish the old REPEATABLE READ snapshot.
    $writer->table('fixture_refs')->insert(['payload' => json_encode(['items' => [['id' => $root]]])]);
    try {
        $service->remove('fixture-site', $actor);
        throw new RuntimeException('Current JSON scan missed a newer committed real reference.');
    } catch (HttpException $error) {
        $check($error->getStatusCode() === 409, 'Expected reference cleanup blocker.');
    }
    DB::rollBack();
    $check(DB::table('fixture_roots')->count() === 1 && DB::table('module_test_data_records')->count() === 2, 'Blocked cleanup changed fixtures.');
    $writer->table('fixture_refs')->delete();
    $realChild = $writer->table('fixture_children')->insertGetId(['root_id' => $root]);
    try {
        $service->remove('fixture-site', $actor);
        throw new RuntimeException('FK cascade guard missed an unowned child.');
    } catch (HttpException $error) {
        $check($error->getStatusCode() === 409, 'Expected FK cleanup blocker.');
    }
    $writer->table('fixture_children')->where('id', $realChild)->delete();
    $result = $service->remove('fixture-site', $actor);
    $check($result === ['batch_count' => 1, 'record_count' => 2] && DB::table('fixture_roots')->count() === 0, 'Exact-owned roundtrip failed.');
    echo "PASS MySQL: isolated migration, request replay, current JSON read after a newer committed reference, FK cascade blocker, exact-owned cleanup.\n";
} finally {
    if (DB::connection()->transactionLevel() > 0) {
        DB::rollBack();
    }
    DB::disconnect('mysql');
    DB::disconnect('fixture_writer');
    if ($created && preg_match('/^aio_fixture_[a-f0-9]{16}$/', $scratch)) {
        $control->statement('DROP DATABASE `'.$scratch.'`');
        echo "Temporary scratch database removed.\n";
    }
}
