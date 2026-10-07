<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt501AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_preserves_cms_content_and_renders_its_cover_and_navigation(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('NT501', $generator->presetsForTheme('NT501')[0]['key']);
        $page = CmsPage::where('slug', 'nt501-gioi-thieu')->firstOrFail();
        $response = $this->get('/vi/p/'.$page->slug)->assertOk()
            ->assertSee($page->title)->assertSee($page->excerpt)->assertSee($page->body, false)
            ->assertSee('nt-about-page')->assertDontSee('>PAGE<', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $image = $xpath->query('//figure[@class="nt-about-cover"]/img')->item(0);
        $this->assertFileExists(public_path(parse_url($image->getAttribute('src'), PHP_URL_PATH)));
        $this->assertSame(3, $xpath->query('//a[@class="nt-about-explore-card"]')->length);
        foreach ($xpath->query('//a[@class="nt-about-explore-card"]') as $link) {
            $this->get($link->getAttribute('href'))->assertOk();
        }
        if ($path = getenv('NT501_ABOUT_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }

        $page->update(['featured_media_id' => null]);
        $this->get('/vi/p/'.$page->slug)->assertOk()->assertSee('/theme-demo/dn302/dn302-living-room.png');
        $otherPage = CmsPage::where('slug', '!=', $page->slug)->firstOrFail();
        $this->get('/vi/p/'.$otherPage->slug)->assertOk()->assertDontSee('class="nt-about-page"', false);
    }
}
