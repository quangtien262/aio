<?php

namespace Modules\FnbPos\Services\Security;

final readonly class FnbAuthoritySnapshot
{
    public function __construct(
        public string $hash,
        public int $revision,
        public string $moduleVersion,
    ) {}
}
