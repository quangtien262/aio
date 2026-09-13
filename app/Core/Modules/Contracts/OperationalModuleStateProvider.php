<?php

namespace App\Core\Modules\Contracts;

use App\Core\Modules\Support\ModuleLifecycleLease;

/**
 * Optional module hook for lifecycle state which must participate in the
 * platform lifecycle protocol, rather than in best-effort post hooks.
 */
interface OperationalModuleStateProvider
{
    /** Prepare/drain module-owned traffic before pre-hooks or migrations. */
    public function prepare(ModuleLifecycleLease $lease): void;

    /** Apply the successful state transition inside the core commit transaction. */
    public function commit(ModuleLifecycleLease $lease): void;

    /** Restore a prepared state when an operation can safely terminate as failed. */
    public function compensate(ModuleLifecycleLease $lease): void;
}
