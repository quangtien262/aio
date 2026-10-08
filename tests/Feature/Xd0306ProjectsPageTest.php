<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0306ProjectsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_page_renders_cards_links_menu_and_an_empty_state(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0306', 'xd0306-digital-agency');
        $response = $this->get('/vi/pj')->assertOk()->assertViewIs('theme-xd0306::projects')
            ->assertSee('Hồ sơ dự án')->assertSee('data-xd5-nav', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//article[@class="xd6-project-card"]');
        $this->assertSame(3, $cards->length);
        foreach ($cards as $card) {
            $imageLink = $xpath->query('./a', $card)->item(0);
            $titleLink = $xpath->query('.//h2/a', $card)->item(0);
            $this->assertSame($imageLink->getAttribute('href'), $titleLink->getAttribute('href'));
        }
        CmsProject::query()->update(['status' => 'draft']);
        $this->get('/vi/pj')->assertOk()->assertSee('Các dự án đang được cập nhật.')
            ->assertDontSee('class="xd6-project-card"', false);
    }
}
