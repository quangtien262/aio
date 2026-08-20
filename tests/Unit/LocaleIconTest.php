<?php

namespace Tests\Unit;

use App\Support\Localization\LocaleIcon;
use PHPUnit\Framework\TestCase;

class LocaleIconTest extends TestCase
{
    public function test_it_resolves_region_specific_and_representative_country_icons(): void
    {
        $this->assertSame('VN', LocaleIcon::countryCode('vi'));
        $this->assertSame('GB', LocaleIcon::countryCode('en'));
        $this->assertSame('US', LocaleIcon::countryCode('en-US'));
        $this->assertSame('HK', LocaleIcon::countryCode('zh-Hant-HK'));
        $this->assertSame('CA', LocaleIcon::countryCode('fr-CA'));
    }

    public function test_every_valid_locale_receives_a_self_contained_image_icon(): void
    {
        foreach (['vi', 'en', 'zh-CN', 'eo', 'x-company'] as $locale) {
            $metadata = LocaleIcon::metadata($locale);

            $this->assertStringStartsWith('data:image/svg+xml;base64,', $metadata['icon_url']);
            $this->assertNotSame('', base64_decode(substr($metadata['icon_url'], 26), true));
            $this->assertNotSame('', $metadata['icon_alt']);
        }

        $vietnamSvg = base64_decode(substr(LocaleIcon::dataUri('vi'), 26), true);
        $this->assertIsString($vietnamSvg);
        $this->assertStringContainsString('#da251d', $vietnamSvg);
        $this->assertStringContainsString('<polygon', $vietnamSvg);
        $this->assertStringNotContainsString('Emoji', $vietnamSvg);
    }
}
