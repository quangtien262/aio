<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$sp = App\Models\SiteProfile::first();
echo json_encode($sp ? $sp->toArray() : null, JSON_PRETTY_PRINT);
