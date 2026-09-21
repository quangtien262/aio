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

        if (str_starts_with($key, 'XD')) {
            $definitions = XdCompleteDemoContentProvider::definitions();
            if (isset($definitions[$key])) {
                return new XdCompleteDemoContentProvider($key, $definitions[$key]);
            }
        }

        foreach ($this->providers as $provider) {
            if ($provider->themeKey() === $key) {
                return $provider;
            }
        }

        return null;
    }
}
