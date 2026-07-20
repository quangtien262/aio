<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0302AboutTest extends TestCase
{
    public function test_about_block_renders_one_composite_image_and_tab_content(): void
    {
        $html = view('theme-xd0302::partials.blocks.about_experience', [
            'anchor' => 'gioi-thieu',
            'block' => ['id' => 12],
            'content' => [
                'tabs' => [
                    ['label' => 'Về chúng tôi', 'description' => 'Nội dung giới thiệu'],
                    ['label' => 'Tầm nhìn', 'description' => 'Nội dung tầm nhìn'],
                    ['label' => 'Sứ mệnh', 'description' => 'Nội dung sứ mệnh'],
                ],
                'image_secondary' => 'https://example.com/legacy-secondary.jpg',
            ],
            'data' => [
                'title' => 'Công ty năng lượng Việt Nam',
                'subtitle' => 'Giới thiệu của chúng tôi',
                'description' => 'Nội dung mặc định',
                'button_label' => 'Chi tiết',
            ],
            'settings' => ['years' => 29, 'cta_url' => '/gioi-thieu'],
            'media' => [
                'image' => 'https://example.com/about-composite.jpg',
                'image_secondary' => 'https://example.com/old-secondary.jpg',
            ],
            'editButton' => '',
            'localizeMenuUrl' => fn (string $url): string => $url,
        ])->render();

        $this->assertSame(1, substr_count($html, '<img'));
        $this->assertStringContainsString('https://example.com/about-composite.jpg', $html);
        $this->assertStringNotContainsString('legacy-secondary.jpg', $html);
        $this->assertStringNotContainsString('old-secondary.jpg', $html);
        $this->assertStringNotContainsString('29+', $html);
        $this->assertSame(3, substr_count($html, 'role="tab"'));
        $this->assertStringContainsString('class="is-active"', $html);
    }
}
