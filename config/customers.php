<?php

use Modules\FnbPos\Services\FnbCustomersProvider;
use Modules\RetailPos\Services\RetailCustomersProvider;

return [
    'consumers' => [
        'fnb-pos' => FnbCustomersProvider::class,
        'retail-pos' => RetailCustomersProvider::class,
    ],
    'export_retention_hours' => 24,
    'import_limit' => 1000,
];
