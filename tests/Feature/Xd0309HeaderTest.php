<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0309HeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_inner_pages_use_a_readable_header_in_the_document_flow(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0309');
        app(ThemeDemoContentGenerator::class)->generate('XD0309', $provider->defaultPreset());
        $urls = [
            'home' => '/vi',
            'services' => route('site.services.index'),
            'service' => route('site.services.show', ['slug' => CmsService::firstOrFail()->slug]),
            'about' => route('site.pages.show', ['slug' => CmsPage::where('slug', 'like', '%gioi-thieu')->firstOrFail()->slug]),
            'projects' => route('site.projects.index'),
            'project' => route('site.projects.show', ['slug' => CmsProject::firstOrFail()->slug]),
            'news' => route('site.blog.index'),
            'news-category' => route('site.blog.category', ['slug' => CmsCategory::firstOrFail()->slug]),
            'article' => route('site.blog.show', ['slug' => CmsPost::firstOrFail()->slug]),
            'products' => route('site.catalog.search'),
            'category' => route('site.catalog.category', ['slug' => CatalogCategory::firstOrFail()->slug]),
            'product' => route('site.catalog.product', ['slug' => CatalogProduct::firstOrFail()->slug]),
            'contact' => route('site.contact'),
        ];
        foreach ($urls as $kind => $url) {
            $response = $this->get($url)->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame($kind === 'home' ? 0 : 1, $xpath->query('//div[@id="top" and contains(@class,"xd9-inner-page")]/header')->length, $kind);
            $this->assertSame(1, $xpath->query('//div[@id="top"]/header[contains(@class,"xd5-header")]')->length, $kind.' shared header');
            $this->assertSame(1, $xpath->query('//div[@id="top"]/header//nav[@data-xd5-nav]')->length, $kind.' shared menu');
            $this->assertSame(1, $xpath->query('//div[@id="top"]/footer[contains(@class,"xd5-footer")]')->length, $kind.' shared footer');
            if ($folder = getenv('XD0309_HEADER_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$kind.'.html', $response->getContent());
            }
        }
    }
}
