<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0306GalleryFaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_faq_is_vietnamese_and_renders_separate_questions(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0306', 'xd0306-digital-agency');
        $faq = LandingPageBlock::where('block_type', 'faq_showcase')->firstOrFail()->data()->where('locale', 'vi')->firstOrFail();
        $this->assertSame('Câu hỏi thường gặp', $faq->title);
        $this->assertSame('Giải đáp cùng bạn', $faq->subtitle);
        $this->assertCount(4, json_decode($faq->content, true)['items']);
        $this->get('/vi')->assertOk()->assertSee('Câu hỏi thường gặp')->assertSee('Dấu ấn sáng tạo')->assertSee('Thời gian triển khai dự án là bao lâu?')->assertDontSee("Faq's");
    }
}
