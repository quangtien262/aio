<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Shop606ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_arrivals_demo_renders_eight_products_and_real_category_links(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->presetsForTheme('SHOP606')[0]['key'];
        $generator->generate('SHOP606', $preset);
        $generator->generate('SHOP606', $preset);
        if ($path = getenv('SHOP606_PRODUCT_PREVIEW')) {
            $previewProduct = CatalogProduct::where('slug', 'shop606-ao-det-kim-kem')->firstOrFail();
            file_put_contents($path, $this->get(route('site.catalog.product', ['slug' => $previewProduct->slug]))->assertOk()->getContent());
        }
        $servicesPage = $this->get('/vi/s')->assertOk()->assertSee('s606-page')->assertDontSee('s605-page')->assertDontSee('OH!UNDER');
        foreach (CmsService::all() as $service) {
            $servicesPage->assertSee($service->title);
            $this->get(route('site.services.show', ['slug' => $service->slug]))->assertOk()->assertSee('s606-service-detail')->assertDontSee('s605-page')->assertSee($service->title);
        }
        $this->get(route('site.blog.index'))->assertOk()->assertSee('s606-page')->assertDontSee('s605-page');
        foreach (CmsPost::all() as $post) {
            $this->get(route('site.blog.show', ['slug' => $post->slug]))->assertOk()->assertSee($post->title)->assertSee('s606-page')->assertDontSee('s605-page');
        }
        $this->get(route('site.projects.index'))->assertOk()->assertSee('s606-page')->assertDontSee('s605-page');
        foreach (CmsProject::all() as $project) {
            $this->get(route('site.projects.show', ['slug' => $project->slug]))->assertOk()->assertSee($project->title)->assertSee('s606-page')->assertDontSee('s605-page');
        }
        if ($path = getenv('SHOP606_CONTACT_PREVIEW')) {
            file_put_contents($path, $this->get(route('site.contact'))->assertOk()->getContent());
        }
        $this->assertSame(8, CatalogProduct::count());
        $this->assertSame(4, CatalogCategory::count());
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(4, $xpath->query('//section[@id="bo-suu-tap"]//div[contains(@class,"s606-collection-grid")]/a')->length);
        $this->assertSame(0, $xpath->query('//article[@class="s606-card"]/div/small')->length);
        $this->assertSame(0, $xpath->query('//section[@id="tin-tuc"]//article/p')->length);
        if ($path = getenv('SHOP606_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
        $cards = $xpath->query('//section[@id="san-pham"]//article');
        $this->assertSame(8, $cards->length);
        foreach ($cards as $card) {
            $src = $xpath->query('.//img', $card)->item(0)->getAttribute('src');
            $this->assertFileExists(public_path(parse_url($src, PHP_URL_PATH)));
            $this->get($xpath->query('.//a', $card)->item(0)->getAttribute('href'))->assertOk();
            $this->assertStringNotContainsString('☆', $card->textContent);
        }
        $links = $xpath->query('//nav[@class="s606-arrivals-categories"]/a');
        $this->assertSame(4, $links->length);
        foreach ($links as $link) {
            $this->get($link->getAttribute('href'))->assertOk();
        }
    }

    public function test_shop606_is_registered_with_expected_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SHOP606');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/SHOP606/preview-shop606.svg'));
        $this->assertFileExists(public_path('theme-previews/SHOP606/cover-shop606.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SHOP606'));
        $this->assertSame([
            'hero_slider',
            'shop606_collections',
            'shop606_sale',
            'shop606_feature',
            'shop606_new',
            'shop606_campaign',
            'shop606_outfit',
            'shop606_news',
            'shop606_gallery',
            'shop606_benefits',
        ], collect($builder->availableBlocks('SHOP606'))->pluck('block_type')->all());
        $this->assertStringContainsString('translateY(-34px)', file_get_contents(base_path('themes/SHOP606/views/partials/styles.blade.php')));
    }

    public function test_shop606_renders_database_content_and_branding_without_reference_contacts(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Maison Ánh Dương',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP606',
            'branding' => [
                'company_name' => 'Maison Ánh Dương',
                'logo_url' => '/storage/branding/maison-anh-duong.svg',
                'support_hotline' => '0886 606 606',
                'support_email' => 'chamsoc@maison.test',
                'support_location' => '18 Phố Lụa, Hà Nội',
                'copyright_text' => 'Bản quyền thuộc về Maison Ánh Dương.',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Đầm thiết kế', 'slug' => 'dam-thiet-ke', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Đầm lụa Ánh Dương', 'slug' => 'dam-lua-anh-duong', 'sku' => 'SHOP606-001', 'price' => 1680000, 'stock' => 8, 'image_url' => '/theme-demo/shop604/product-women-rose.png', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        CmsPost::query()->create(['title' => 'Cách phối trang phục thanh lịch', 'slug' => 'cach-phoi-trang-phuc-thanh-lich', 'status' => 'published', 'excerpt' => 'Gợi ý phối đồ hiện đại và tinh tế.', 'body' => '<p>Nội dung bài viết.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'SHOP606', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/maison-anh-duong.svg', false)
            ->assertSee('0886 606 606')
            ->assertSee('chamsoc@maison.test')
            ->assertSee('18 Phố Lụa, Hà Nội')
            ->assertSee('Bản quyền thuộc về Maison Ánh Dương.')
            ->assertSee('Đầm thiết kế')
            ->assertSee('Đầm lụa Ánh Dương')
            ->assertSee('Cách phối trang phục thanh lịch')
            ->assertSee('data-block-type="shop606_outfit"', false)
            ->assertDontSee('EGA CHIC')
            ->assertDontSee('19006750')
            ->assertDontSee('support@sapo.vn')
            ->assertDontSee('70 Lu Gia');
    }
}
