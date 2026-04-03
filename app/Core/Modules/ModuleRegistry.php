<?php

namespace App\Core\Modules;

use App\Models\ModuleInstallation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ModuleRegistry
{
    public function all(): Collection
    {
        $installations = ModuleInstallation::query()->get()->keyBy('key');

        return collect(File::directories(base_path('modules')))
            ->map(fn (string $path): ?array => $this->readManifest($path))
            ->filter()
            ->map(function (array $payload) use ($installations): array {
                $manifest = ModuleManifest::fromArray($payload);
                $installation = $installations->get($manifest->key);

                return [
                    'key' => $manifest->key,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'description' => $manifest->description,
                    'website_types' => $manifest->websiteTypes,
                    'dependencies' => $manifest->dependencies,
                    'permissions' => $manifest->permissions,
                    'status' => $installation?->status ?? 'available',
                    'is_installed' => (bool) $installation,
                    'is_enabled' => $installation?->status === 'enabled',
                    'installed_at' => $installation?->installed_at,
                    'enabled_at' => $installation?->enabled_at,
                ];
            })
            ->values();
    }

    public function permissions(): array
    {
        return $this->all()
            ->flatMap(fn (array $module): array => Arr::get($module, 'permissions', []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function readManifest(string $modulePath): ?array
    {
        $manifestPath = $modulePath.DIRECTORY_SEPARATOR.'module.json';

        if (! File::exists($manifestPath)) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode(File::get($manifestPath), true);

        return $decoded;
    }
}
