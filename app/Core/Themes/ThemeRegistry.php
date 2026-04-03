<?php

namespace App\Core\Themes;

use App\Models\ThemeInstallation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ThemeRegistry
{
    public function all(): Collection
    {
        $installations = ThemeInstallation::query()->get()->keyBy('key');

        return collect(File::directories(base_path('themes')))
            ->map(fn (string $path): ?array => $this->readManifest($path))
            ->filter()
            ->map(function (array $payload) use ($installations): array {
                $manifest = ThemeManifest::fromArray($payload);
                $installation = $installations->get($manifest->key);

                return [
                    'key' => $manifest->key,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'description' => $manifest->description,
                    'website_type' => $manifest->websiteType,
                    'blocks' => $manifest->blocks,
                    'parent' => $manifest->parent,
                    'preview' => $manifest->preview,
                    'supports' => $manifest->supports,
                    'demo' => $manifest->demo,
                    'status' => $installation?->status ?? 'available',
                    'is_installed' => (bool) $installation,
                    'is_active' => (bool) $installation?->is_active,
                    'installed_at' => $installation?->installed_at,
                    'activated_at' => $installation?->activated_at,
                ];
            })
            ->values();
    }

    private function readManifest(string $themePath): ?array
    {
        $manifestPath = $themePath.DIRECTORY_SEPARATOR.'theme.json';

        if (! File::exists($manifestPath)) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode(File::get($manifestPath), true);

        return $decoded;
    }
}
