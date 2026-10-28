<?php

return [
    'private_disk' => 'pharmacy_private',
    'network_enabled' => (bool) env('PHARMACY_NETWORK_ENABLED', false),
    'production_enabled' => (bool) env('PHARMACY_PRODUCTION_ENABLED', false),
    'allowed_provider_hosts' => array_values(array_filter(explode(',', (string) env('PHARMACY_PROVIDER_HOSTS', '')))),
    // Only contract-verified adapters may be registered here; an arbitrary endpoint is not a provider.
    'transports' => [],
    'prescription_valid_days' => 5,
    'reservation_minutes' => 15,
    'exports_retention_hours' => 24,
    'advanced_release' => ['controlled' => false, 'cold_chain' => false, 'online' => false, 'knowledge' => false],
];
