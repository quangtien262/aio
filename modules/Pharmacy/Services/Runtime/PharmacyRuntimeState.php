<?php

namespace Modules\Pharmacy\Services\Runtime;

use App\Core\Modules\Support\ModuleRuntimeBarrier;

final class PharmacyRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'pharmacy';

    protected const TABLE = 'ph_runtime_states';
}
