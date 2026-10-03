<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0313BenefitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_regenerated_demo_renders_four_benefits_instead_of_product_categories(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->presetsForTheme('XD0313')[0]['key'];
        $generator->generate('XD0313', $preset);
        $generator->generate('XD0313', $preset);
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, CmsTestimonial::count());
        $this->assertSame(4, CmsPost::count());
        $this->assertSame(6, $xpath->query('//figure[@class="rx13-feedback-card"]')->length);
        $posts = $xpath->query('//section[@id="blog"]//article');
        $this->assertSame(4, $posts->length);
        foreach ($posts as $post) {
            $this->assertSame(0, $xpath->query('.//p|.//a[@class="rx13-button"]', $post)->length);
            $link = $xpath->query('.//h3/a', $post)->item(0);
            $this->get($link->getAttribute('href'))->assertOk();
            $image = $xpath->query('.//img', $post)->item(0);
            $this->assertFileExists(public_path(parse_url($image->getAttribute('src'), PHP_URL_PATH)));
        }
        $response->assertDontSee('Video nổi bật');
        if ($path = getenv('XD0313_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
        $cards = $xpath->query('//section[@id="uu-diem"]//article');
        $this->assertSame(4, $cards->length);
        foreach (['Hướng dẫn hồ sơ rõ ràng', 'Lộ trình phù hợp', 'Tư vấn tận tâm', 'Theo dõi từng bước'] as $index => $title) {
            $this->assertStringContainsString($title, $cards->item($index)->textContent);
            $this->assertStringNotContainsString('Sản phẩm và thiết bị', $cards->item($index)->textContent);
        }
    }
}
