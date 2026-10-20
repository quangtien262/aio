<?php

namespace Modules\Customers\Services;

use App\Core\Modules\Support\ModuleRuntimeBarrier;

class CustomerRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'customers';

    protected const TABLE = 'cust_runtime_states';
}
