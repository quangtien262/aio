<?php

namespace App\Support\Database;

trait ResolvesModuleRollbackPaths
{
    protected function getMigrationPaths(): array
    {
        $paths = parent::getMigrationPaths();

        // Discover package files for DOWN only. The migrator still selects
        // executed migrations from its ledger; normal production UP remains
        // core-only and explicit --path retains its original scope.
        if ($this->input->getOption('path')) {
            return $paths;
        }

        return array_values(array_unique(array_merge(
            $paths,
            glob(base_path('modules/*/database/migrations'), GLOB_ONLYDIR) ?: [],
        )));
    }
}
