<?php

use Modules\Pharmacy\Services\PharmacyInventoryPolicy;

return [
    'managed_policies' => [
        'pharmacy' => PharmacyInventoryPolicy::class,
    ],
];
