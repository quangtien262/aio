<?php

namespace Modules\FnbPos\Security;

use App\Core\Modules\Contracts\VersionedModuleSecurityProvider;
use App\Core\Modules\Support\ModuleSecurityDefinition;
use Modules\FnbPos\Security\Versions\V0_1_0;
use Modules\FnbPos\Security\Versions\V0_1_0Dev1;
use RuntimeException;

final class FnbVersionedSecurity implements VersionedModuleSecurityProvider
{
    public function definition(string $version): ModuleSecurityDefinition
    {
        return match ($version) {
            '0.1.0-dev.1' => V0_1_0Dev1::definition(),
            '0.1.0' => V0_1_0::definition(),
            default => throw new RuntimeException("No immutable F&B security definition exists for version [{$version}]."),
        };
    }
}
