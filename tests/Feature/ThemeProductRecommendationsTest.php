<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeProductRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_theme_product_page_renders_both_recommendation_groups(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Catalog', 'website_type' => 'ecommerce', 'active_theme_key' => 'BOOK920']);
        $products = collect();
        for ($index = 0; $index < 10; $index++) {
            $products->push(CatalogProduct::create([
                'name' => 'Recommendation item '.$index, 'slug' => 'recommendation-'.$index,
                'sku' => 'REC-'.$index, 'price' => 100000, 'original_price' => 120000,
                'stock' => 10, 'is_active' => true,
                'image_url' => '/theme-demo/xd-shared/travel-2.jpg',
            ]));
        }
        foreach (glob(base_path('themes/*/views/product.blade.php')) as $file) {
            $theme = basename(dirname($file, 2));
            $profile->update(['active_theme_key' => $theme]);
            $response = $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $products->first()->slug]));
            $response->assertOk()->assertViewIs('theme-'.strtolower($theme).'::product');
            $related = collect($response->viewData('relatedProducts'));
            $latest = collect($response->viewData('latestProducts'));
            $this->assertCount(4, $related, $theme);
            $this->assertCount(4, $latest, $theme);
            $this->assertCount(8, $related->concat($latest)->unique('url'), $theme);
            foreach ($related->concat($latest) as $item) {
                $response->assertSee($item['title'])->assertSee($item['url'], false);
            }
            $this->assertStringNotContainsString('catalog.latest', $response->getContent(), $theme);
            if ($folder = getenv('THEME_RECOMMENDATIONS_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$theme.'.html', $response->getContent());
            }
        }
    }

    public function test_shared_recommendations_use_the_requested_language(): void
    {
        app()->setLocale('en');
        $html = view('themes.common.product-recommendations', [
            'relatedProducts' => [['url' => '/en/product', 'title' => 'Product', 'price' => 0]],
            'latestProducts' => [['url' => '/en/new-product', 'title' => 'New product', 'price' => 120000]],
        ])->render();
        $this->assertStringContainsString('Related products', $html);
        $this->assertStringContainsString('New arrivals', $html);
        $this->assertStringContainsString('Contact for pricing', $html);
    }
}
