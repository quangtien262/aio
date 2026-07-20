<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0302HeroTest extends TestCase
{
    public function test_cta_and_dark_overlay_are_hidden_when_hero_has_no_text(): void
    {
        $html = $this->renderHero([
            'title' => '',
            'subtitle' => '',
            'description' => '',
            'button_label' => '',
        ], [[
            'title' => '',
            'kicker' => '',
            'summary' => '',
            'button_label' => 'Nút cũ của banner',
            'image' => 'https://example.com/hero.jpg',
        ]]);

        $this->assertStringNotContainsString('xd2-hero--has-text', $html);
        $this->assertStringNotContainsString('<a class="xd2-button"', $html);
    }

    public function test_item_cta_is_rendered_when_its_label_and_link_are_present(): void
    {
        $html = $this->renderHero([
            'button_label' => 'CTA cấp khối không còn được sử dụng',
        ], [[
            'title' => 'Năng lượng sạch',
            'button_label' => 'Xem dự án',
            'link_url' => '#du-an',
            'image' => 'https://example.com/hero.jpg',
        ]]);

        $this->assertStringContainsString('xd2-hero--has-text', $html);
        $this->assertStringContainsString('>Xem dự án <span>→</span></a>', $html);
    }

    public function test_item_cta_is_hidden_when_its_label_is_missing(): void
    {
        $html = $this->renderHero([], [[
            'title' => 'Năng lượng sạch',
            'button_label' => '',
            'link_url' => '#du-an',
            'image' => 'https://example.com/hero.jpg',
        ]]);

        $this->assertStringNotContainsString('<a class="xd2-button"', $html);
    }

    private function renderHero(array $data, array $slides): string
    {
        return view('theme-xd0302::partials.blocks.hero_slider', [
            'anchor' => 'top',
            'block' => [
                'id' => 1,
                'dynamic_items' => [],
            ],
            'content' => ['slides' => $slides],
            'data' => $data,
            'settings' => [],
            'editButton' => '',
        ])->render();
    }
}
