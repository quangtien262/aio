<?php

namespace Modules\FnbPos\Http;

/** A final boundary for operational responses; full customer reads have their own permission-gated controller. */
final class FnbPublicProjection
{
    private const INTERNAL_FIELDS = [
        'customer_snapshot', 'buyer_snapshot', 'billing_snapshot', 'recipe_snapshot', 'recipe_snapshot_hash',
        'request_fingerprint', 'request_hash', 'idempotency_key', 'owner_token', 'metadata',
        'provider_connection_key', 'provider_operation_key', 'provider_reference', 'provider_trace_reference', 'reference',
        'token_hash', 'qr_token_hash', 'device_uid', 'proof_hash',
    ];

    public static function operational(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, self::INTERNAL_FIELDS, true)) {
                continue;
            }
            $result[$key] = self::operational($item);
        }

        return $result;
    }
}
