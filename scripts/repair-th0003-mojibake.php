<?php

$files = [
    __DIR__ . '/../themes/TH0003/theme.json',
    __DIR__ . '/../themes/TH0003/lang/vi.json',
    __DIR__ . '/../themes/TH0003/lang/en.json',
    __DIR__ . '/../themes/TH0003/views/home.blade.php',
    __DIR__ . '/../themes/TH0003/views/category.blade.php',
    __DIR__ . '/../themes/TH0003/views/search.blade.php',
    __DIR__ . '/../themes/TH0003/views/product.blade.php',
    __DIR__ . '/../themes/TH0003/views/cart.blade.php',
    __DIR__ . '/../themes/TH0003/views/checkout.blade.php',
    __DIR__ . '/../themes/TH0003/views/checkout-success.blade.php',
    __DIR__ . '/../themes/TH0003/views/cms.blade.php',
    __DIR__ . '/../themes/TH0003/views/partials/home-hero-slider.blade.php',
    __DIR__ . '/../themes/TH0003/views/partials/engagement-modals.blade.php',
];

$needsRepairPattern = '/[ÃÄÆðâ]/u';

$repair = static function (string $value) use ($needsRepairPattern): string {
    if (! preg_match($needsRepairPattern, $value)) {
        return $value;
    }

    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
};

$quotedPatterns = [
    '/"([^"\r\n]*[ÃÄÆðâ][^"\r\n]*)"/u',
    "/'([^'\\r\\n]*[ÃÄÆðâ][^'\\r\\n]*)'/u",
];

$replacements = [
    'ðŸ“' => '📍',
    'ðŸ“©' => '📩',
    'ðŸ“ž' => '📞',
    'ðŸ›’' => '🛒',
    'âœ‰' => '✉',
    'âŒ•' => '⌕',
    'â€º' => '›',
    'â†’' => '→',
    'â±' => '⏱',
    'â—Œ' => '◌',
];

foreach ($files as $file) {
    $content = file_get_contents($file);

    foreach ($quotedPatterns as $pattern) {
        $content = preg_replace_callback($pattern, static function (array $matches) use ($repair): string {
            return $matches[0][0] . $repair($matches[1]) . substr($matches[0], -1);
        }, $content);
    }

    $content = strtr($content, $replacements);

    file_put_contents($file, $content);
}

echo "TH0003 mojibake repair applied.\n";
