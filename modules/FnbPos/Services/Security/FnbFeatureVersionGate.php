<?php

namespace Modules\FnbPos\Services\Security;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\ModuleInstallation;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\FnbPos\Security\FnbVersionedSecurity;
use RuntimeException;

final class FnbFeatureVersionGate
{
    public function __construct(
        private readonly ModuleCapabilityChecker $capabilities,
        private readonly FnbVersionedSecurity $security,
    ) {}

    public function allows(string $requiredVersion, string $featureFlag): bool
    {
        if (! $this->capabilities->enabled('fnb-pos')) {
            return false;
        }

        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->first();
        if ($installation === null || version_compare((string) $installation->version, $requiredVersion, '<')) {
            return false;
        }

        try {
            $feature = $this->security->definition((string) $installation->version)->features[$featureFlag] ?? null;
        } catch (RuntimeException) {
            return false;
        }

        return is_array($feature)
            && ($feature['enabled'] ?? false) === true
            && version_compare((string) $installation->version, (string) ($feature['introduced_version'] ?? $requiredVersion), '>=');
    }

    /** @throws AuthorizationException */
    public function authorize(string $requiredVersion, string $featureFlag): void
    {
        if (! $this->allows($requiredVersion, $featureFlag)) {
            throw new AuthorizationException('Tính năng F&B chưa khả dụng ở version/feature flag hiện tại.');
        }
    }
}
