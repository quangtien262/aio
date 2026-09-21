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

        $remaining = json_decode(file_get_contents(resource_path('demo/remaining-themes.json')), true, 512, JSON_THROW_ON_ERROR);
        if (isset($remaining[$key])) {
            return new IndustryDemoContentProvider($key === 'CORPORATE-STARTER' ? 'corporate-starter' : $key, $remaining[$key]);
        }
        if ($key === 'BDS702') {
            return app(Bds702DemoContentProvider::class);
        }

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
