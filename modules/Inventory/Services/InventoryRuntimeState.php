<?php

namespace Modules\Inventory\Services;

use App\Core\Modules\Support\ModuleRuntimeBarrier;

final class InventoryRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'inventory';

    protected const TABLE = 'inv_runtime_states';
}
