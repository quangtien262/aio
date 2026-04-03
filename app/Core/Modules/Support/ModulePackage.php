<?php

namespace App\Core\Modules\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

readonly class ModulePackage
{
    /**
     * @param  array<int, string>  $migrationPaths
     * @param  array<int, string>  $seeders
     * @param  array<int, string>  $configFiles
     * @param  array<int, string>  $assetPaths
     */
    public function __construct(
        public string $key,
        public string $basePath,
        public array $migrationPaths,
        public array $seeders,
        public array $configFiles,
        public array $assetPaths,
    ) {
    }

    public static function fromModule(array $module): self
    {
        $basePath = $module['path'];
        $package = $module['package'] ?? [];

        return new self(
            key: $module['key'],
            basePath: $basePath,
            migrationPaths: self::resolvePaths($basePath, Arr::get($package, 'migrations', [])),
            seeders: array_values(Arr::get($package, 'seeders', [])),
            configFiles: self::resolvePaths($basePath, Arr::get($package, 'config', [])),
            assetPaths: self::resolvePaths($basePath, Arr::get($package, 'assets', [])),
        );
    }

    public function configDestination(string $sourcePath): string
    {
        return config_path(pathinfo($sourcePath, PATHINFO_BASENAME));
    }

    public function assetDestination(string $sourcePath): string
    {
        $basename = pathinfo($sourcePath, PATHINFO_BASENAME);

        if ($basename === 'public') {
            return public_path('modules/'.Str::slug($this->key));
        }

        return public_path('modules/'.Str::slug($this->key).'/'.$basename);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private static function resolvePaths(string $basePath, array $paths): array
    {
        return collect($paths)
            ->map(fn (string $path): string => str_starts_with($path, $basePath) ? $path : $basePath.DIRECTORY_SEPARATOR.$path)
            ->values()
            ->all();
    }
}
