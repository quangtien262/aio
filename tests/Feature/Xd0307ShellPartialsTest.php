<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0307ShellPartialsTest extends TestCase
{
    public function test_xd0307_header_and_footer_normalize_contact_variables(): void
    {
        $header = file_get_contents(base_path('themes/XD0307/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/XD0307/views/partials/footer.blade.php'));

        $this->assertStringContainsString('$xd5SupportAddress', $header);
        $this->assertStringContainsString('$supportAddress ?? $address', $header);
        $this->assertStringContainsString('$supportEmail ?? $email', $header);
        $this->assertStringContainsString('$companyName ?? $logoAlt', $header);
        $this->assertStringContainsString('data-xd-auth-open="login"', $header);
        $this->assertStringContainsString('data-xd-auth-open="register"', $header);
        $this->assertStringContainsString('class="xd5-language"', $header);
        $this->assertSame(1, substr_count($header, "@include('partials.storefront-language-switcher')"));

        $this->assertStringContainsString('$xd5SupportAddress', $footer);
        $this->assertStringContainsString('$supportAddress ?? $address', $footer);
        $this->assertStringContainsString('$supportEmail ?? $email', $footer);
        $this->assertStringContainsString('$companyName ?? $logoAlt', $footer);

        $this->assertStringNotContainsString('{{ $supportAddress }}', $header);
        $this->assertStringNotContainsString('{{ $supportEmail }}', $header);
        $this->assertStringNotContainsString('{{ $companyName }}', $header);
        $this->assertStringNotContainsString('{{ $supportAddress }}', $footer);
        $this->assertStringNotContainsString('{{ $supportEmail }}', $footer);
        $this->assertStringNotContainsString('{{ $companyName }}', $footer);
    }
}
