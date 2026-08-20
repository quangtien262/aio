<?php

namespace App\Support\Localization;

final class LocaleIcon
{
    /**
     * A language without an explicit region still needs one stable,
     * recognizable storefront icon. Region subtags always take precedence.
     *
     * @var array<string, string>
     */
    private const LANGUAGE_COUNTRIES = [
        'af' => 'ZA', 'am' => 'ET', 'ar' => 'SA', 'bg' => 'BG', 'bn' => 'BD',
        'ca' => 'ES', 'cs' => 'CZ', 'da' => 'DK', 'de' => 'DE', 'el' => 'GR',
        'en' => 'GB', 'es' => 'ES', 'et' => 'EE', 'fa' => 'IR', 'fi' => 'FI',
        'fil' => 'PH', 'fr' => 'FR', 'he' => 'IL', 'hi' => 'IN', 'hr' => 'HR',
        'hu' => 'HU', 'id' => 'ID', 'is' => 'IS', 'it' => 'IT', 'ja' => 'JP',
        'ko' => 'KR', 'lt' => 'LT', 'lv' => 'LV', 'ms' => 'MY', 'nl' => 'NL',
        'no' => 'NO', 'pl' => 'PL', 'pt' => 'PT', 'ro' => 'RO', 'ru' => 'RU',
        'sk' => 'SK', 'sl' => 'SI', 'sr' => 'RS', 'sv' => 'SE', 'sw' => 'KE',
        'ta' => 'IN', 'te' => 'IN', 'th' => 'TH', 'tl' => 'PH', 'tr' => 'TR',
        'uk' => 'UA', 'ur' => 'PK', 'vi' => 'VN', 'zh' => 'CN',
    ];

    /**
     * @return array{country_code:?string,icon_url:string,icon_alt:string}
     */
    public static function metadata(string $locale, ?string $languageName = null): array
    {
        $locale = LocaleCode::tryNormalize($locale) ?? trim($locale);
        $countryCode = self::countryCode($locale);
        $label = trim((string) $languageName);

        return [
            'country_code' => $countryCode,
            'icon_url' => self::dataUri($locale),
            'icon_alt' => $label !== ''
                ? $label
                : strtoupper($locale !== '' ? $locale : 'LANG'),
        ];
    }

    public static function countryCode(string $locale): ?string
    {
        $locale = LocaleCode::tryNormalize($locale) ?? trim($locale);
        $parts = explode('-', $locale);
        $language = strtolower((string) array_shift($parts));

        foreach ($parts as $part) {
            if (strlen($part) === 2 && ctype_alpha($part)) {
                return strtoupper($part);
            }
        }

        return self::LANGUAGE_COUNTRIES[$language] ?? null;
    }

    public static function dataUri(string $locale): string
    {
        $locale = LocaleCode::tryNormalize($locale) ?? trim($locale);
        $countryCode = self::countryCode($locale);
        $svg = $countryCode !== null
            ? self::flagSvg($countryCode)
            : self::fallbackSvg($locale);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function flagSvg(string $countryCode): string
    {
        $artwork = match ($countryCode) {
            'VN' => '<rect width="36" height="26" fill="#da251d"/>'.self::star(18, 13, 6.5, '#ffed00'),
            'GB' => '<rect width="36" height="26" fill="#21468b"/><path d="M0 0 36 26M36 0 0 26" stroke="#fff" stroke-width="6"/><path d="M0 0 36 26M36 0 0 26" stroke="#cf142b" stroke-width="2.5"/><path d="M18 0v26M0 13h36" stroke="#fff" stroke-width="9"/><path d="M18 0v26M0 13h36" stroke="#cf142b" stroke-width="5"/>',
            'US' => self::horizontal(['#b22234', '#fff', '#b22234', '#fff', '#b22234', '#fff', '#b22234', '#fff', '#b22234', '#fff', '#b22234', '#fff', '#b22234']).'<rect width="15" height="14" fill="#3c3b6e"/><g fill="#fff">'.self::dotGrid(2, 2, 5, 4, 2.6, 2.6).'</g>',
            'JP' => '<rect width="36" height="26" fill="#fff"/><circle cx="18" cy="13" r="7" fill="#bc002d"/>',
            'CN' => '<rect width="36" height="26" fill="#de2910"/>'.self::star(8, 7, 4.5, '#ffde00').self::star(15, 3.5, 1.6, '#ffde00').self::star(18, 7, 1.6, '#ffde00').self::star(18, 12, 1.6, '#ffde00').self::star(14.5, 15.5, 1.6, '#ffde00'),
            'HK' => '<rect width="36" height="26" fill="#de2910"/><g fill="#fff"><ellipse cx="18" cy="8" rx="2.2" ry="5"/><ellipse cx="23" cy="12" rx="2.2" ry="5" transform="rotate(72 23 12)"/><ellipse cx="21" cy="18" rx="2.2" ry="5" transform="rotate(144 21 18)"/><ellipse cx="15" cy="18" rx="2.2" ry="5" transform="rotate(216 15 18)"/><ellipse cx="13" cy="12" rx="2.2" ry="5" transform="rotate(288 13 12)"/></g>',
            'TW' => '<rect width="36" height="26" fill="#fe0000"/><rect width="18" height="13" fill="#000095"/><circle cx="9" cy="6.5" r="4" fill="#fff"/>',
            'KR' => '<rect width="36" height="26" fill="#fff"/><path d="M13 13a5 5 0 0 1 10 0 2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 0-5 0" fill="#cd2e3a"/><path d="M23 13a5 5 0 0 1-10 0 2.5 2.5 0 0 1 5 0 2.5 2.5 0 0 0 5 0" fill="#0047a0"/>',
            'FR' => self::vertical(['#0055a4', '#fff', '#ef4135']),
            'DE' => self::horizontal(['#000', '#dd0000', '#ffce00']),
            'ES' => self::horizontalWeighted([['#aa151b', 1], ['#f1bf00', 2], ['#aa151b', 1]]),
            'PT' => '<rect width="14" height="26" fill="#046a38"/><rect x="14" width="22" height="26" fill="#da291c"/><circle cx="14" cy="13" r="4" fill="#ffcc00"/>',
            'RU' => self::horizontal(['#fff', '#0039a6', '#d52b1e']),
            'UA' => self::horizontal(['#0057b7', '#ffd700']),
            'PL' => self::horizontal(['#fff', '#dc143c']),
            'NL' => self::horizontal(['#ae1c28', '#fff', '#21468b']),
            'HU' => self::horizontal(['#ce2939', '#fff', '#477050']),
            'RO' => self::vertical(['#002b7f', '#fcd116', '#ce1126']),
            'IT' => self::vertical(['#009246', '#fff', '#ce2b37']),
            'ID' => self::horizontal(['#ce1126', '#fff']),
            'TH' => self::horizontalWeighted([['#a51931', 1], ['#fff', 1], ['#2d2a4a', 2], ['#fff', 1], ['#a51931', 1]]),
            'PH' => '<rect width="36" height="13" fill="#0038a8"/><rect y="13" width="36" height="13" fill="#ce1126"/><path d="M0 0 15 13 0 26Z" fill="#fff"/><circle cx="5" cy="13" r="2.4" fill="#fcd116"/>',
            'MY' => self::horizontal(['#cc0001', '#fff', '#cc0001', '#fff', '#cc0001', '#fff', '#cc0001', '#fff']).'<rect width="18" height="13" fill="#010066"/><circle cx="8" cy="6.5" r="4.2" fill="#ffcc00"/><circle cx="9.7" cy="5.8" r="4" fill="#010066"/>',
            'IN' => self::horizontal(['#ff9933', '#fff', '#138808']).'<circle cx="18" cy="13" r="3.1" fill="none" stroke="#000080" stroke-width="1"/><circle cx="18" cy="13" r=".8" fill="#000080"/>',
            'BD' => '<rect width="36" height="26" fill="#006a4e"/><circle cx="16" cy="13" r="7" fill="#f42a41"/>',
            'PK' => '<rect width="36" height="26" fill="#01411c"/><rect width="8" height="26" fill="#fff"/><circle cx="22" cy="12" r="6" fill="#fff"/><circle cx="24" cy="10.5" r="6" fill="#01411c"/>'.self::star(28, 7, 2, '#fff'),
            'TR' => '<rect width="36" height="26" fill="#e30a17"/><circle cx="15" cy="13" r="7" fill="#fff"/><circle cx="17" cy="13" r="5.5" fill="#e30a17"/>'.self::star(23, 13, 2.5, '#fff'),
            'IL' => '<rect width="36" height="26" fill="#fff"/><rect y="3" width="36" height="3" fill="#0038b8"/><rect y="20" width="36" height="3" fill="#0038b8"/><path d="m18 7 5 9H13Zm0 12-5-9h10Z" fill="none" stroke="#0038b8" stroke-width="1.2"/>',
            'SA' => '<rect width="36" height="26" fill="#006c35"/><path d="M9 9h18M11 12h14M10 17h17" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>',
            'CZ' => self::horizontal(['#fff', '#d7141a']).'<path d="M0 0 15 13 0 26Z" fill="#11457e"/>',
            'DK' => self::cross('#c60c30', '#fff', 11, 4),
            'FI' => self::cross('#fff', '#003580', 11, 5),
            'SE' => self::cross('#006aa7', '#fecc00', 11, 4),
            'NO' => self::cross('#ba0c2f', '#fff', 11, 7).'<rect x="12.5" width="3" height="26" fill="#00205b"/><rect y="11.5" width="36" height="3" fill="#00205b"/>',
            'IS' => self::cross('#02529c', '#fff', 11, 7).'<rect x="12.5" width="3" height="26" fill="#dc1e35"/><rect y="11.5" width="36" height="3" fill="#dc1e35"/>',
            'GR' => self::horizontal(['#0d5eaf', '#fff', '#0d5eaf', '#fff', '#0d5eaf', '#fff', '#0d5eaf', '#fff', '#0d5eaf']).'<rect width="14" height="14" fill="#0d5eaf"/><rect x="5.5" width="3" height="14" fill="#fff"/><rect y="5.5" width="14" height="3" fill="#fff"/>',
            'BG' => self::horizontal(['#fff', '#00966e', '#d62612']),
            'EE' => self::horizontal(['#4891d9', '#000', '#fff']),
            'LT' => self::horizontal(['#fdb913', '#006a44', '#c1272d']),
            'LV' => self::horizontalWeighted([['#9e3039', 2], ['#fff', 1], ['#9e3039', 2]]),
            'HR' => self::horizontal(['#ff0000', '#fff', '#171796']),
            'RS' => self::horizontal(['#c6363c', '#0c4076', '#fff']),
            'SI' => self::horizontal(['#fff', '#005da4', '#ed1c24']),
            'SK' => self::horizontal(['#fff', '#0b4ea2', '#ee1c25']),
            'IR' => self::horizontal(['#239f40', '#fff', '#da0000']),
            'CA' => self::verticalWeighted([['#d80621', 1], ['#fff', 2], ['#d80621', 1]]).self::star(18, 13, 4, '#d80621'),
            'ET' => self::horizontal(['#078930', '#fcdd09', '#da121a']).'<circle cx="18" cy="13" r="4.3" fill="#0f47af"/>'.self::star(18, 13, 3, '#fdda0d'),
            'KE' => self::horizontalWeighted([['#000', 2], ['#fff', .35], ['#bb0000', 2], ['#fff', .35], ['#006600', 2]]),
            'ZA' => self::horizontal(['#e03c31', '#fff', '#007749']),
            default => self::countryBadge($countryCode),
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="26" viewBox="0 0 36 26">'
            .'<defs><clipPath id="locale-flag"><rect width="36" height="26" rx="4"/></clipPath></defs>'
            .'<g clip-path="url(#locale-flag)">'.$artwork.'</g>'
            .'<rect x=".5" y=".5" width="35" height="25" rx="3.5" fill="none" stroke="#0f172a" stroke-opacity=".18"/>'
            .'</svg>';
    }

    private static function fallbackSvg(string $locale): string
    {
        $code = strtoupper(substr((string) preg_replace('/[^a-z0-9]/i', '', $locale), 0, 3));
        $code = $code !== '' ? $code : '🌐';
        $hue = abs((int) crc32(strtolower($locale))) % 360;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="26" viewBox="0 0 36 26">'
            .'<rect width="36" height="26" rx="5" fill="hsl('.$hue.',62%,42%)"/>'
            .'<rect x=".5" y=".5" width="35" height="25" rx="4.5" fill="none" stroke="rgba(255,255,255,.5)"/>'
            .'<text x="18" y="17" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="Segoe UI,Arial,sans-serif">'
            .htmlspecialchars($code, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</text></svg>';
    }

    /** @param list<string> $colors */
    private static function horizontal(array $colors): string
    {
        return self::horizontalWeighted(array_map(fn (string $color): array => [$color, 1], $colors));
    }

    /** @param list<array{0:string,1:int|float}> $stripes */
    private static function horizontalWeighted(array $stripes): string
    {
        $total = array_sum(array_column($stripes, 1));
        $offset = 0.0;
        $svg = '';

        foreach ($stripes as [$color, $weight]) {
            $height = 26 * $weight / $total;
            $svg .= '<rect y="'.round($offset, 3).'" width="36" height="'.round($height + .05, 3).'" fill="'.$color.'"/>';
            $offset += $height;
        }

        return $svg;
    }

    /** @param list<string> $colors */
    private static function vertical(array $colors): string
    {
        return self::verticalWeighted(array_map(fn (string $color): array => [$color, 1], $colors));
    }

    /** @param list<array{0:string,1:int|float}> $stripes */
    private static function verticalWeighted(array $stripes): string
    {
        $total = array_sum(array_column($stripes, 1));
        $offset = 0.0;
        $svg = '';

        foreach ($stripes as [$color, $weight]) {
            $width = 36 * $weight / $total;
            $svg .= '<rect x="'.round($offset, 3).'" width="'.round($width + .05, 3).'" height="26" fill="'.$color.'"/>';
            $offset += $width;
        }

        return $svg;
    }

    private static function cross(string $background, string $cross, float $x, float $width): string
    {
        return '<rect width="36" height="26" fill="'.$background.'"/>'
            .'<rect x="'.$x.'" width="'.$width.'" height="26" fill="'.$cross.'"/>'
            .'<rect y="'.((26 - $width) / 2).'" width="36" height="'.$width.'" fill="'.$cross.'"/>';
    }

    private static function star(float $cx, float $cy, float $radius, string $fill): string
    {
        $points = [];

        for ($index = 0; $index < 10; $index++) {
            $angle = deg2rad(-90 + $index * 36);
            $pointRadius = $index % 2 === 0 ? $radius : $radius * .382;
            $points[] = round($cx + cos($angle) * $pointRadius, 3).','.round($cy + sin($angle) * $pointRadius, 3);
        }

        return '<polygon points="'.implode(' ', $points).'" fill="'.$fill.'"/>';
    }

    private static function dotGrid(float $x, float $y, int $columns, int $rows, float $gapX, float $gapY): string
    {
        $svg = '';

        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $svg .= '<circle cx="'.($x + $column * $gapX).'" cy="'.($y + $row * $gapY).'" r=".45"/>';
            }
        }

        return $svg;
    }

    private static function countryBadge(string $countryCode): string
    {
        $hue = abs((int) crc32($countryCode)) % 360;

        return '<rect width="36" height="26" fill="hsl('.$hue.',62%,40%)"/>'
            .'<text x="18" y="17" text-anchor="middle" fill="#fff" font-size="10" font-weight="800" font-family="Segoe UI,Arial,sans-serif">'
            .htmlspecialchars($countryCode, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</text>';
    }
}
