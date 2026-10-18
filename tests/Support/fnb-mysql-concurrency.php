<?php

use App\Models\Admin;
use App\Support\AuditChainVerifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbChainService;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Symfony\Component\Process\Process;

require __DIR__.'/FnbMysqlSandbox.php';
$isWorker = in_array('--worker', $argv, true);
if ($isWorker && (getenv('FNB_MYSQL_GATE_WORKER') !== '1'
    || ! preg_match('/\Aaio_fnb_load_[a-f0-9]{12}\z/', (string) getenv('FNB_MYSQL_GATE_DATABASE'))
    || strlen((string) getenv('FNB_MYSQL_GATE_NONCE')) !== 64
    || getenv('DB_DATABASE') !== getenv('FNB_MYSQL_GATE_DATABASE')
    || getenv('DB_CONNECTION') !== 'mysql' || (getenv('DB_URL') !== false && getenv('DB_URL') !== ''))) {
    throw new RuntimeException('Worker entry refused before application bootstrap: missing isolated gate environment.');
}
if (! $isWorker) {
    FnbMysqlSandbox::requireOptIn($argv, 'tests/Support/fnb-mysql-concurrency.php', '[--outlets=10] [--iterations=25] [--workload=availability|counter-sale]');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function fnbGateOutput(array $value): void
{
    echo json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;
    flush();
}

function fnbGateContext(array $fixture): FnbContext
{
    return new FnbContext('fnb-load', (int) $fixture['outlet_id'], (int) $fixture['actor_id'], (int) $fixture['terminal_id'],
        function (string $operation, array $payload, FnbContext $context): void {
            $permission = match ($operation) {
                'catalog.item.availability' => 'fnb.menu.availability.update',
                'session.open', 'order.create' => 'fnb.order.create',
                'order.line.add', 'session.close' => 'fnb.order.update',
                'order.submit' => 'fnb.order.submit',
                'kitchen.line.transition' => 'fnb.kitchen.update',
                'check.create' => 'fnb.order.split',
                'check.finalize', 'check.settlement_plan', 'payment.collect' => 'fnb.payment.collect',
                'gate.hold' => 'fnb.order.view',
                default => throw new RuntimeException('Unexpected operation in gate authorization: '.$operation),
            };
            $actor = Admin::query()->whereKey($context->actorId)->sharedLock()->firstOrFail();
            app(FnbOutletAccessService::class)->authorizeTerminal($actor, $context->websiteKey, $context->outletId, $context->terminalId, $permission);
        });
}

function fnbGateSale(FnbContext $context, array $fixture, string $key, callable $timed): void
{
    $fnb = app(FnbService::class);
    $session = $timed(fn () => $fnb->openSession($context, ['business_day_id' => $fixture['day_id'], 'service_type' => 'counter', 'guest_count' => 1], $key.'-session'))['resource'];
    $order = $timed(fn () => $fnb->createOrder($context, $session['id'], $fixture['shift_id'], [], $key.'-order', $session['version']))['resource'];
    $order = $timed(fn () => $fnb->addOrderLine($context, $order['id'], ['variant_id' => $fixture['variant_id'], 'quantity' => '1'], $key.'-line', $order['version']))['resource'];
    $timed(fn () => $fnb->submitOrder($context, $order['id'], $key.'-submit', $order['version']));
    $ticket = DB::table('fnb_kitchen_tickets')->where('outlet_id', $context->outletId)->where('order_id', $order['id'])->firstOrFail();
    $line = DB::table('fnb_kitchen_ticket_lines')->where('ticket_id', $ticket->id)->firstOrFail();
    foreach (['preparing', 'ready', 'served'] as $status) {
        $ticketVersion = (int) DB::table('fnb_kitchen_tickets')->where('id', $ticket->id)->value('version');
        $timed(fn () => $fnb->transitionKitchenLine($context, $line->id, $status, $key.'-'.$status, $ticketVersion));
    }
    $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
    $check = $timed(fn () => $fnb->createCheck($context, $session['id'], [], $key.'-check', $sessionVersion))['resource'];
    $check = $timed(fn () => $fnb->finalizeCheck($context, $check['id'], $key.'-finalize', $check['version']))['resource'];
    $check = $timed(fn () => $fnb->setSettlementPlan($context, $check['id'], 'single_cash', $key.'-plan', $check['version']))['resource'];
    $payment = ['payment_method_id' => $fixture['cash_method_id'], 'amount_minor' => 20000, 'tendered_minor' => 20000];
    $timed(fn () => $fnb->collectPayment($context, $check['id'], $fixture['shift_id'], $payment, $key.'-payment', $check['version']));
    // Simulate a lost response: same identity must replay without a second payment.
    $replay = $timed(fn () => $fnb->collectPayment($context, $check['id'], $fixture['shift_id'], $payment, $key.'-payment', $check['version']));
    if (! $replay['replayed']) {
        throw new RuntimeException('Counter-sale payment retry did not replay.');
    }
    $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
    $timed(fn () => $fnb->closeSession($context, $session['id'], $key.'-close', $sessionVersion));
}

if ($isWorker) {
    try {
        FnbMysqlSandbox::assertWorkerDatabase();
        $task = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        $context = fnbGateContext($task['fixture']);
        $latencies = [];
        $timed = function (callable $operation) use (&$latencies): array {
            $start = hrtime(true);
            $result = $operation();
            $latencies[] = (hrtime(true) - $start) / 1_000_000;

            return $result;
        };
        while (microtime(true) < (float) ($task['start_at'] ?? 0)) {
            usleep(1000);
        }
        if ($task['mode'] === 'hold') {
            app(FnbCommandRunner::class)->run($context, 'gate.hold', [], $task['key'], function () use ($task): array {
                $release = $task['release_file'];
                if (! str_starts_with($release, sys_get_temp_dir().'/'.getenv('FNB_MYSQL_GATE_DATABASE').'-')) {
                    throw new RuntimeException('Invalid gate release marker.');
                }
                fnbGateOutput(['type' => 'stage', 'stage' => 'locked']);
                $deadline = microtime(true) + 15;
                while (! is_file($release) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                if (! is_file($release)) {
                    throw new RuntimeException('Independent outlet did not progress before hold deadline.');
                }

                return ['resource' => ['released' => true]];
            });
            fnbGateOutput(['type' => 'result', 'status' => 'ok']);
        } elseif ($task['mode'] === 'race') {
            $result = $timed(fn () => app(FnbService::class)->setAvailability($context, $task['fixture']['item_id'], true, null, null, $task['key'], $task['version']));
            fnbGateOutput(['type' => 'result', 'status' => 'ok', 'replayed' => $result['replayed'], 'latencies_ms' => $latencies]);
        } else {
            $version = (int) DB::table('fnb_outlet_item_states')->where('outlet_id', $context->outletId)->where('item_id', $task['fixture']['item_id'])->value('version');
            for ($iteration = 0; $iteration < $task['iterations']; $iteration++) {
                $key = 'load-'.$context->outletId.'-'.$iteration;
                if ($task['workload'] === 'counter-sale') {
                    fnbGateSale($context, $task['fixture'], $key, $timed);
                } else {
                    $available = $iteration % 2 === 0;
                    $result = $timed(fn () => app(FnbService::class)->setAvailability($context, $task['fixture']['item_id'], $available, null, $available ? null : 'Synthetic load', $key, $version));
                    $version = (int) $result['resource']['version'];
                }
            }
            fnbGateOutput(['type' => 'result', 'status' => 'ok', 'iterations' => $task['iterations'], 'latencies_ms' => $latencies]);
        }
        exit(0);
    } catch (FnbConflictException $exception) {
        fnbGateOutput(['type' => 'result', 'status' => 'conflict', 'message' => $exception->getMessage()]);
        exit(0);
    } catch (Throwable $exception) {
        fnbGateOutput(['type' => 'result', 'status' => 'error', 'exception' => get_class($exception), 'message' => $exception->getMessage()]);
        exit(1);
    }
}

$options = ['outlets' => 10, 'iterations' => 25, 'workload' => 'availability'];
foreach ($argv as $argument) {
    if (preg_match('/^--(outlets|iterations|workload)=(.+)$/', $argument, $match)) {
        $options[$match[1]] = $match[2];
    }
}
$outletCount = filter_var($options['outlets'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 50]]);
$iterations = filter_var($options['iterations'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
if ($outletCount === false || $iterations === false || ! in_array($options['workload'], ['availability', 'counter-sale'], true)) {
    throw new InvalidArgumentException('Use outlets=2..50, iterations=1..1000, workload=availability|counter-sale.');
}
$sandbox = new FnbMysqlSandbox('aio_fnb_load_');
$workers = [];
$markers = [];
$passed = false;
$spawn = function (array $task, float $timeout = 30) use ($sandbox, &$workers): Process {
    $process = new Process([PHP_BINARY, __FILE__, '--worker'], dirname(__DIR__, 2), $sandbox->workerEnvironment());
    $process->setInput(json_encode($task, JSON_THROW_ON_ERROR));
    $process->setTimeout($timeout);
    $process->start();
    $workers[] = $process;

    return $process;
};
$resultOf = function (Process $process): array {
    $process->wait();
    foreach (array_reverse(explode("\n", trim($process->getOutput()))) as $line) {
        $result = json_decode($line, true);
        if (is_array($result) && ($result['type'] ?? '') === 'result') {
            if (! $process->isSuccessful() || $result['status'] === 'error') {
                throw new RuntimeException('Gate worker failed: '.($result['message'] ?? 'unknown'));
            }

            return $result;
        }
    }
    throw new RuntimeException('Worker returned no structured result: '.substr($process->getErrorOutput(), 0, 500));
};
$assert = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    $sandbox->create();
    $sandbox->install();
    $now = now();
    DB::table('admins')->insert(['id' => 1, 'name' => 'Synthetic gate owner', 'username' => 'gate-owner', 'email' => 'owner@gate.invalid', 'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT), 'is_active' => true, 'status' => 'active', 'is_system_owner' => true, 'must_change_password' => false, 'auth_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('sites')->insert(['website_key' => 'fnb-load', 'name' => 'Synthetic load only', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $owner = Admin::findOrFail(1);
    $fnb = app(FnbService::class);
    $hq = new FnbContext('fnb-load', 0, 1);
    $onboard = $fnb->onboard($hq, ['outlet' => ['code' => 'LOAD-1', 'name' => 'Synthetic outlet 1', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'], 'terminal' => ['code' => 'POS', 'name' => 'POS'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'table_count' => 0], 'load-onboard')['resource'];
    $firstContext = new FnbContext('fnb-load', $onboard['outlet']['id'], 1, $onboard['terminal']['id']);
    $item = $fnb->saveCatalogItem($firstContext, ['code' => 'LOAD-COFFEE', 'name' => 'Synthetic coffee', 'tax_rate_bps' => 0, 'variants' => [['code' => 'ONE', 'name' => 'One', 'base_price_minor' => 20000, 'station_id' => $onboard['station']['id']]]], 'load-item')['resource'];
    $fixtures = [];
    for ($index = 0; $index < $outletCount; $index++) {
        $outlet = $index === 0 ? ['outlet' => $onboard['outlet'], 'terminals' => [$onboard['terminal']]]
            : app(FnbChainService::class)->save($hq, 'outlet', null, ['code' => 'LOAD-'.($index + 1), 'name' => 'Synthetic outlet '.($index + 1), 'table_count' => 0], 'load-outlet-'.$index)['resource'];
        $outletId = (int) $outlet['outlet']['id'];
        $terminalId = (int) ((array) $outlet['terminals'][0])['id'];
        $actorId = DB::table('admins')->insertGetId(['name' => 'Synthetic operator '.$index, 'username' => 'gate-'.$index, 'email' => 'operator-'.$index.'@gate.invalid', 'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT), 'is_active' => true, 'status' => 'active', 'is_system_owner' => false, 'must_change_password' => false, 'auth_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($owner, 'fnb-load', $outletId, Admin::findOrFail($actorId), 'fnb-manager', terminalIds: [$terminalId]));
        DB::table('fnb_outlet_item_states')->insertOrIgnore(['website_key' => 'fnb-load', 'outlet_id' => $outletId, 'item_id' => $item['id'], 'is_available' => true, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $context = new FnbContext('fnb-load', $outletId, 1, $terminalId);
        $day = $fnb->openBusinessDay($context, now()->toDateString(), 'load-day-'.$index)['resource'];
        $shift = $fnb->openShift($context, $day['id'], 0, 'load-shift-'.$index)['resource'];
        $fixtures[] = ['outlet_id' => $outletId, 'terminal_id' => $terminalId, 'actor_id' => $actorId, 'item_id' => (int) $item['id'], 'variant_id' => (int) $item['variants'][0]['id'], 'day_id' => (int) $day['id'], 'shift_id' => (int) $shift['id'], 'cash_method_id' => (int) DB::table('fnb_payment_methods')->where('outlet_id', $outletId)->where('kind', 'cash')->value('id')];
    }

    // Hold one real command transaction until a separate outlet commits; no guessed latency SLA.
    $release = sys_get_temp_dir().'/'.$sandbox->name.'-'.bin2hex(random_bytes(6)).'-release';
    $markers[] = $release;
    $hold = $spawn(['mode' => 'hold', 'fixture' => $fixtures[0], 'key' => 'gate-hold', 'release_file' => $release], 20);
    $buffer = '';
    $assert($hold->waitUntil(function ($type, $output) use (&$buffer): bool {
        $buffer .= $output;

        return str_contains($buffer, '"stage":"locked"');
    }), 'Hold worker failed before the scope barrier.');
    $version = (int) DB::table('fnb_outlet_item_states')->where('outlet_id', $fixtures[1]['outlet_id'])->where('item_id', $item['id'])->value('version');
    try {
        $independent = $resultOf($spawn(['mode' => 'race', 'fixture' => $fixtures[1], 'key' => 'gate-independent', 'version' => $version], 8));
        $assert($independent['status'] === 'ok', 'Independent outlet command did not commit.');
    } finally {
        file_put_contents($release, 'release');
    }
    $assert($resultOf($hold)['status'] === 'ok', 'Held transaction failed.');

    foreach ([true, false] as $sameKey) {
        $version = (int) DB::table('fnb_outlet_item_states')->where('outlet_id', $fixtures[0]['outlet_id'])->where('item_id', $item['id'])->value('version');
        $startAt = microtime(true) + 0.5;
        $race = [];
        foreach ([0, 1] as $index) {
            $race[] = $spawn(['mode' => 'race', 'fixture' => $fixtures[0], 'key' => $sameKey ? 'gate-same-key' : 'gate-conflict-'.$index, 'version' => $version, 'start_at' => $startAt]);
        }
        $results = array_map($resultOf, $race);
        $assert((int) DB::table('fnb_outlet_item_states')->where('outlet_id', $fixtures[0]['outlet_id'])->where('item_id', $item['id'])->value('version') === $version + 1, 'Concurrent commands changed the aggregate more than once.');
        if ($sameKey) {
            $assert(array_column($results, 'status') === ['ok', 'ok'] && count(array_filter($results, fn ($result) => $result['replayed'])) === 1, 'Same-key retry did not yield exactly one replay.');
        } else {
            $statuses = array_column($results, 'status');
            sort($statuses);
            $assert($statuses === ['conflict', 'ok'], 'Optimistic race did not yield exactly one conflict.');
        }
    }

    DB::table('fnb_outlet_item_states')->update(['is_available' => true]);
    $availabilityVersions = DB::table('fnb_outlet_item_states')->where('item_id', $item['id'])->pluck('version', 'outlet_id')->all();
    $started = hrtime(true);
    $load = [];
    $allLatencies = [];
    foreach ($fixtures as $fixture) {
        $load[] = $spawn(['mode' => 'load', 'fixture' => $fixture, 'iterations' => $iterations, 'workload' => $options['workload']], max(60, $iterations * 15));
    }
    foreach ($load as $process) {
        $result = $resultOf($process);
        $assert($result['status'] === 'ok' && $result['iterations'] === $iterations, 'Load worker did not finish its requested iterations.');
        $allLatencies = array_merge($allLatencies, $result['latencies_ms']);
    }
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    if ($options['workload'] === 'counter-sale') {
        $assert(DB::table('fnb_payments')->where('status', 'succeeded')->count() === $outletCount * $iterations, 'Counter-sale payment count diverged.');
        $assert((int) DB::table('fnb_payments')->where('status', 'succeeded')->sum('amount_minor') === $outletCount * $iterations * 20000, 'Collected money diverged.');
        $assert(DB::table('fnb_service_sessions')->where('status', '!=', 'closed')->count() === 0, 'Counter-sale left sessions open.');
        foreach ($fixtures as $fixture) {
            $assert((int) DB::table('fnb_shifts')->where('id', $fixture['shift_id'])->value('expected_cash_minor') === $iterations * 20000, 'Per-terminal drawer total diverged.');
        }
    }
    if ($options['workload'] === 'availability') {
        foreach ($fixtures as $fixture) {
            $actualVersion = (int) DB::table('fnb_outlet_item_states')->where('outlet_id', $fixture['outlet_id'])->where('item_id', $item['id'])->value('version');
            $assert($actualVersion === (int) $availabilityVersions[$fixture['outlet_id']] + $iterations, 'Availability load lost or duplicated an aggregate mutation.');
        }
    }
    $verification = app(AuditChainVerifier::class)->verify();
    $assert($verification['valid'], 'Audit chain failed after concurrency/load.');
    sort($allLatencies);
    $percentile = fn (float $p) => round($allLatencies[max(0, (int) ceil(count($allLatencies) * $p) - 1)], 2);
    fnbGateOutput(['type' => 'summary', 'passed' => true, 'mysql_version' => DB::selectOne('SELECT VERSION() AS version')->version,
        'outlets' => $outletCount, 'iterations_per_outlet' => $iterations, 'workload' => $options['workload'], 'command_calls' => count($allLatencies),
        'elapsed_seconds' => round($elapsed, 2), 'commands_per_second' => round(count($allLatencies) / $elapsed, 2),
        'command_latency_ms' => ['p50' => $percentile(0.5), 'p95' => $percentile(0.95), 'p99' => $percentile(0.99), 'max' => round(max($allLatencies), 2)],
        'audit_rows_verified' => $verification['count'], 'gates' => ['independent_outlet_progress', 'same_key_replay', 'same_aggregate_version_conflict', 'load_completion', 'audit_integrity'],
        'scope' => 'Synthetic domain commands with live outlet/terminal authorization; excludes HTTP sessions, browser UX, payment providers and physical printers. No production SLA is implied.']);
    $passed = true;
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
} finally {
    foreach ($markers as $marker) {
        if (! is_file($marker)) {
            file_put_contents($marker, 'release');
        }
    }
    foreach ($workers as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
        }
    }
    foreach ($markers as $marker) {
        if (is_file($marker)) {
            unlink($marker);
        }
    }
    $sandbox->close();
}
exit($passed ? 0 : 1);
