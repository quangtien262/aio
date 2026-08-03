<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0322HeaderTest extends TestCase
{
    public function test_language_switcher_and_contact_actions_live_inside_xd0322_header(): void
    {
        $header = file_get_contents(base_path('themes/XD0322/views/partials/header.blade.php'));
        $styles = file_get_contents(base_path('themes/XD0322/views/partials/styles.blade.php'));

        $this->assertStringContainsString('class="c322-header-contact"', $header);
        $this->assertStringContainsString('class="foot-header__account"', $header);
        $this->assertStringContainsString('class="c322-header-language"', $header);
        $this->assertSame(1, substr_count($header, "@include('partials.storefront-language-switcher')"));
        $this->assertStringContainsString('.c322-header-language .sf-language-switcher{position:static', $styles);
        $this->assertStringContainsString('.c322-header-contact a:last-child{display:inline-flex}', $styles);
    }
}
