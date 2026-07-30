<?php

declare(strict_types=1);

/**
 * Repairs UTF-8 text that was decoded as Windows-1252 one or more times before
 * it reached a theme JSON catalog.
 *
 * Run without --write to preview. The repair is conservative: every pass must
 * produce valid UTF-8, reduce the mojibake score and introduce no replacement
 * or question-mark characters.
 */

$write = in_array('--write', $argv, true);
$themeRoot = dirname(__DIR__).DIRECTORY_SEPARATOR.'themes';
$summary = [
    'mode' => $write ? 'write' : 'dry-run',
    'files' => 0,
    'values' => 0,
    'passes' => 0,
    'examples' => [],
    'invalid_catalogs' => [],
];

$mojibakeScore = static function (string $value): int {
    preg_match_all('/Ã|Â|â|Ä|Å|Æ|áº|á»/u', $value, $matches);

    return count($matches[0]);
};

$repairString = static function (string $value) use ($mojibakeScore): array {
    $current = $value;
    $passes = 0;

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $currentScore = $mojibakeScore($current);

        if ($currentScore === 0) {
            break;
        }

        $candidate = mb_convert_encoding($current, 'Windows-1252', 'UTF-8');

        if (
            ! mb_check_encoding($candidate, 'UTF-8')
            || str_contains($candidate, "\u{FFFD}")
            || substr_count($candidate, '?') > substr_count($current, '?')
            || $mojibakeScore($candidate) >= $currentScore
        ) {
            break;
        }

        $current = $candidate;
        $passes++;
    }

    return [$current, $passes];
};

$repairValue = static function (mixed $value) use (&$repairValue, $repairString, &$summary): mixed {
    if (is_array($value)) {
        foreach ($value as $key => $entry) {
            $value[$key] = $repairValue($entry);
        }

        return $value;
    }

    if (! is_string($value)) {
        return $value;
    }

    [$repaired, $passes] = $repairString($value);

    if ($passes === 0) {
        return $value;
    }

    $summary['values']++;
    $summary['passes'] += $passes;

    if (count($summary['examples']) < 12) {
        $summary['examples'][] = [
            'before' => $value,
            'after' => $repaired,
            'passes' => $passes,
        ];
    }

    return $repaired;
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.json')) {
        continue;
    }

    $path = $file->getPathname();

    if (! str_contains(str_replace('\\', '/', $path), '/lang/')) {
        continue;
    }

    $contents = file_get_contents($path);
    $catalog = is_string($contents) ? json_decode($contents, true) : null;

    if (! is_array($catalog)) {
        $summary['invalid_catalogs'][] = $path;
        continue;
    }

    $valuesBefore = $summary['values'];
    $repaired = $repairValue($catalog);

    if ($summary['values'] === $valuesBefore) {
        continue;
    }

    $summary['files']++;

    if ($write) {
        file_put_contents(
            $path,
            json_encode(
                $repaired,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL,
        );
    }
}

echo json_encode(
    $summary,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
).PHP_EOL;

exit($summary['invalid_catalogs'] === [] ? 0 : 1);
