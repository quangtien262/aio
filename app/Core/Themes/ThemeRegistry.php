<?php

namespace App\Core\Themes;

use App\Models\ThemeDemoRecord;
use App\Models\ThemeInstallation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

class ThemeRegistry
{
    public function all(): Collection
    {
        $installations = ThemeInstallation::query()->get()->keyBy('key');
        $demoCounts = ThemeDemoRecord::query()
            ->selectRaw('theme_key, COUNT(*) as aggregate_count')
            ->groupBy('theme_key')
            ->pluck('aggregate_count', 'theme_key');

        return collect(File::directories(base_path('themes')))
            ->map(fn (string $path): ?array => $this->readManifest($path))
            ->filter()
            ->map(function (array $payload) use ($installations, $demoCounts): array {
                $manifest = ThemeManifest::fromArray($payload);
                $installation = $installations->get($manifest->key);
                $demoRecordCount = (int) ($demoCounts[$manifest->key] ?? 0);

                return [
                    'key' => $manifest->key,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'description' => $manifest->description,
                    'website_type' => $manifest->websiteType,
                    'blocks' => $manifest->blocks,
                    'parent' => $manifest->parent,
                    'preview' => $manifest->preview,
                    'preview_urls' => $this->resolvePreviewUrls($manifest->key, $manifest->preview),
                    'supports' => $manifest->supports,
                    'demo' => $manifest->demo,
                    'localization' => [
                        'default_locale' => $manifest->localization['default_locale'] ?? config('localization.default_locale', 'vi'),
                        'supported_locales' => array_values($manifest->localization['supported_locales'] ?? [config('localization.default_locale', 'vi')]),
                    ],
                    'status' => $installation?->status ?? 'available',
                    'is_installed' => (bool) $installation,
                    'is_active' => (bool) $installation?->is_active,
                    'installed_at' => $installation?->installed_at,
                    'activated_at' => $installation?->activated_at,
                    'has_demo_data' => $demoRecordCount > 0,
                    'demo_record_count' => $demoRecordCount,
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

    /**
     * @param  array{thumbnail?:string,cover?:string}  $preview
     * @return array{thumbnail:?string,cover:?string}
     */
    private function resolvePreviewUrls(string $themeKey, array $preview): array
    {
        return [
            'thumbnail' => $this->resolvePreviewUrl($themeKey, $preview['thumbnail'] ?? null),
            'cover' => $this->resolvePreviewUrl($themeKey, $preview['cover'] ?? null),
        ];
    }

    private function resolvePreviewUrl(string $themeKey, ?string $fileName): ?string
    {
        if (! is_string($fileName) || trim($fileName) === '') {
            return null;
        }

        $relativePath = 'theme-previews/'.$themeKey.'/'.$fileName;
        $absolutePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if (! File::exists($absolutePath)) {
            return null;
        }

        return URL::to($relativePath);
    }
}
