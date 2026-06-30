<?php

namespace App\Core\Cms;

use App\Models\SiteProfile;
use App\Models\CmsMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CmsMenuLocationRegistry
{
    public function all(): array
    {
        $siteProfile = SiteProfile::query()->first();
        $storedLocations = data_get($siteProfile?->branding, 'cms.menu_locations');

        if (is_array($storedLocations) && $storedLocations !== []) {
            $locations = collect($this->normalize($storedLocations))
                ->concat($this->defaultLocations())
                ->unique('value')
                ->values()
                ->all();

            return $this->mergeWithExistingMenuLocations($locations);
        }

        return $this->mergeWithExistingMenuLocations($this->defaultLocations());
    }

    public function values(): array
    {
        return array_values(array_map(fn (array $location): string => $location['value'], $this->all()));
    }

    public function save(array $locations): array
    {
        $normalized = $this->normalize($locations);
        $siteProfile = SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            [
                'website_type' => 'ecommerce',
                'active_theme_key' => null,
                'is_setup_completed' => false,
                'completed_steps' => [],
                'branding' => [],
            ],
        );

        $branding = $siteProfile->branding ?? [];
        data_set($branding, 'cms.menu_locations', $normalized);

        $siteProfile->forceFill(['branding' => $branding])->save();

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $locations
     * @return array<int, array{label: string, value: string}>
     */
    private function normalize(array $locations): array
    {
        return Collection::make($locations)
            ->map(function (mixed $location): ?array {
                if (! is_array($location)) {
                    return null;
                }

                $label = trim((string) ($location['label'] ?? ''));
                $value = trim((string) ($location['value'] ?? ''));

                if ($label === '' || $value === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'value' => $value,
                ];
            })
            ->filter()
            ->unique('value')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function defaultLocations(): array
    {
        $configured = config('cms.menu_locations', [
            ['label' => 'Primary', 'value' => 'primary'],
            ['label' => 'Footer', 'value' => 'footer'],
        ]);

        if (array_is_list($configured)) {
            return $this->normalize($configured);
        }

        return collect($configured)
            ->map(fn (string $label, string $value): array => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * Keep old/demo menu locations valid even when the editable registry was saved
     * before those locations existed.
     *
     * @param  array<int, array{label: string, value: string}>  $locations
     * @return array<int, array{label: string, value: string}>
     */
    private function mergeWithExistingMenuLocations(array $locations): array
    {
        if (! class_exists(CmsMenu::class) || ! Schema::hasTable('cms_menus')) {
            return $locations;
        }

        $existingValues = CmsMenu::query()
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location')
            ->filter()
            ->values();

        $next = collect($locations);

        foreach ($existingValues as $value) {
            if ($next->contains('value', $value)) {
                continue;
            }

            $next->push([
                'label' => str((string) $value)->replace('-', ' ')->title()->toString(),
                'value' => (string) $value,
            ]);
        }

        return $next->unique('value')->values()->all();
    }
}
