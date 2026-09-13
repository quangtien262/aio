<?php

namespace App\Core\Modules\Support;

readonly class ModuleLifecycleLease
{
    public function __construct(
        public string $operationId,
        public string $ownerToken,
        public string $moduleKey,
        public string $operation,
        public ?string $fromVersion,
        public ?string $targetVersion,
        public bool $resumed = false,
    ) {}
}
