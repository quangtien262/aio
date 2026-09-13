<?php

namespace App\Core\Modules\Contracts;

use App\Core\Modules\Support\ModuleSecurityDefinition;

interface VersionedModuleSecurityProvider
{
    /**
     * Return the immutable security definition for exactly this installed
     * version. Implementations must reject unknown versions.
     */
    public function definition(string $version): ModuleSecurityDefinition;
}
