<?php

namespace Modules\FnbPos\Security\Versions;

use App\Core\Modules\Support\ModuleSecurityDefinition;

final class V0_2_0Dev3
{
    public static function definition(): ModuleSecurityDefinition
    {
        $base = V0_2_0Dev2::definition();

        return new ModuleSecurityDefinition('0.2.0-dev.3', $base->permissions, $base->roles, $base->criticalPolicies,
            $base->features, $base->customRolePolicy, $base->assignmentChannel);
    }
}
