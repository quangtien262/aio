<?php

namespace Modules\Pharmacy\Services\Integrations;

/** Only explicitly registered, contract-reviewed adapters may perform network I/O. */
interface PharmacyTransport
{
    /** Must use envelope.event_id as provider idempotency identity, no redirects, and
     * enforce connection.delivery_timeout_seconds (1..15s) including connect/retries. */
    public function send(array $connection, array $envelope, array $credentials): array;

    /** Lookup the same identity; must never blindly resubmit an uncertain operation. */
    public function reconcile(array $connection, array $envelope, array $credentials): array;
}
