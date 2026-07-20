<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0302TestimonialShowcaseTest extends TestCase
{
    public function test_custom_source_renders_saved_items_instead_of_dynamic_items(): void
    {
        $html = $this->renderBlock(
            ['source' => 'custom', 'limit' => 3],
            [['name' => 'Tên đã sửa', 'quote' => 'Nội dung đã sửa']],
            [['name' => 'Tên từ CMS', 'quote' => 'Nội dung CMS']],
        );

        $this->assertStringContainsString('Tên đã sửa', $html);
        $this->assertStringContainsString('Nội dung đã sửa', $html);
        $this->assertStringNotContainsString('Tên từ CMS', $html);
    }

    public function test_cms_source_renders_dynamic_items(): void
    {
        $html = $this->renderBlock(
            ['source' => 'cms_testimonials', 'limit' => 3],
            [['name' => 'Tên thủ công', 'quote' => 'Nội dung thủ công']],
            [['name' => 'Tên từ CMS', 'quote' => 'Nội dung CMS']],
        );

        $this->assertStringContainsString('Tên từ CMS', $html);
        $this->assertStringNotContainsString('Tên thủ công', $html);
    }

    public function test_legacy_block_with_saved_items_is_treated_as_custom(): void
    {
        $html = $this->renderBlock(
            ['limit' => 3],
            [['title' => 'Bản đã lưu', 'summary' => 'Nội dung đã lưu']],
            [['name' => 'Bản CMS', 'quote' => 'Nội dung CMS']],
        );

        $this->assertStringContainsString('Bản đã lưu', $html);
        $this->assertStringContainsString('Nội dung đã lưu', $html);
        $this->assertStringNotContainsString('Bản CMS', $html);
    }

    private function renderBlock(array $settings, array $customItems, array $dynamicItems): string
    {
        return view('theme-xd0302::partials.blocks.testimonial_showcase', [
            'anchor' => 'cam-nhan-khach-hang',
            'block' => ['id' => 1, 'block_type' => 'testimonial_showcase', 'dynamic_items' => $dynamicItems],
            'data' => ['title' => 'Cảm nhận', 'subtitle' => 'Lời chứng thực', 'description' => 'Mô tả'],
            'content' => ['items' => $customItems],
            'settings' => $settings,
            'editButton' => '',
        ])->render();
    }
}
