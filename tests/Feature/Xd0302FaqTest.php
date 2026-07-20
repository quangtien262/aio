<?php

namespace Tests\Feature;

use Tests\TestCase;

class Xd0302FaqTest extends TestCase
{
    public function test_faq_aside_renders_block_data_instead_of_fixed_copy(): void
    {
        $html = $this->renderFaq([
            'aside_title' => 'Tiêu đề tùy chỉnh',
            'aside_description' => 'Mô tả tùy chỉnh',
            'aside_button_label' => 'Liên hệ ngay',
            'aside_button_url' => '/contact',
            'items' => [['question' => 'Câu hỏi?', 'answer' => 'Câu trả lời.']],
        ], ['aside_image' => 'https://example.com/faq.jpg']);

        $this->assertStringContainsString('Tiêu đề tùy chỉnh', $html);
        $this->assertStringContainsString('Mô tả tùy chỉnh', $html);
        $this->assertStringContainsString('https://example.com/faq.jpg', $html);
        $this->assertStringContainsString('href="/contact"', $html);
        $this->assertStringContainsString('Liên hệ ngay', $html);
        $this->assertStringContainsString('Câu hỏi?', $html);
    }

    public function test_faq_aside_button_is_hidden_without_label_or_link(): void
    {
        $withoutLabel = $this->renderFaq(['aside_button_label' => '', 'aside_button_url' => '/contact']);
        $withoutLink = $this->renderFaq(['aside_button_label' => 'Liên hệ ngay', 'aside_button_url' => '']);

        $this->assertStringNotContainsString('<a class="xd2-button"', $withoutLabel);
        $this->assertStringNotContainsString('<a class="xd2-button"', $withoutLink);
    }

    private function renderFaq(array $content, array $media = []): string
    {
        return view('theme-xd0302::partials.blocks.faq_showcase', [
            'anchor' => 'hoi-dap',
            'block' => ['id' => 1, 'media' => $media],
            'data' => ['title' => 'Hỏi đáp', 'subtitle' => 'Câu hỏi thường gặp', 'content' => $content],
            'content' => $content,
            'editButton' => '',
        ])->render();
    }
}
