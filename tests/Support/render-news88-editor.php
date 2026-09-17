<?php

// Render a real NEWS88 admin page against an isolated, in-memory database.
foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => __DIR__.'/nonexistent-news88-config.php',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Models\SiteProfile::query()->create([
    'website_key' => 'website-main', 'site_name' => 'NEWS88 browser test',
    'website_type' => 'news', 'active_theme_key' => 'NEWS88', 'branding' => [],
]);
Illuminate\Support\Facades\Auth::guard('admin')->setUser(App\Models\Admin::factory()->create());
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('http://localhost/vi?mod=admin');
$response = $kernel->handle($request);
if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, 'NEWS88 render failed: HTTP '.$response->getStatusCode());
    exit(1);
}
echo $response->getContent();
$kernel->terminate($request, $response);
