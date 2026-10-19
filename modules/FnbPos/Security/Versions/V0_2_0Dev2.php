<?php

namespace Modules\FnbPos\Security\Versions;

use App\Core\Modules\Support\ModuleSecurityDefinition;

final class V0_2_0Dev2
{
    public static function definition(): ModuleSecurityDefinition
    {
        $base = V0_2_0Dev1::definition();

        return new ModuleSecurityDefinition('0.2.0-dev.2', $base->permissions, $base->roles, $base->criticalPolicies,
            $base->features, $base->customRolePolicy, $base->assignmentChannel);
    }
}
