<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app()->make(App\Http\Controllers\Site\CmsSiteController::class);
$view = $controller->home();
if (is_object($view) && method_exists($view, 'getData')) {
    $data = $view->getData();
    echo json_encode([ 'keys' => array_keys($data), 'themeHomeData' => $data['themeHomeData'] ?? null ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['result' => 'not a view', 'class' => is_object($view) ? get_class($view) : gettype($view)]);
}
