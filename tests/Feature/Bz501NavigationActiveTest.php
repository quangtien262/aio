<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Bz501NavigationActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_selects_the_current_page_and_its_parent_section(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('BZ501', 'bz501-complete');
        $cases = [
            ['/vi', '/vi'],
            ['/vi/p/bz501-gioi-thieu', '/vi/p/bz501-gioi-thieu'],
            ['/vi/contact', '/vi/contact'],
            ['/vi/tim-kiem?q=test', '/vi/tim-kiem'],
            ['/vi/san-pham/'.CatalogProduct::firstOrFail()->slug, '/vi/tim-kiem'],
            ['/vi/s', '/vi/s'],
            ['/vi/ser/'.CmsService::firstOrFail()->slug, '/vi/s'],
            ['/vi/pj', '/vi/pj'],
            ['/vi/prj/'.CmsProject::firstOrFail()->slug, '/vi/pj'],
            ['/vi/c', '/vi/c'],
            ['/vi/n/'.CmsPost::firstOrFail()->slug, '/vi/c'],
        ];
        foreach ($cases as [$url, $expectedPath]) {
            $response = $this->get($url)->assertOk();
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($dom);
            $active = $xpath->query('//nav[contains(@class,"bz501-navigation")]/a[@aria-current="page"]');
            $this->assertCount(1, $active, $url);
            $this->assertSame($expectedPath, parse_url($active->item(0)->getAttribute('href'), PHP_URL_PATH), $url);
            $this->assertStringContainsString('is-active', $active->item(0)->getAttribute('class'), $url);
        }
    }
}
