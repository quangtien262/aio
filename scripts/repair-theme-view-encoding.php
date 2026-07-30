<?php

declare(strict_types=1);

/**
 * Repairs legacy mojibake in Blade text and string literals without rewriting
 * already-valid Vietnamese. Markup/PHP delimiters and characters that cannot
 * originate from Windows-1252 form conservative segment boundaries.
 *
 * Run without --write to preview. The transformation is idempotent.
 */

$write = in_array('--write', $argv, true);
$themeRoot = dirname(__DIR__).DIRECTORY_SEPARATOR.'themes';
$summary = [
    'mode' => $write ? 'write' : 'dry-run',
    'files' => 0,
    'segments' => 0,
    'passes' => 0,
    'examples' => [],
];

$mojibakeScore = static function (string $value): int {
    preg_match_all('/Ã|Â|â|Ä|Å|Æ|áº|á»/u', $value, $matches);

    return count($matches[0]);
};

$repairSegment = static function (string $value) use ($mojibakeScore, &$summary): string {
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

    if ($passes === 0) {
        return $value;
    }

    $summary['segments']++;
    $summary['passes'] += $passes;

    if (count($summary['examples']) < 12) {
        $summary['examples'][] = [
            'before' => $value,
            'after' => $current,
            'passes' => $passes,
        ];
    }

    return $current;
};

$isBoundary = static function (string $character): bool {
    if (preg_match('/[\r\n<>\'"{}\[\]();=]/u', $character)) {
        return true;
    }

    $encoded = mb_convert_encoding($character, 'Windows-1252', 'UTF-8');

    return $encoded === '?'
        || mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252') !== $character;
};

$repairContents = static function (string $contents) use ($isBoundary, $repairSegment): string {
    $characters = preg_split('//u', $contents, -1, PREG_SPLIT_NO_EMPTY);

    if (! is_array($characters)) {
        return $contents;
    }

    $result = '';
    $segment = '';

    foreach ($characters as $character) {
        if (! $isBoundary($character)) {
            $segment .= $character;
            continue;
        }

        $result .= $repairSegment($segment).$character;
        $segment = '';
    }

    return $result.$repairSegment($segment);
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $path = $file->getPathname();
    $contents = file_get_contents($path);

    if (! is_string($contents) || ! mb_check_encoding($contents, 'UTF-8')) {
        continue;
    }

    $segmentsBefore = $summary['segments'];
    $repaired = $repairContents($contents);

    if ($summary['segments'] === $segmentsBefore || $repaired === $contents) {
        continue;
    }

    $summary['files']++;

    if ($write) {
        file_put_contents($path, $repaired);
    }
}

echo json_encode(
    $summary,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
).PHP_EOL;
