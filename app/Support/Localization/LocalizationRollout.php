<?php

namespace App\Support\Localization;

class LocalizationRollout
{
    /**
     * @return array{enabled: bool, reason: string, stage: string}
     */
    public function readerDecision(
        string $resourceType,
        ?string $websiteKey = null,
        ?string $themeKey = null,
    ): array {
        $stage = strtolower(trim((string) config(
            "localized-content.rollout.stages.{$resourceType}",
            'all',
        )));

        if ((string) config('localized-content.rollout.reader', 'new') !== 'new') {
            return [
                'enabled' => false,
                'reason' => 'global_reader',
                'stage' => $stage,
            ];
        }

        if (! (bool) config("localized-content.rollout.modules.{$resourceType}", false)) {
            return [
                'enabled' => false,
                'reason' => 'module_disabled',
                'stage' => $stage,
            ];
        }

        $resourceOverrides = (array) config(
            "localized-content.rollout.overrides.{$resourceType}",
            [],
        );
        $websiteOverride = $this->override(
            (array) ($resourceOverrides['websites'] ?? []),
            $websiteKey,
        );
        $websiteOverride ??= $this->override(
            (array) config('localized-content.rollout.websites', []),
            $websiteKey,
        );

        // A website override is the emergency switch for every theme served by
        // that website, so it deliberately takes precedence over theme flags.
        if ($websiteOverride !== null) {
            return [
                'enabled' => $websiteOverride,
                'reason' => 'website_override',
                'stage' => $stage,
            ];
        }

        $themeOverride = $this->override(
            (array) ($resourceOverrides['themes'] ?? []),
            $themeKey,
            true,
        );
        $themeOverride ??= $this->override(
            (array) config('localized-content.rollout.themes', []),
            $themeKey,
            true,
        );

        if ($themeOverride !== null) {
            return [
                'enabled' => $themeOverride,
                'reason' => 'theme_override',
                'stage' => $stage,
            ];
        }

        if ($stage === 'canary') {
            $canaries = (array) config(
                "localized-content.rollout.canaries.{$resourceType}",
                [],
            );
            $isCanary = (
                $this->contains(
                    (array) ($canaries['websites'] ?? []),
                    $websiteKey,
                )
                || $this->contains(
                    (array) ($canaries['themes'] ?? []),
                    $themeKey,
                    true,
                )
            );

            return [
                'enabled' => $isCanary,
                'reason' => $isCanary ? 'canary_match' : 'canary_miss',
                'stage' => $stage,
            ];
        }

        if (in_array($stage, ['legacy', 'off', 'disabled'], true)) {
            return [
                'enabled' => false,
                'reason' => 'stage_legacy',
                'stage' => $stage,
            ];
        }

        return [
            'enabled' => in_array($stage, ['all', 'new', 'enabled'], true),
            'reason' => in_array($stage, ['all', 'new', 'enabled'], true)
                ? 'stage_all'
                : 'invalid_stage',
            'stage' => $stage,
        ];
    }

    public function usesNewReader(
        string $resourceType,
        ?string $websiteKey = null,
        ?string $themeKey = null,
    ): bool {
        return $this->readerDecision(
            $resourceType,
            $websiteKey,
            $themeKey,
        )['enabled'];
    }

    public function dualWriteEnabled(): bool
    {
        return (bool) config('localized-content.rollout.dual_write', true);
    }

    public function legacyFallbackEnabled(): bool
    {
        return (bool) config('localized-content.rollout.legacy_fallback', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function override(
        array $overrides,
        ?string $key,
        bool $uppercase = false,
    ): ?bool {
        if ($key === null || trim($key) === '') {
            return null;
        }

        $needle = $this->normalize($key, $uppercase);

        foreach ($overrides as $candidate => $enabled) {
            if ($this->normalize((string) $candidate, $uppercase) === $needle) {
                return (bool) $enabled;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function contains(
        array $values,
        ?string $key,
        bool $uppercase = false,
    ): bool {
        if ($key === null || trim($key) === '') {
            return false;
        }

        $needle = $this->normalize($key, $uppercase);

        foreach ($values as $value) {
            if ($this->normalize((string) $value, $uppercase) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value, bool $uppercase): string
    {
        $value = trim($value);

        return $uppercase ? strtoupper($value) : strtolower($value);
    }
}
