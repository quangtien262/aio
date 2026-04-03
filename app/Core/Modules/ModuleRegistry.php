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

        $modules = collect(File::directories(base_path('modules')))
            ->map(fn (string $path): ?array => $this->readManifest($path))
            ->filter()
            ->mapWithKeys(function (array $payload) use ($installations): array {
                $manifest = ModuleManifest::fromArray($payload);
                $installation = $installations->get($manifest->key);
                $status = $installation?->status ?? 'available';
                $latestVersion = $manifest->version;
                $installedVersion = $this->isInstalledStatus($status) ? ($installation?->version ?? $latestVersion) : null;

                return [$manifest->key => [
                    'key' => $manifest->key,
                    'path' => $payload['__path'],
                    'name' => $manifest->name,
                    'version' => $latestVersion,
                    'latest_version' => $latestVersion,
                    'installed_version' => $installedVersion,
                    'description' => $manifest->description,
                    'website_types' => $manifest->websiteTypes,
                    'dependencies' => $manifest->dependencies,
                    'permissions' => $manifest->permissions,
                    'hooks' => $manifest->hooks,
                    'menus' => $this->normalizeMenus($manifest->menus, $manifest->key),
                    'changelog' => $this->normalizeChangelog($manifest->changelog),
                    'package' => $this->normalizePackage($manifest->package),
                    'lifecycle' => $this->normalizeLifecycle($manifest->lifecycle),
                    'status' => $status,
                    'is_installed' => $this->isInstalledStatus($status),
                    'is_enabled' => $status === 'enabled',
                    'upgrade_available' => $installedVersion !== null && version_compare($latestVersion, $installedVersion, '>'),
                    'installed_at' => $installation?->installed_at,
                    'enabled_at' => $installation?->enabled_at,
                    'last_upgraded_at' => $installation?->last_upgraded_at,
                ]];
            });

        return $modules
            ->map(fn (array $module): array => $this->enrichModule($module, $modules))
            ->values();
    }

    public function find(string $key): ?array
    {
        return $this->all()->firstWhere('key', $key);
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

    public function navigationForPermissions(array $permissions): array
    {
        return $this->all()
            ->where('status', 'enabled')
            ->flatMap(function (array $module) use ($permissions): array {
                return collect($module['menus'] ?? [])
                    ->filter(fn (array $menu): bool => empty($menu['permission']) || in_array($menu['permission'], $permissions, true))
                    ->map(fn (array $menu): array => [
                        ...$menu,
                        'module_key' => $module['key'],
                        'source' => 'module',
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    private function enrichModule(array $module, Collection $modules): array
    {
        $dependencies = collect($module['dependencies'] ?? [])
            ->map(function (string $dependencyKey) use ($modules): array {
                $dependency = $modules->get($dependencyKey);

                return [
                    'key' => $dependencyKey,
                    'name' => $dependency['name'] ?? $dependencyKey,
                    'exists' => $dependency !== null,
                    'status' => $dependency['status'] ?? 'missing',
                    'is_installed' => $dependency['is_installed'] ?? false,
                    'is_enabled' => $dependency['is_enabled'] ?? false,
                ];
            })
            ->values()
            ->all();

        $dependents = $modules
            ->filter(fn (array $candidate): bool => in_array($module['key'], $candidate['dependencies'] ?? [], true))
            ->map(fn (array $candidate): array => [
                'key' => $candidate['key'],
                'name' => $candidate['name'],
                'status' => $candidate['status'],
                'is_installed' => $candidate['is_installed'],
                'is_enabled' => $candidate['is_enabled'],
            ])
            ->values()
            ->all();

        $blockers = $this->buildBlockers($module, $dependencies, $dependents);

        return [
            ...$module,
            'dependency_statuses' => $dependencies,
            'dependents' => $dependents,
            'blockers' => $blockers,
            'available_actions' => [
                'install' => empty($blockers['install']),
                'enable' => empty($blockers['enable']),
                'disable' => empty($blockers['disable']),
                'upgrade' => empty($blockers['upgrade']),
                'uninstall' => empty($blockers['uninstall']),
            ],
        ];
    }

    private function buildBlockers(array $module, array $dependencies, array $dependents): array
    {
        $lifecycle = $module['lifecycle'] ?? [];

        $installBlockers = [];
        $enableBlockers = [];
        $disableBlockers = [];
        $upgradeBlockers = [];
        $uninstallBlockers = [];

        if (($lifecycle['install'] ?? false) !== true) {
            $installBlockers[] = 'Manifest không cho phép cài đặt module này.';
        }

        if (($lifecycle['enable'] ?? false) !== true) {
            $enableBlockers[] = 'Manifest không cho phép bật module này.';
        }

        if (($lifecycle['disable'] ?? false) !== true) {
            $disableBlockers[] = 'Manifest không cho phép tắt module này.';
        }

        if (($lifecycle['upgrade'] ?? false) !== true) {
            $upgradeBlockers[] = 'Manifest không cho phép nâng cấp module này.';
        }

        if (($lifecycle['uninstall'] ?? false) !== true) {
            $uninstallBlockers[] = 'Manifest không cho phép gỡ module này.';
        }

        if ($module['is_installed']) {
            $installBlockers[] = 'Module đã được cài đặt.';
        }

        if (! $module['is_installed']) {
            $enableBlockers[] = 'Cần cài đặt module trước khi bật.';
            $upgradeBlockers[] = 'Cần cài đặt module trước khi nâng cấp.';
            $uninstallBlockers[] = 'Module chưa được cài đặt.';
        }

        if ($module['status'] === 'enabled') {
            $enableBlockers[] = 'Module đang được bật.';
            $uninstallBlockers[] = 'Cần tắt module trước khi gỡ.';
        }

        if ($module['status'] !== 'enabled') {
            $disableBlockers[] = 'Module chưa được bật.';
        }

        if (($module['upgrade_available'] ?? false) !== true) {
            $upgradeBlockers[] = 'Module đang ở phiên bản mới nhất.';
        }

        foreach ($dependencies as $dependency) {
            if (! $dependency['exists']) {
                $installBlockers[] = "Không tìm thấy module phụ thuộc {$dependency['key']} trong source.";
                $enableBlockers[] = "Không tìm thấy module phụ thuộc {$dependency['key']} trong source.";
                $upgradeBlockers[] = "Không tìm thấy module phụ thuộc {$dependency['key']} trong source.";
                continue;
            }

            if (! $dependency['is_installed']) {
                $installBlockers[] = "Cần cài đặt module phụ thuộc {$dependency['name']} trước.";
                $enableBlockers[] = "Cần cài đặt module phụ thuộc {$dependency['name']} trước.";
                $upgradeBlockers[] = "Cần cài đặt module phụ thuộc {$dependency['name']} trước khi nâng cấp.";
            }

            if (! $dependency['is_enabled']) {
                $enableBlockers[] = "Cần bật module phụ thuộc {$dependency['name']} trước.";
                $upgradeBlockers[] = "Cần bật module phụ thuộc {$dependency['name']} trước khi nâng cấp.";
            }
        }

        foreach ($dependents as $dependent) {
            if ($dependent['is_enabled']) {
                $disableBlockers[] = "Không thể tắt vì module {$dependent['name']} đang phụ thuộc và đang bật.";
            }

            if ($dependent['is_installed']) {
                $uninstallBlockers[] = "Không thể gỡ vì module {$dependent['name']} đang phụ thuộc.";
            }
        }

        return [
            'install' => array_values(array_unique($installBlockers)),
            'enable' => array_values(array_unique($enableBlockers)),
            'disable' => array_values(array_unique($disableBlockers)),
            'upgrade' => array_values(array_unique($upgradeBlockers)),
            'uninstall' => array_values(array_unique($uninstallBlockers)),
        ];
    }

    private function normalizeLifecycle(array $lifecycle): array
    {
        return [
            'install' => $lifecycle['install'] ?? true,
            'enable' => $lifecycle['enable'] ?? true,
            'disable' => $lifecycle['disable'] ?? true,
            'upgrade' => $lifecycle['upgrade'] ?? true,
            'uninstall' => $lifecycle['uninstall'] ?? true,
        ];
    }

    private function normalizeMenus(array $menus, string $moduleKey): array
    {
        return collect($menus)
            ->map(function (array $menu, int $index) use ($moduleKey): array {
                return [
                    'key' => $menu['key'] ?? "{$moduleKey}-{$index}",
                    'label' => $menu['label'] ?? ucfirst($moduleKey),
                    'description' => $menu['description'] ?? 'Menu module được đồng bộ từ manifest.',
                    'badge' => $menu['badge'] ?? 'Module',
                    'color' => $menu['color'] ?? 'geekblue',
                    'icon' => $menu['icon'] ?? 'appstore',
                    'route' => $menu['route'] ?? "/admin/modules/{$moduleKey}",
                    'permission' => $menu['permission'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeChangelog(array $changelog): array
    {
        return collect($changelog)
            ->filter(fn (array $entry): bool => ! empty($entry['version']))
            ->sortByDesc(fn (array $entry): string => $entry['version'])
            ->map(fn (array $entry): array => [
                'version' => $entry['version'],
                'date' => $entry['date'] ?? null,
                'notes' => array_values($entry['notes'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function normalizePackage(array $package): array
    {
        return [
            'migrations' => array_values($package['migrations'] ?? ['database/migrations']),
            'seeders' => array_values($package['seeders'] ?? []),
            'config' => array_values($package['config'] ?? []),
            'assets' => array_values($package['assets'] ?? ['public']),
        ];
    }

    private function isInstalledStatus(string $status): bool
    {
        return in_array($status, ['installed', 'enabled', 'disabled', 'upgrade_pending'], true);
    }

    private function readManifest(string $modulePath): ?array
    {
        $manifestPath = $modulePath.DIRECTORY_SEPARATOR.'module.json';

        if (! File::exists($manifestPath)) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode(File::get($manifestPath), true);

        if ($decoded === null) {
            return null;
        }

        return [
            ...$decoded,
            '__path' => $modulePath,
        ];
    }
}
