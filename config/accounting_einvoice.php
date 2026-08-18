<?php

return [
    /*
     * Global operational gates. Both are intentionally opt-in. Enabling a
     * provider connection in the database cannot bypass these deployment gates.
     */
    'network_enabled' => (bool) env('ACCOUNTING_EINVOICE_NETWORK_ENABLED', false),
    'production_enabled' => (bool) env('ACCOUNTING_EINVOICE_PRODUCTION_ENABLED', false),
    'legal_timezone' => env('ACCOUNTING_EINVOICE_LEGAL_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'health_max_age_hours' => (int) env('ACCOUNTING_EINVOICE_HEALTH_MAX_AGE_HOURS', 24),

    'production' => [
        'contract_version' => env(
            'ACCOUNTING_EINVOICE_CONTRACT_VERSION',
            '',
        ),
        'sandbox_health_max_age_hours' => (int) env(
            'ACCOUNTING_EINVOICE_SANDBOX_HEALTH_MAX_AGE_HOURS',
            24,
        ),
    ],
];
