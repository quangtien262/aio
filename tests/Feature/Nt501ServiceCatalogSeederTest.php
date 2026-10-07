<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsService;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Database\Seeders\Nt501ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Nt501ServiceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_legacy_demo_sections_without_duplicates(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('NT501', $generator->presetsForTheme('NT501')[0]['key']);
        foreach (CmsService::orderBy('sort_order')->skip(4)->take(2)->get() as $service) {
            $service->images()->delete();
            $service->delete();
        }
        $generic = CatalogCategory::create(['name' => 'Sản phẩm và thiết bị', 'slug' => 'nt501-san-pham', 'is_active' => true]);
        CatalogProduct::query()->update(['catalog_category_id' => $generic->id]);
        CatalogCategory::where('id', '!=', $generic->id)->delete();
        Site::create(['domain' => 'another.demo.test', 'website_key' => 'another', 'theme_key' => 'NT501', 'status' => 'active']);
        Site::create(['domain' => 'other.demo.test', 'website_key' => 'other', 'theme_key' => 'DN351', 'status' => 'active']);

        $this->seed(Nt501ServiceCatalogSeeder::class);
        $markerCount = ThemeDemoRecord::withoutGlobalScope('current_website')->count();
        $this->seed(Nt501ServiceCatalogSeeder::class);

        $this->assertSame($markerCount, ThemeDemoRecord::withoutGlobalScope('current_website')->count());
        foreach (['website-main', 'another'] as $website) {
            $this->assertSame(6, CmsService::query()->forWebsite($website)->count());
            $this->assertSame(3, CatalogProduct::query()->forWebsite($website)->count());
        }
        $this->assertSame(0, CmsService::query()->forWebsite('other')->count());
        $this->assertSame(0, CatalogCategory::query()->forWebsite('other')->count());
        $this->assertSame(0, $generic->products()->count());
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $services = $xpath->query('//section[@id="dich-vu"]//article');
        $this->assertSame(6, $services->length);
        foreach ($services as $card) {
            $image = $xpath->query('.//img', $card)->item(0);
            $this->assertFileExists(public_path(parse_url($image->getAttribute('src'), PHP_URL_PATH)));
            $this->get($xpath->query('.//a', $card)->item(0)->getAttribute('href'))->assertOk();
        }
        $categories = $xpath->query('//section[contains(@class,"nt-stats")]//article');
        $this->assertSame(3, $categories->length);
        foreach (['Ghế nội thất', 'Bàn làm việc', 'Đèn trang trí'] as $index => $name) {
            $this->assertStringContainsString($name, $categories->item($index)->textContent);
        }
    }

    public function test_seeder_preserves_manual_content_with_matching_slugs(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'active_theme_key' => 'NT501']);
        $service = CmsService::create(['title' => 'Dịch vụ riêng', 'slug' => Str::slug('NT501-Tư vấn không gian'), 'status' => 'draft']);
        $category = CatalogCategory::create(['name' => 'Danh mục riêng', 'slug' => 'manual', 'is_active' => true]);
        $product = CatalogProduct::create(['name' => 'Ghế riêng', 'slug' => Str::slug('NT501-Ghế nội thất'), 'sku' => 'MANUAL-CHAIR', 'catalog_category_id' => $category->id, 'price' => 987654]);

        $this->seed(Nt501ServiceCatalogSeeder::class);

        $this->assertSame('Dịch vụ riêng', $service->fresh()->title);
        $this->assertSame('draft', $service->fresh()->status);
        $this->assertSame(0, $service->images()->count());
        $this->assertSame($category->id, $product->fresh()->catalog_category_id);
        $this->assertSame('987654.00', $product->fresh()->price);
        $this->assertFalse(ThemeDemoRecord::where('model_type', CatalogProduct::class)->where('model_id', $product->id)->exists());
    }
}
