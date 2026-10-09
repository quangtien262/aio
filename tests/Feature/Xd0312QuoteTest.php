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

class Xd0312QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pages_share_header_footer_and_quote_dialog(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0312');
        app(ThemeDemoContentGenerator::class)->generate('XD0312', $provider->defaultPreset());
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
            $this->assertSame(1, $xpath->query('//dialog[@id="xd12-quote-dialog"]')->length, $kind.' shared modal');
            $this->assertSame(2, $xpath->query('//header//*[@data-xd12-quote-open]')->length, $kind.' quote buttons');
            if ($kind !== 'home') { $response->assertSee('data-storefront-inner-header', false); }
            $this->assertSame(1, $xpath->query('//div[@id="top"]/header[contains(@class,"xd12-header")]')->length, $kind.' shared header');
            $this->assertSame(1, $xpath->query('//div[@id="top"]/header//nav[@data-xd5-nav]')->length, $kind.' shared menu');
            $this->assertSame(1, $xpath->query('//div[@id="top"]/footer[contains(@class,"xd5-footer")]')->length, $kind.' shared footer');
            if ($folder = getenv('XD0312_HEADER_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$kind.'.html', $response->getContent());
            }
        }
    }
    public function test_quote_ajax_validates_and_saves_the_inquiry_and_linked_order(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \App\Models\SiteProfile::create(['site_name' => 'XD0312', 'active_theme_key' => 'XD0312', 'website_type' => 'service']);
        $this->postJson('/vi/contact', ['source' => 'quote_modal', 'name' => '', 'email' => 'invalid', 'message' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->assertDatabaseCount('contact_inquiries', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->postJson('/vi/contact', [
            'source' => 'quote_modal', 'name' => 'Khách logistics', 'email' => 'customer@example.test',
            'phone' => '0909123456', 'subject' => 'Vận chuyển hàng', 'route_summary' => 'Hà Nội - Đà Nẵng',
            'message' => 'Cần vận chuyển 20 kiện hàng trong tuần tới.',
        ])->assertOk()->assertJsonStructure(['message', 'data']);
        $inquiry = \App\Models\ContactInquiry::firstOrFail();
        $this->assertSame('quote_modal', $inquiry->source);
        $this->assertSame('Hà Nội - Đà Nẵng', $inquiry->route_summary);
        $this->assertNotNull($inquiry->order_id);
        $this->assertDatabaseCount('orders', 1);
    }
}
