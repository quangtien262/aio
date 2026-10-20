<?php

namespace Modules\Purchasing\Services;

use App\Core\Modules\Support\ModuleRuntimeBarrier;

class PurchasingRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'purchasing';

    protected const TABLE = 'purch_runtime_states';
}
