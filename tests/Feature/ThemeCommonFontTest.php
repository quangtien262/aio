<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThemeCommonFontTest extends TestCase
{
    public function test_dn302_and_nt501_share_the_common_chakra_manrope_font_pack(): void
    {
        $common = file_get_contents(resource_path('views/themes/common/fonts/chakra-manrope.blade.php'));
        $dnLayout = file_get_contents(base_path('themes/DN302/views/layout.blade.php'));
        $dnStyles = file_get_contents(base_path('themes/DN302/views/partials/styles.blade.php'));
        $ntLayout = file_get_contents(base_path('themes/NT501/views/layout.blade.php'));
        $ntStyles = file_get_contents(base_path('themes/NT501/views/partials/styles.blade.php'));

        $this->assertStringContainsString('Chakra+Petch', $common);
        $this->assertStringContainsString('Manrope', $common);
        $this->assertStringContainsString('--theme-font-display', $common);
        $this->assertStringContainsString("@include('themes.common.fonts.chakra-manrope')", $dnLayout);
        $this->assertStringContainsString("@include('themes.common.fonts.chakra-manrope')", $ntLayout);
        $this->assertStringContainsString('--dn-display:var(--theme-font-display)', $dnStyles);
        $this->assertStringContainsString('--nt-serif:var(--theme-font-display)', $ntStyles);
        $this->assertStringNotContainsString('Playfair Display', $ntStyles);
    }
}
