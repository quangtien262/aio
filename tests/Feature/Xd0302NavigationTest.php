<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0302NavigationTest extends TestCase
{
    public function test_header_renders_recursive_desktop_and_mobile_menus(): void
    {
        $html = view('theme-xd0302::partials.header', [
            'navItems' => [[
                'label' => 'Dịch vụ',
                'href' => '#dich-vu',
                'children' => [[
                    'label' => 'Điện mặt trời',
                    'href' => '/solar',
                    'children' => [[
                        'label' => 'Cho nhà máy',
                        'href' => '/solar/factory',
                    ]],
                ]],
            ]],
            'phoneHref' => '19009477',
            'hotline' => '1900 9477',
            'supportEmail' => 'admin@example.com',
        ])->render();

        $this->assertSame(4, substr_count($html, 'class="xd2-submenu'));
        $this->assertSame(4, substr_count($html, 'data-xd-submenu-toggle'));
        $this->assertStringContainsString('Điện mặt trời', $html);
        $this->assertStringContainsString('Cho nhà máy', $html);
        $this->assertStringContainsString('xd2-mobile-menu__children', $html);
    }
}
