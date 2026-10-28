<?php

namespace Modules\Inventory\Contracts;

/** A native owner must authorize the live actor and business intent, including replay. */
interface ManagedStockPolicy
{
    public function authorize(int $actorId, object $warehouse, string $operation, array $context): void;
}
