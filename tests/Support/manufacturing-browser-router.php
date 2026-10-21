<?php

use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

$root = dirname(__DIR__, 2);
$fixture = getenv('MANUFACTURING_BROWSER_FIXTURE') ?: '';
$secret = getenv('MANUFACTURING_BROWSER_SECRET') ?: '';
if (PHP_SAPI !== 'cli-server' || ! is_file($fixture) || ! preg_match('/^manufacturing-browser-[a-f0-9]+\.sqlite$/', basename($fixture))
    || realpath(dirname($fixture)) !== realpath($root.'/storage/framework/testing') || strlen($secret) < 32) {
    http_response_code(403);
    exit('Isolated manufacturing fixture required.');
}
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $root.'/storage/framework/testing/manufacturing-no-cache.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $fixture, 'DB_URL' => '', 'SESSION_DRIVER' => 'database',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_DEBUG' => 'true'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path && $path !== '/' && is_file($root.'/public'.$path) && str_starts_with((string) realpath($root.'/public'.$path), $root.'/public/')) {
    return false;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Keep this fixture on its compiled assets even when a developer's Vite server
// has left public/hot in place. HMR must not reload a form during this test.
$app->make(\Illuminate\Foundation\Vite::class)->useHotFile($root.'/storage/framework/testing/manufacturing-browser-no-hot');
$app['config']->set('filesystems.disks.manufacturing_private.root', $fixture.'.files');
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $fixture) {
    throw new RuntimeException('Browser fixture isolation check failed.');
}
Route::middleware('web')->get('/__manufacturing_fixture/login/{token}/{actor?}', function (Request $request, string $token, string $actor = 'owner') use ($secret) {
    abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true) && hash_equals($secret, $token), 404);
    abort_unless(in_array($actor, ['owner', 'operator', 'guest'], true), 404);
    $account = $actor !== 'owner' ? Admin::query()->where('username', 'manufacturing-operator')->firstOrFail() : Admin::query()->where('id', 1)->where('username', 'manufacturing-browser')->firstOrFail();
    if ($actor !== 'owner') {
        // Re-establish the forced-password fixture on each isolated browser run.
        $account->forceFill(['password' => 'ManufacturingOperator123!', 'must_change_password' => true])->save();
        DB::table('sites')->update(['status' => 'inactive']);
    }
    if ($actor === 'guest') {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/modules/manufacturing/execution');
    }
    Auth::guard('admin')->login($account);
    $request->session()->regenerate();
    $request->session()->put('admin_auth_version', $account->auth_version);
    return redirect('/admin/modules/manufacturing/'.($actor === 'operator' ? 'execution' : 'settings'));
});
$app->handleRequest(Request::capture());
