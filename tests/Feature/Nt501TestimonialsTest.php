<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogCategory;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\CmsTestimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt501TestimonialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_regeneration_renders_six_testimonials_without_duplicates(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->presetsForTheme('NT501')[0]['key'];
        $generator->generate('NT501', $preset);
        $result = $generator->generate('NT501', $preset);
        $this->assertSame(6, data_get($result, 'counts.testimonials'));
        $this->assertSame(6, CmsTestimonial::count());
        $response = $this->get('/vi')->assertOk();
        $categories = CatalogCategory::orderBy('sort_order')->get();
        $this->assertCount(3, $categories);
        foreach ($categories as $category) {
            $this->assertSame(1, $category->products()->count());
            $this->assertFileExists(public_path($category->image_url));
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $cards = (new \DOMXPath($dom))->query('//section[contains(@class,"nt-stats")]//article');
        $this->assertSame(3, $cards->length);
        foreach ($categories as $index => $category) {
            $this->assertStringContainsString($category->name, $cards->item($index)->textContent);
        }

        $this->assertSame(6, data_get($result, 'counts.services'));
        $this->assertSame(6, CmsService::count());
        $xpath = new \DOMXPath($dom);
        $serviceCards = $xpath->query('//section[@id="dich-vu"]//article');
        $this->assertSame(6, $serviceCards->length);
        foreach ($serviceCards as $card) {
            $image = $xpath->query('.//img', $card)->item(0);
            $this->assertFileExists(public_path(parse_url($image->getAttribute('src'), PHP_URL_PATH)));
            $link = $xpath->query('.//a', $card)->item(0);
            $this->get($link->getAttribute('href'))->assertOk();
        }
        $response->assertSee('Cải tạo không gian sống')->assertSee('Tư vấn vật liệu và ánh sáng');

        $this->assertSame(6, data_get($result, 'counts.projects'));
        $this->assertSame(6, CmsProject::count());
        foreach (['nt-showcase__card' => 2, 'nt-project-card' => 6] as $class => $expected) {
            $projectCards = $xpath->query('//article[@class="'.$class.'"]');
            $this->assertSame($expected, $projectCards->length);
            foreach ($projectCards as $card) {
                $image = $xpath->query('.//img', $card)->item(0);
                $this->assertFileExists(public_path(parse_url($image->getAttribute('src'), PHP_URL_PATH)));
                $link = $xpath->query('.//a', $card)->item(0);
                $this->get($link->getAttribute('href'))->assertOk();
            }
        }
        $response->assertDontSee('Đang cập nhật dự án.');

        $this->assertSame(6, substr_count($response->getContent(), 'class="nt-feedback-card"'));
        foreach (CmsTestimonial::all() as $testimonial) {
            $response->assertSee($testimonial->name)->assertSee($testimonial->quote);
            $this->assertFileExists(public_path($testimonial->image_url));
        }
        if ($path = getenv('NT501_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }
}
