<?php

namespace App\Core\Modules\Support;

/** Package paths are explicit; production UP still runs only through lifecycle. */
final class ModuleMigrationPaths
{
    public static function all(bool $includeHistorical = false): array
    {
        $paths = [];
        foreach (glob(base_path('modules/*/module.json')) ?: [] as $manifestPath) {
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $package = $manifest['package'] ?? [];
            $relativePaths = $package['migrations'] ?? ['database/migrations'];
            if ($includeHistorical) {
                $relativePaths = array_merge($relativePaths, $package['historical_migrations'] ?? []);
            }
            foreach ($relativePaths as $relative) {
                $path = dirname($manifestPath).'/'.$relative;
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
