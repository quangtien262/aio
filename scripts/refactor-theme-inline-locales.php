<?php

declare(strict_types=1);

/**
 * Moves legacy two-locale Blade ternaries into each theme's JSON translation
 * catalog and replaces them with ThemeTranslationService lookups.
 *
 * Run without --write to preview coverage. The transformation is idempotent.
 */

$write = in_array('--write', $argv, true);
$themeRoot = dirname(__DIR__).DIRECTORY_SEPARATOR.'themes';
$pattern = <<<'REGEX'
/(?:app\(\)->getLocale\(\)\s*={2,3}\s*'en'|\$isEnglish)\s*\?\s*'((?:\\.|[^'\\])*)'\s*:\s*'((?:\\.|[^'\\])*)'/
REGEX;
$summary = [
    'themes' => 0,
    'files' => 0,
    'replacements' => 0,
    'skipped_invalid_catalogs' => [],
];

/**
 * PHP single-quoted literals only interpret \\ and \'.
 */
$decodeLiteral = static fn (string $value): string => str_replace(
    ['\\\\', "\\'"],
    ['\\', "'"],
    $value,
);
$encodeLiteral = static fn (string $value): string => str_replace(
    ['\\', "'"],
    ['\\\\', "\\'"],
    $value,
);

foreach (new DirectoryIterator($themeRoot) as $themeDirectory) {
    if (! $themeDirectory->isDir() || $themeDirectory->isDot()) {
        continue;
    }

    $themeKey = $themeDirectory->getFilename();
    $viewsPath = $themeDirectory->getPathname().DIRECTORY_SEPARATOR.'views';

    if (! is_dir($viewsPath)) {
        continue;
    }

    $catalogs = [];
    $themeFiles = [];
    $themeReplacements = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewsPath, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $path = $file->getPathname();
        $contents = file_get_contents($path);

        if (! is_string($contents) || ! preg_match($pattern, $contents)) {
            continue;
        }

        $replacements = 0;
        $updated = preg_replace_callback(
            $pattern,
            static function (array $matches) use (
                &$catalogs,
                &$replacements,
                $decodeLiteral,
                $encodeLiteral,
                $themeKey,
            ): string {
                $english = $decodeLiteral($matches[1]);
                $source = $decodeLiteral($matches[2]);
                $key = 'legacy_inline.'.substr(sha1($english."\0".$source), 0, 16);
                $catalogs['en'][$key] = $english;
                $catalogs['vi'][$key] = $source;
                $replacements++;

                return sprintf(
                    "app(\\App\\Core\\Themes\\ThemeTranslationService::class)->bladeText('%s', app()->getLocale(), '%s', '%s')",
                    $encodeLiteral($themeKey),
                    $key,
                    $encodeLiteral($source),
                );
            },
            $contents,
        );

        if (! is_string($updated) || $replacements === 0) {
            continue;
        }

        if (! preg_match('/\$isEnglish\s*\?/', $updated)) {
            $updated = preg_replace(
                "/^[\\t ]*\\\$isEnglish\\s*=\\s*app\\(\\)->getLocale\\(\\)\\s*={2,3}\\s*'en';\\R/m",
                '',
                $updated,
            ) ?? $updated;
        }

        $themeFiles[$path] = $updated;
        $themeReplacements += $replacements;
    }

    if ($themeReplacements === 0) {
        continue;
    }

    $decodedCatalogs = [];

    foreach (['vi', 'en'] as $locale) {
        $catalogPath = $themeDirectory->getPathname()
            .DIRECTORY_SEPARATOR.'lang'
            .DIRECTORY_SEPARATOR.$locale.'.json';
        $existing = is_file($catalogPath)
            ? json_decode((string) file_get_contents($catalogPath), true)
            : [];

        if (! is_array($existing)) {
            $summary['skipped_invalid_catalogs'][] = $catalogPath;
            continue 2;
        }

        $decodedCatalogs[$locale] = [
            'path' => $catalogPath,
            'entries' => array_replace($existing, $catalogs[$locale] ?? []),
        ];
    }

    $summary['themes']++;
    $summary['files'] += count($themeFiles);
    $summary['replacements'] += $themeReplacements;

    if (! $write) {
        continue;
    }

    foreach ($themeFiles as $path => $contents) {
        file_put_contents($path, $contents);
    }

    foreach ($decodedCatalogs as $catalog) {
        $directory = dirname($catalog['path']);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $catalog['path'],
            json_encode(
                $catalog['entries'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL,
        );
    }
}

echo json_encode(
    ['mode' => $write ? 'write' : 'dry-run'] + $summary,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
).PHP_EOL;

exit($summary['skipped_invalid_catalogs'] === [] ? 0 : 1);
