<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dl750DiningPresetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_choose_dining_presets_from_both_theme_and_domain_dialogs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@aio.local')->firstOrFail(), 'admin');
        $themes = $this->getJson('/admin/api/themes')->assertOk()->json('data');
        $presets = collect($themes)->firstWhere('key', 'DL750')['demo']['presets'];
        $this->assertSame(['dl750-complete', 'dl750-cafe', 'dl750-restaurant'], array_column($presets, 'key'));
        $this->getJson('/admin/api/site-mappings')->assertOk()->assertJsonPath('meta.demo_presets_by_theme.DL750', $presets);
    }

    public function test_dining_presets_generate_scoped_content_and_can_switch_back_to_camping(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $this->assertSame(['dl750-complete', 'dl750-cafe', 'dl750-restaurant'], array_column($generator->presetsForTheme('DL750'), 'key'));
        $manual = CatalogProduct::create(['name' => 'Sản phẩm tự nhập', 'slug' => 'manual', 'sku' => 'manual', 'price' => 1, 'is_active' => true]);
        app(SiteContext::class)->set(null, 'other-website');
        $other = CatalogProduct::create(['name' => 'Website khác', 'slug' => 'other', 'sku' => 'other', 'price' => 1, 'is_active' => true]);
        app(SiteContext::class)->set(null, 'website-main');
        foreach (['dl750-cafe' => 'Mộc Garden Café', 'dl750-restaurant' => 'Mộc Garden Restaurant'] as $preset => $brand) {
            $result = $generator->generate('DL750', $preset);
            $this->assertSame($preset, $result['preset']['key']);
            $this->assertSame($brand, SiteProfile::first()->site_name);
            $this->assertSame(8, CatalogProduct::where('slug', 'like', 'dl750-%')->count());
            foreach (CatalogCategory::all() as $category) {
                $this->assertFileExists(public_path(ltrim($category->image_url, '/')));
            }
            $response = $this->get('/vi')->assertOk()->assertSee($brand)->assertSee('Khám phá thực đơn')->assertSee('Gợi ý hôm nay')->assertDontSee('OUTDOOR SALE')->assertDontSee('Trải nghiệm an toàn');
            foreach (CatalogProduct::where('slug', 'like', 'dl750-%')->get() as $product) {
                $this->assertLessThan(500000, (float) $product->price);
                $this->get('/vi/san-pham/'.$product->slug)->assertOk()->assertSee($product->name);
            }
            if ($folder = getenv('DL750_DINING_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$preset.'.html', $response->getContent());
            }
        }
        $generator->generate('DL750', 'dl750-complete');
        $this->assertSame('/theme-demo/complete/logo-dl750.svg', data_get(SiteProfile::first()->branding, 'logo_url'));
        $this->assertSame(8, CatalogCategory::count());
        $this->assertSame(3, CatalogProduct::where('slug', 'like', 'dl750-%')->count());
        $this->assertDatabaseHas('catalog_products', ['id' => $manual->id, 'name' => 'Sản phẩm tự nhập']);
        $this->assertDatabaseHas('catalog_products', ['id' => $other->id, 'website_key' => 'other-website']);
    }
}
