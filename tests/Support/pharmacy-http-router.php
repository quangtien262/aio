<?php

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

// Isolated native-app UAT router: use built assets while preserving a developer's
// public/hot file and running Vite session untouched.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || ! str_starts_with((string) getenv('DB_DATABASE'), '/private/tmp/aio-pharmacy-http-')) {
    http_response_code(403);
    exit('Scratch test environment required.');
}
$public = realpath(__DIR__.'/../../public');
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public.'/'.ltrim((string) $uri, '/'));
if ($file && str_starts_with($file, $public.'/') && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->booted(function () use ($app): void {
    $app->make(Vite::class)->useHotFile(dirname((string) getenv('DB_DATABASE')).'/unused-vite-hot');
});
$app->handleRequest(Request::capture());
