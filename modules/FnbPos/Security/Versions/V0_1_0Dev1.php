<?php

namespace Modules\FnbPos\Security\Versions;

use App\Core\Modules\Support\ModuleSecurityDefinition;

final class V0_1_0Dev1
{
    public static function definition(): ModuleSecurityDefinition
    {
        // Explicit prerelease alias. It intentionally cannot float to a newer map.
        return V0_1_0::definition('0.1.0-dev.1');
    }
}
