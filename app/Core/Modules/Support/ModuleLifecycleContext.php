<?php

namespace App\Core\Modules\Support;

use App\Models\ModuleInstallation;

readonly class ModuleLifecycleContext
{
    public function __construct(
        public string $operation,
        public array $module,
        public ?ModuleInstallation $installation = null,
        public ?string $fromVersion = null,
    ) {
    }

    public static function forOperation(string $operation, array $module, ?ModuleInstallation $installation = null, ?string $fromVersion = null): self
    {
        return new self($operation, $module, $installation, $fromVersion);
    }

    public function withInstallation(?ModuleInstallation $installation): self
    {
        return new self($this->operation, $this->module, $installation, $this->fromVersion);
    }
}
