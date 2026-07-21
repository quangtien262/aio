<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0301SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_vietnamese_search_page_renders_without_mojibake(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0301', 'construction-materials');

        $response = $this->get(route('site.catalog.search', ['locale' => 'vi']));

        $response
            ->assertOk()
            ->assertSee('Tìm kiếm sản phẩm')
            ->assertSee('Bộ lọc')
            ->assertSee('Tất cả danh mục')
            ->assertSee('Lọc sản phẩm')
            ->assertSee('Kết quả')
            ->assertSee('sản phẩm phù hợp')
            ->assertDontSee('TÃ', false)
            ->assertDontSee('áº', false)
            ->assertDontSee('á»', false);
    }
}
