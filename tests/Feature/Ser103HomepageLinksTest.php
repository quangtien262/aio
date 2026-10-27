<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Database\Seeders\Ser103HomepageSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ser103HomepageLinksTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        config(['localized-content.rollout.legacy_fallback' => false]);
        app(ThemeDemoContentGenerator::class)->generate('SER103', 'ser103-bohu-wedding');
    }

    private function homeXPath(): DOMXPath
    {
        $html = $this->get('/vi')->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_home_cards_gallery_and_footer_link_to_their_actual_published_cms_records(): void
    {
        $this->seedDemo();
        CmsService::create(['title' => 'Scheduled service', 'slug' => 'scheduled-service', 'status' => 'published', 'publish_at' => now()->addDay(), 'is_highlight' => true, 'sort_order' => -1]);
        CmsService::create(['website_key' => 'another-website', 'title' => 'Other website service', 'slug' => 'other-service', 'status' => 'published', 'is_highlight' => true, 'sort_order' => -1]);
        $xpath = $this->homeXPath();
        $cards = $xpath->query('//*[@id="dich-vu"]//a[contains(@class,"ser103-service-card")]');
        $this->assertCount(6, $cards);
        $services = CmsService::with('featuredImage')->where('publish_at', '<=', now())->orderBy('sort_order')->get();
        foreach ($services as $index => $service) {
            $card = $cards->item($index);
            $url = route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]);
            $this->assertSame($url, $card->getAttribute('href'));
            $this->assertSame($service->title, $xpath->query('.//h3', $card)->item(0)->textContent);
            $this->assertSame($service->summary, $xpath->query('.//p', $card)->item(0)->textContent);
            $this->assertSame($service->featuredImage->image_url, $xpath->query('.//img', $card)->item(0)->getAttribute('src'));
            $this->get($url)->assertOk()->assertSee($service->title)->assertSee($service->summary);
        }
        $articles = $xpath->query('//*[@id="tin-tuc"]//article');
        $this->assertCount(3, $articles);
        foreach (CmsPost::with('featuredMedia')->latest('publish_at')->get() as $index => $post) {
            $article = $articles->item($index);
            $url = route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]);
            foreach ($xpath->query('.//a', $article) as $link) {
                $this->assertSame($url, $link->getAttribute('href'));
            }
            $this->assertSame($post->featuredMedia->file_url, $xpath->query('.//img', $article)->item(0)->getAttribute('src'));
            $this->assertStringContainsString($post->excerpt, $article->textContent);
            $this->assertStringContainsString($post->publish_at->format('d/m/Y'), $article->textContent);
            $this->get($url)->assertOk()->assertSee($post->title)->assertSee($post->excerpt);
        }
        $galleryLinks = $xpath->query('//*[@id="thu-vien"]/a');
        $this->assertCount(5, $galleryLinks);
        $serviceUrls = $services->map(fn ($service) => route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]))->all();
        foreach ($galleryLinks as $link) {
            $this->assertContains($link->getAttribute('href'), $serviceUrls);
        }
        $footerServices = $xpath->query('//footer//section[h3[text()="Danh mục dịch vụ"]]/a[position() <= 4]');
        $this->assertCount(4, $footerServices);
        foreach ($footerServices as $link) {
            $this->assertContains($link->getAttribute('href'), $serviceUrls);
        }
        $this->assertCount(0, $xpath->query('//a[@href="#" or @href="#bang-gia" or @href="#san-pham"]'));
    }

    public function test_empty_cms_sources_do_not_fall_back_to_obsolete_demo_cards(): void
    {
        $this->seedDemo();
        CmsService::query()->update(['status' => 'draft']);
        CmsPost::query()->update(['status' => 'draft']);
        // Even old saved samples must remain hidden while a CMS source is selected.
        $legacy = json_decode(file_get_contents(base_path('tests/Fixtures/ser103-legacy-home-items.json')), true);
        foreach (LandingPageBlock::whereIn('block_type', array_keys($legacy))->get() as $block) {
            $block->data()->where('locale', 'vi')->update(['content' => json_encode(['items' => $legacy[$block->block_type]])]);
        }
        $xpath = $this->homeXPath();
        $this->assertCount(0, $xpath->query('//*[@id="dich-vu"]//a'));
        $this->assertCount(0, $xpath->query('//*[@id="tin-tuc"]//article'));
        $this->assertCount(0, $xpath->query('//*[@id="thu-vien"]/a'));
    }

    public function test_product_source_uses_real_product_details_and_does_not_mix_other_websites(): void
    {
        $this->seedDemo();
        $product = CatalogProduct::create([
            'name' => 'Áo dài cưới SER103', 'slug' => 'ao-dai-cuoi-ser103', 'sku' => 'SER103-TEST-01',
            'price' => 1500000, 'stock' => 5, 'is_active' => true,
            'short_description' => 'Áo dài lụa dành cho ngày cưới.', 'detail_content' => '<p>Thiết kế áo dài cưới riêng.</p>',
            'image_url' => '/theme-previews/SER103/gallery-aodai.webp',
        ]);
        CatalogProduct::create(['website_key' => 'another-website', 'name' => 'Other website product', 'slug' => 'other-product', 'sku' => 'OTHER-PRODUCT', 'price' => 1, 'is_active' => true]);
        LandingPageBlock::where('block_type', 'business_service_grid')->firstOrFail()
            ->update(['settings' => ['source' => 'cms_products', 'limit' => 6, 'featured_only' => false]]);
        $this->assertSame('website-main', $product->website_key);
        $this->assertDatabaseHas('content_translations', ['resource_type' => 'catalog_product', 'resource_id' => $product->id, 'locale' => 'vi', 'translation_status' => 'published']);
        $this->assertCount(1, app(LandingPageBuilder::class)->previewDynamicItems(LandingPageBlock::where('block_type', 'business_service_grid')->firstOrFail(), 'vi'));
        $xpath = $this->homeXPath();
        $cards = $xpath->query('//*[@id="dich-vu"]//a[contains(@class,"ser103-service-card")]');
        $this->assertCount(1, $cards);
        $url = $cards->item(0)->getAttribute('href');
        $this->assertStringContainsString($product->slug, $url);
        $this->assertSame($product->name, $xpath->query('.//h3', $cards->item(0))->item(0)->textContent);
        $this->get($url)->assertOk()->assertSee($product->name)->assertSee('Thiết kế áo dài cưới riêng.');
    }

    public function test_legacy_homepage_repair_is_repeatable_and_preserves_edited_blocks_and_other_websites(): void
    {
        $this->seedDemo();
        $legacy = json_decode(file_get_contents(base_path('tests/Fixtures/ser103-legacy-home-items.json')), true);
        $blocks = LandingPageBlock::whereIn('block_type', array_keys($legacy))->get();
        foreach ($blocks as $block) {
            $items = $legacy[$block->block_type];
            if ($block->block_type !== 'collection_gallery') {
                foreach ($items as &$item) {
                    $item['url'] = url('vi'.$item['url']);
                }
                unset($item);
            }
            $block->update(['settings' => ['source' => 'custom', 'limit' => count($legacy[$block->block_type]), 'custom_flag' => 'preserve']]);
            $block->data()->where('locale', 'vi')->update(['content' => json_encode(['items' => $items])]);
        }
        $serviceBlock = $blocks->firstWhere('block_type', 'business_service_grid');
        $identities = CmsService::all()->map(fn ($service) => [$service->id, $service->slug])->all();
        $contentBefore = $blocks->mapWithKeys(fn ($block) => [$block->id => $block->data()->where('locale', 'vi')->first()->content])->all();
        SiteProfile::create(['website_key' => 'unrelated', 'site_name' => 'Another website', 'active_theme_key' => 'DN350']);
        $otherPage = LandingPage::create(['website_key' => 'unrelated', 'theme_key' => 'SER103', 'is_home' => true, 'slug' => 'old-home', 'status' => 'published']);
        $otherBlock = $otherPage->blocks()->create(['theme_key' => 'SER103', 'block_type' => 'business_service_grid', 'settings' => $serviceBlock->settings]);
        $otherBlock->data()->create(['locale' => 'vi', 'content' => $contentBefore[$serviceBlock->id]]);
        $migration = require database_path('migrations/2026_10_10_000004_repair_ser103_homepage_content_sources.php');
        $migration->up();
        $migration->up();
        foreach ($blocks as $block) {
            $settings = $block->fresh()->settings;
            $this->assertSame($block->block_type === 'latest_posts' ? 'cms_posts' : 'cms_services', $settings['source']);
            $this->assertSame('preserve', $settings['custom_flag']);
            $this->assertSame($contentBefore[$block->id], $block->data()->where('locale', 'vi')->first()->content);
        }
        $this->assertSame('custom', $otherBlock->fresh()->settings['source']);
        // Restore one old block, edit its content, and prove the additive seeder leaves it intact.
        $custom = $legacy['business_service_grid'];
        $custom[0]['summary'] = 'Thông tin do Sếp tự biên tập.';
        $serviceBlock->update(['settings' => ['source' => 'custom', 'limit' => 5]]);
        $serviceBlock->data()->where('locale', 'vi')->update(['content' => json_encode(['items' => $custom])]);
        app(Ser103HomepageSeeder::class)->run();
        $this->assertSame('custom', $serviceBlock->fresh()->settings['source']);
        $this->assertSame($identities, CmsService::all()->map(fn ($service) => [$service->id, $service->slug])->all());
        $this->get('/vi')->assertOk()->assertSee('Thông tin do Sếp tự biên tập.');
        $custom = $legacy['business_service_grid'];
        $custom[0]['url'] = 'https://custom.example.test/vi/s';
        $serviceBlock->data()->where('locale', 'vi')->update(['content' => json_encode(['items' => $custom])]);
        $migration->up();
        $this->assertSame('custom', $serviceBlock->fresh()->settings['source']);
    }
}
