<?php

namespace App\Core\Themes\Demo;

class ThemeDemoContentProviderRegistry
{
    /** @param iterable<ThemeDemoContentProvider> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function forTheme(string $themeKey): ?ThemeDemoContentProvider
    {
        $key = strtoupper(trim($themeKey));

        foreach ($this->providers as $provider) {
            if ($provider->themeKey() === $key) {
                return $provider;
            }
        }

        return null;
    }
}
