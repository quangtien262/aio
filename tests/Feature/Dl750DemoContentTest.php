<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\ThemeDemoRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dl750DemoContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_categories_are_seeded_repeatably_and_render_with_working_links(): void
    {
        $custom = CatalogCategory::create(['name' => 'Danh mục riêng', 'slug' => 'custom-category', 'is_active' => false]);
        $other = CatalogCategory::create(['website_key' => 'other-site', 'name' => 'Danh mục website khác', 'slug' => 'other-category']);
        $generator = app(ThemeDemoContentGenerator::class);
        $expected = ['Lều và mái che', 'Túi ngủ và đệm', 'Balo du lịch', 'Đèn dã ngoại', 'Bàn ghế cắm trại', 'Bếp và dụng cụ nấu', 'Trang phục trekking', 'Phụ kiện dã ngoại'];

        foreach ([1, 2] as $run) {
            $generator->generate('DL750', 'dl750-complete');
            $ids = ThemeDemoRecord::where('theme_key', 'DL750')->where('model_type', CatalogCategory::class)->pluck('model_id');
            $categories = CatalogCategory::whereIn('id', $ids)->orderBy('sort_order')->get();
            $this->assertSame($expected, $categories->pluck('name')->all());
            $this->assertSame(9, CatalogCategory::count());
            $this->assertSame(3, CatalogProduct::count());
            foreach (['Lều dã ngoại' => 'Lều và mái che', 'Balo du lịch' => 'Balo du lịch', 'Đèn cắm trại' => 'Đèn dã ngoại'] as $product => $category) {
                $this->assertSame($categories->firstWhere('name', $category)->id, CatalogProduct::where('name', $product)->firstOrFail()->catalog_category_id);
            }
        }

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $section = $xpath->query('//section[@id="danh-muc"]')->item(0);
        $this->assertNotNull($section);
        $this->assertSame(8, $xpath->query('.//a', $section)->length);
        foreach ($categories as $category) {
            $this->assertStringContainsString($category->name, $section->textContent);
            $this->assertStringContainsString($category->description, $section->textContent);
            $url = route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]);
            $links = iterator_to_array($xpath->query('.//a', $section));
            $this->assertContains(parse_url($url, PHP_URL_PATH), array_map(fn ($link) => parse_url($link->getAttribute('href'), PHP_URL_PATH), $links));
            $this->get($url)->assertOk()->assertSee($category->name);
        }

        $generator->delete('DL750');
        $this->assertSame(1, CatalogCategory::count());
        $this->assertSame(0, ThemeDemoRecord::where('theme_key', 'DL750')->count());
        $this->assertDatabaseHas('catalog_categories', ['id' => $custom->id, 'name' => 'Danh mục riêng']);
        $this->assertDatabaseHas('catalog_categories', ['id' => $other->id, 'website_key' => 'other-site']);
    }

    public function test_theme_without_category_definitions_keeps_its_generic_category(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('BZ501', 'bz501-complete');

        $category = CatalogCategory::sole();
        $this->assertSame('Sản phẩm và thiết bị', $category->name);
        $this->assertSame('bz501-san-pham', $category->slug);
        $this->assertSame(3, CatalogProduct::where('catalog_category_id', $category->id)->count());
    }
}
