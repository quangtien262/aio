<?php

namespace Modules\RetailPos\Services;

use App\Core\Modules\Support\ModuleRuntimeBarrier;

final class RetailRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'retail-pos';

    protected const TABLE = 'retail_runtime_states';
}
