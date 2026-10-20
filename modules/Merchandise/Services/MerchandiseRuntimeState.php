<?php

namespace Modules\Merchandise\Services;

use App\Core\Modules\Support\ModuleRuntimeBarrier;

class MerchandiseRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'merchandise';

    protected const TABLE = 'merch_runtime_states';
}
