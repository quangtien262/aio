<?php

namespace Tests\Unit;

use App\Support\Localization\LocaleCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocaleCodeTest extends TestCase
{
    public static function validLocales(): array
    {
        return [
            ['vi', 'vi'],
            ['EN_us', 'en-US'],
            ['zh-hant-hk', 'zh-Hant-HK'],
            ['sr_Latn_RS', 'sr-Latn-RS'],
            ['de-DE-u-co-phonebk', 'de-DE-u-co-phonebk'],
            ['x-company-preview', 'x-company-preview'],
        ];
    }

    #[DataProvider('validLocales')]
    public function test_it_validates_and_normalizes_bcp_47_locale_codes(
        string $input,
        string $expected,
    ): void {
        $this->assertTrue(LocaleCode::isValid($input));
        $this->assertSame($expected, LocaleCode::normalize($input));
    }

    public function test_it_rejects_invalid_or_oversized_locale_codes(): void
    {
        $this->assertFalse(LocaleCode::isValid('e_US'));
        $this->assertFalse(LocaleCode::isValid('en--US'));
        $this->assertFalse(LocaleCode::isValid(str_repeat('a', 36)));
        $this->assertNull(LocaleCode::tryNormalize('en-@-US'));

        $this->expectException(InvalidArgumentException::class);
        LocaleCode::normalize('en-@-US');
    }

    public function test_route_pattern_accepts_structurally_valid_dynamic_segments(): void
    {
        $pattern = '/^'.LocaleCode::routePattern().'$/D';

        $this->assertSame(1, preg_match($pattern, 'en-US'));
        $this->assertSame(1, preg_match($pattern, 'zh-Hant-HK'));
        $this->assertSame(1, preg_match($pattern, 'x-company-preview'));
        $this->assertSame(0, preg_match($pattern, 'en_US'));
        $this->assertSame(0, preg_match($pattern, '123'));
    }
}
