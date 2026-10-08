<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ser0101HomepageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_preserves_configured_content_and_shows_pricing_features_and_quote_actions(): void
    {
        $html = view('theme-ser0101::home', [
            'siteProfile' => [],
            'activeTheme' => ['key' => 'SER0101'],
            'landingPage' => ['theme_key' => 'SER0101', 'is_home' => true],
            'landingBlocks' => [
                ['id' => 1, 'anchor_id' => 'dich-vu', 'block_type' => 'featured_categories', 'data' => ['title' => 'Dịch vụ riêng', 'content' => ['items' => [['title' => 'Đưa đón sân bay', 'url' => '/vi/ser/dua-don']]]]],
                ['id' => 2, 'anchor_id' => 'bang-gia', 'block_type' => 'service_pricing', 'data' => ['title' => 'Bảng giá riêng', 'content' => ['items' => [['title' => 'Gói tiêu chuẩn', 'price' => 250000, 'features' => 'Đón theo lịch|Xác nhận trước chuyến', 'url' => '#']]]]],
                ['id' => 3, 'anchor_id' => 'lien-he', 'block_type' => 'landing_contact', 'data' => ['title' => 'Liên hệ riêng']],
            ],
        ])->render();

        $this->assertStringContainsString('Dịch vụ riêng', $html);
        $this->assertStringContainsString('href="/vi/ser/dua-don"', $html);
        $this->assertStringContainsString('<li>Đón theo lịch</li>', $html);
        $this->assertStringContainsString('<li>Xác nhận trước chuyến</li>', $html);
        $this->assertStringContainsString('250.000đ', $html);
        $this->assertStringContainsString('Liên hệ riêng', $html);
        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringContainsString('class="ser-home-card-link" data-open-quote-modal', $html);
        $this->assertStringContainsString('class="aio-landing-action" data-open-quote-modal', $html);
    }

    public function test_other_theme_keeps_shared_landing_renderer(): void
    {
        $html = view('partials.configurable-landing-blocks', [
            'activeTheme' => ['key' => 'SER102'],
            'landingPage' => ['is_home' => true],
            'landingBlocks' => [['id' => 1, 'anchor_id' => 'dich-vu', 'block_type' => 'featured_categories', 'data' => ['title' => 'Dịch vụ khác', 'content' => ['items' => [['title' => 'Chăm sóc xe', 'url' => '/vi/s']]]]]],
        ])->render();

        $this->assertStringContainsString('Chăm sóc xe', $html);
        $this->assertStringNotContainsString('ser-home-block', $html);
    }
}
