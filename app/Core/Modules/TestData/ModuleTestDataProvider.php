<?php

namespace App\Core\Modules\TestData;

interface ModuleTestDataProvider
{
    /** @return list<string> Human-readable fixture contents, without running writes. */
    public function preview(): array;

    /** Insert new, isolated fixtures; every inserted row must be recorded through the context. */
    public function create(ModuleTestDataContext $context): void;
}
