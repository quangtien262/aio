<?php

namespace App\Support\Localization;

class LocalizationRollout
{
    public function usesNewReader(
        string $resourceType,
        ?string $websiteKey = null,
        ?string $themeKey = null,
    ): bool {
        if ((string) config('localized-content.rollout.reader', 'new') !== 'new') {
            return false;
        }

        if (! (bool) config("localized-content.rollout.modules.{$resourceType}", false)) {
            return false;
        }

        $websiteOverrides = (array) config('localized-content.rollout.websites', []);

        if ($websiteKey !== null && array_key_exists($websiteKey, $websiteOverrides)) {
            return (bool) $websiteOverrides[$websiteKey];
        }

        if ($themeKey !== null) {
            $themeOverrides = (array) config('localized-content.rollout.themes', []);
            $normalizedTheme = strtoupper($themeKey);

            if (array_key_exists($normalizedTheme, $themeOverrides)) {
                return (bool) $themeOverrides[$normalizedTheme];
            }
        }

        return true;
    }

    public function dualWriteEnabled(): bool
    {
        return (bool) config('localized-content.rollout.dual_write', true);
    }

    public function legacyFallbackEnabled(): bool
    {
        return (bool) config('localized-content.rollout.legacy_fallback', true);
    }
}
