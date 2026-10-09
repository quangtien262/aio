<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsPage;
use App\Models\CmsService;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDetailRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_themes_show_ten_published_items_per_group_on_pages_service_and_article_details(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Recommendations', 'website_type' => 'corporate', 'active_theme_key' => 'XD0307']);
        CmsPage::create(['title' => 'About us', 'slug' => 'about-recommendations', 'body' => '<p>About us</p>', 'status' => 'published']);
        for ($i = 0; $i < 13; $i++) {
            CatalogProduct::create(['sku' => 'REC-'.$i, 'name' => 'Recommended product '.$i, 'slug' => 'recommended-product-'.$i, 'price' => 100, 'is_active' => true]);
            CmsService::create(['title' => 'Recommended service '.$i, 'slug' => 'recommended-service-'.$i, 'content' => '<p>Service detail</p>', 'status' => 'published', 'publish_at' => now()->subDays($i + 1)]);
            CmsPost::create(['title' => 'Recommended news '.$i, 'slug' => 'recommended-news-'.$i, 'body' => '<p>Article detail</p>', 'status' => 'published', 'publish_at' => now()->subDays($i + 1)]);
        }
        foreach (['draft', 'future', 'foreign'] as $kind) {
            foreach ([CmsService::class, CmsPost::class] as $model) {
                $model::create(['website_key' => $kind === 'foreign' ? 'other-website' : 'website-main', 'title' => 'Forbidden '.$kind, 'slug' => 'forbidden-'.class_basename($model).'-'.$kind, 'status' => $kind === 'draft' ? 'draft' : 'published', 'publish_at' => $kind === 'future' ? now()->addDay() : now()]);
            }
            CatalogProduct::create(['website_key' => $kind === 'foreign' ? 'other-website' : 'website-main', 'sku' => 'FORBIDDEN-'.$kind, 'name' => 'Forbidden '.$kind, 'slug' => 'forbidden-product-'.$kind, 'price' => 1, 'is_active' => $kind === 'foreign']);
        }
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            foreach (['/vi/ser/recommended-service-0', '/vi/n/recommended-news-0', '/vi/p/about-recommendations'] as $path) {
                $response = $this->get($path)->assertOk();
                foreach (['articleLatestProducts', 'articleServices', 'serviceSidebarPosts'] as $key) {
                    $items = $response->viewData($key);
                    $this->assertCount(10, $items, $theme.' '.$path.' '.$key);
                    $response->assertSee($items->first()['url'], false);
                }
                $document = new \DOMDocument;
                @$document->loadHTML($response->getContent());
                $xpath = new \DOMXPath($document);
                if (str_contains($path, '/p/')) {
                    $this->assertSame(3, $xpath->query('//*[@data-detail-recommendations]')->length, $theme.' page groups');
                    if ($theme === 'XD0309' && ($preview = getenv('PAGE_COLLECTION_PREVIEW'))) {
                        file_put_contents($preview, $response->getContent());
                    }
                }
                foreach ($xpath->query('//*[@data-detail-recommendations or @data-service-sidebar or @data-article-sidebar]') as $group) {
                    $this->assertSame(10, (int) $xpath->evaluate('count(.//a)', $group), $theme.' '.$path.' rendered group');
                }
                $response->assertDontSee('Forbidden draft')->assertDontSee('Forbidden future')->assertDontSee('Forbidden foreign');
                if (str_contains($path, '/ser/')) {
                    $this->assertFalse($response->viewData('articleServices')->contains(fn ($item) => str_ends_with($item['url'], '/recommended-service-0')), $theme);
                } elseif (str_contains($path, '/n/')) {
                    $this->assertFalse($response->viewData('serviceSidebarPosts')->contains(fn ($item) => str_ends_with($item['url'], '/recommended-news-0')), $theme);
                }
            }
        }
    }

    public function test_page_groups_are_hidden_independently(): void
    {
        SiteProfile::create(['site_name' => 'Page groups', 'website_type' => 'corporate', 'active_theme_key' => 'XD0309']);
        CmsPage::create(['title' => 'About', 'slug' => 'about-empty', 'status' => 'published']);
        $this->get('/vi/p/about-empty')->assertOk()->assertDontSee('data-detail-recommendations=', false);
        CatalogProduct::create(['sku' => 'PAGE', 'name' => 'Page product', 'slug' => 'page-product', 'price' => 1, 'is_active' => true]);
        $this->get('/vi/p/about-empty')->assertOk()->assertSee('data-detail-recommendations="products"', false)->assertDontSee('data-detail-recommendations="services"', false)->assertDontSee('data-detail-recommendations="news"', false);
        CmsService::create(['title' => 'Page service', 'slug' => 'page-service', 'status' => 'published']);
        $this->get('/vi/p/about-empty')->assertOk()->assertSee('data-detail-recommendations="services"', false)->assertDontSee('data-detail-recommendations="news"', false);
        CmsPost::create(['title' => 'Page news', 'slug' => 'page-news', 'status' => 'published']);
        $this->get('/vi/p/about-empty')->assertOk()->assertSee('data-detail-recommendations="news"', false);
    }

    public function test_empty_groups_are_hidden_independently(): void
    {
        SiteProfile::create(['site_name' => 'Empty groups', 'website_type' => 'corporate', 'active_theme_key' => 'XD0307']);
        CmsService::create(['title' => 'Service', 'slug' => 'empty-service', 'status' => 'published']);
        $this->get('/vi/ser/empty-service')->assertOk()->assertDontSee('data-detail-recommendations=', false)->assertDontSee('Liên kết nhanh');
        CatalogProduct::create(['sku' => 'ONLY', 'name' => 'Only product', 'slug' => 'only-product', 'price' => 1, 'is_active' => true]);
        $this->get('/vi/ser/empty-service')->assertOk()->assertSee('data-detail-recommendations="products"', false)->assertDontSee('data-detail-recommendations="services"', false)->assertDontSee('data-detail-recommendations="news"', false);
        CmsPost::create(['title' => 'Only news', 'slug' => 'only-news', 'status' => 'published']);
        $this->get('/vi/ser/empty-service')->assertOk()->assertSee('data-detail-recommendations="news"', false);
        $this->get('/vi/n/only-news')->assertOk()->assertSee('data-detail-recommendations="services"', false)->assertDontSee('data-detail-recommendations="news"', false);
    }
}
