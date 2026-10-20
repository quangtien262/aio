<?php

namespace Modules\Customers\Events;

/** Durable delivery identity lets extensions deduplicate retries. No contact data is broadcast. */
readonly class CustomerChanged
{
    public function __construct(public string $eventId, public string $destination, public string $websiteKey, public string $profileId, public string $type, public int $version, public array $changedFields) {}
}
