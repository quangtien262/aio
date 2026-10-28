<?php

namespace Modules\Pharmacy\Security;

use App\Core\Modules\Contracts\VersionedModuleSecurityProvider;
use App\Core\Modules\Support\ModuleSecurityDefinition;
use Modules\Pharmacy\Security\Versions\V0_1_0Dev1;

final class PharmacyVersionedSecurity implements VersionedModuleSecurityProvider
{
    public function definition(string $version): ModuleSecurityDefinition
    {
        return match ($version) {
            '0.1.0-dev.1' => V0_1_0Dev1::definition(),
            default => throw new \RuntimeException('Không có định nghĩa security bất biến cho phiên bản Pharmacy '.$version),
        };
    }
}
