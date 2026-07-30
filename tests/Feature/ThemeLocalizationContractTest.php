<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ThemeLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_theme_satisfies_the_localization_contract_by_rollout_group(): void
    {
        $themes = collect(File::directories(base_path('themes')))
            ->map(function (string $directory): ?array {
                $manifestPath = $directory.DIRECTORY_SEPARATOR.'theme.json';

                if (! File::exists($manifestPath)) {
                    return null;
                }

                $manifest = json_decode((string) File::get($manifestPath), true);

                return is_array($manifest)
                    ? ['directory' => $directory, 'manifest' => $manifest]
                    : null;
            })
            ->filter()
            ->values();
        $groups = [
            'canary' => ['BOOK920', 'DN302', 'BDS701'],
            'xd' => $themes->pluck('manifest.key')->filter(fn (string $key): bool => str_starts_with($key, 'XD'))->all(),
            'ec' => $themes->pluck('manifest.key')->filter(fn (string $key): bool => str_starts_with($key, 'EC'))->all(),
            'shop_nt' => $themes->pluck('manifest.key')->filter(fn (string $key): bool => (
                str_starts_with($key, 'SHOP') || str_starts_with($key, 'NT')
            ))->all(),
            'ser_dn' => $themes->pluck('manifest.key')->filter(fn (string $key): bool => (
                str_starts_with($key, 'SER') || str_starts_with($key, 'DN')
            ))->all(),
        ];
        $groupedKeys = collect($groups)->flatten()->unique();
        $groups['remaining'] = $themes->pluck('manifest.key')->diff($groupedKeys)->all();

        foreach ($groups as $group => $keys) {
            foreach (array_unique($keys) as $key) {
                $theme = $themes->first(fn (array $item): bool => (
                    (string) data_get($item, 'manifest.key') === $key
                ));
                $this->assertNotNull($theme, "{$group}: missing theme {$key}");
                $this->assertThemeContract($theme['directory'], $theme['manifest'], $group);
            }
        }

        $this->assertEmpty(
            $themes->pluck('manifest.key')->diff(collect($groups)->flatten()->unique())->all(),
            'Every registered theme must belong to a localization rollout group.',
        );
    }

    public function test_canary_theme_translation_reader_supports_fallback_for_new_locales(): void
    {
        $service = app(ThemeTranslationService::class);

        foreach (['BOOK920', 'DN302', 'BDS701'] as $themeKey) {
            $vi = $service->translations($themeKey, 'vi');
            $en = $service->translations($themeKey, 'en-US');

            $this->assertNotEmpty($vi, "{$themeKey} must expose source strings.");
            $this->assertNotEmpty($en, "{$themeKey} must resolve an inherited locale.");
        }
    }

    public function test_theme_catalogs_and_views_have_no_repairable_legacy_encoding(): void
    {
        foreach ([
            'repair-theme-translation-encoding.php',
            'repair-theme-view-encoding.php',
        ] as $script) {
            $process = new Process([PHP_BINARY, base_path('scripts/'.$script)]);
            $process->setTimeout(120);
            $process->mustRun();
            $report = json_decode($process->getOutput(), true);

            $this->assertIsArray($report, "{$script}: invalid audit output.");
            $this->assertSame(0, $report['files'] ?? null, "{$script}: repairable mojibake remains.");
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertThemeContract(
        string $directory,
        array $manifest,
        string $group,
    ): void {
        $key = (string) ($manifest['key'] ?? basename($directory));
        $locales = (array) data_get($manifest, 'localization.supported_locales', []);
        $catalogs = [];

        $this->assertContains('vi', $locales, "{$group}/{$key}: source locale is required.");
        $this->assertContains('en', $locales, "{$group}/{$key}: English locale is required.");

        foreach ($locales as $locale) {
            $path = $directory.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.$locale.'.json';
            $this->assertFileExists($path, "{$group}/{$key}: missing {$locale}.json");
            $decoded = json_decode((string) File::get($path), true);
            $this->assertIsArray($decoded, "{$group}/{$key}: invalid {$locale}.json");
            $this->assertNotEmpty($decoded, "{$group}/{$key}: empty {$locale}.json");
            $catalogs[$locale] = $decoded;
        }

        foreach ($catalogs as $locale => $catalog) {
            $this->assertEqualsCanonicalizing(
                array_keys($catalogs['vi']),
                array_keys($catalog),
                "{$group}/{$key}: {$locale}.json must keep the same key contract as vi.json.",
            );
        }

        $viewDirectory = $directory.DIRECTORY_SEPARATOR.'views';

        if (! File::isDirectory($viewDirectory)) {
            return;
        }

        foreach (File::allFiles($viewDirectory) as $view) {
            if ($view->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) File::get($view->getPathname());
            $this->assertDoesNotMatchRegularExpression(
                "/app\\(\\)->getLocale\\(\\)\\s*={2,3}\\s*'en'|\\\$isEnglish\\s*\\?/",
                $contents,
                "{$group}/{$key}: locale-specific conditionals must use the theme translation catalog.",
            );

            if (! preg_match('/<head(?:\s|>)/i', $contents)) {
                continue;
            }

            $this->assertStringContainsString(
                "partials.localized-seo",
                $contents,
                "{$group}/{$key}: every HTML document must expose canonical/hreflang metadata.",
            );
        }
    }
}
