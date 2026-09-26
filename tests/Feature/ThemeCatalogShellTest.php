<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\Customer;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeCatalogShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_repaired_themes_keep_cart_and_checkout_in_their_own_layout(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Shop', 'website_type' => 'ecommerce', 'active_theme_key' => 'FOOT403']);
        $product = CatalogProduct::create(['name' => 'Family combo', 'slug' => 'family-combo', 'sku' => 'COMBO', 'price' => 250000, 'stock' => 10, 'is_active' => true]);
        $this->actingAs(Customer::factory()->create(), 'customer');
        foreach (['FOOT403' => 'dr-header', 'BDS702' => 'b702-header', 'SHOP606' => 's606-header'] as $theme => $header) {
            $profile->update(['active_theme_key' => $theme]);
            $this->post(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), ['quantity' => 1])->assertRedirect();
            $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee($header, false)->assertSee('Family combo');
            $this->get(route('site.checkout.index', ['locale' => 'vi']))->assertOk()->assertSee($header, false)
                ->assertSee('name="customer_name"', false)->assertDontSee('header.s601-header', false);
        }
    }

    public function test_all_catalog_pages_keep_the_home_header_and_footer(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Shell audit', 'website_type' => 'ecommerce', 'active_theme_key' => 'FOOT403']);
        $category = CatalogCategory::create(['name' => 'Audit category', 'slug' => 'audit-category', 'is_active' => true]);
        $product = CatalogProduct::create(['category_id' => $category->id, 'name' => 'Audit product', 'slug' => 'audit-product', 'sku' => 'SHELL-1', 'price' => 250000, 'stock' => 10, 'is_active' => true, 'image_url' => '/theme-demo/complete/logo-foot403.svg', 'detail_content' => '<p>Actual product content.</p>']);
        $signature = function ($html, $tag) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $nodes = $dom->getElementsByTagName($tag);
            $node = $nodes->item($tag === 'footer' ? $nodes->length - 1 : 0);

            return $node?->getAttribute('class');
        };
        $failures = [];
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            $home = $this->get('/vi')->assertOk()->getContent();
            foreach (['product' => '/vi/san-pham/audit-product', 'category' => '/vi/danh-muc/audit-category', 'search' => '/vi/tim-kiem', 'cart' => '/vi/gio-hang'] as $key => $url) {
                if ($key === 'category') {
                    $url = route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]);
                }
                $response = $this->get($url)->assertOk();
                foreach (['header', 'footer'] as $tag) {
                    if ($signature($home, $tag) !== $signature($response->getContent(), $tag)) {
                        $failures[] = $theme.' '.$key.' '.$tag;
                    }
                }
                if ($folder = getenv('CATALOG_SHELL_PREVIEW')) {
                    if (! is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }file_put_contents($folder.'/'.$theme.'-'.$key.'.html', $response->getContent());
                }
            }
        }
        $this->assertSame([], $failures);
    }
}
