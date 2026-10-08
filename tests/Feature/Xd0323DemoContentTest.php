<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0323DemoContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fills_home_rows_repeatably_and_preserves_manual_team_member(): void
    {
        $manual = CmsTeamMember::create(['name' => 'Nhân sự riêng', 'slug' => 'manual-member', 'status' => 'draft']);
        $generator = app(ThemeDemoContentGenerator::class);
        $preset = $generator->presetsForTheme('XD0323')[0]['key'];
        foreach ([1, 2] as $run) {
            $result = $generator->generate('XD0323', $preset);
            $this->assertSame(6, $result['counts']['categories']);
            $this->assertSame(8, $result['counts']['products']);
            $this->assertSame(4, $result['counts']['team_members']);
            $this->assertSame(6, CatalogCategory::count());
            $this->assertSame(8, CatalogProduct::count());
            $this->assertSame(5, CmsTeamMember::count());
        }
        foreach (CatalogCategory::all() as $category) {
            $this->assertFileExists(public_path($category->image_url));
            $this->assertGreaterThan(0, CatalogProduct::where('catalog_category_id', $category->id)->count());
        }
        foreach (CatalogProduct::all() as $product) {
            $this->assertFileExists(public_path($product->image_url));
        }
        foreach (CmsTeamMemberImage::all() as $image) {
            $this->assertFileExists(public_path($image->image_url));
        }
        $response = $this->get('/vi')->assertOk()->assertDontSee('Nhân sự riêng');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['danh-muc' => ['a', 6], 'san-pham' => ['article', 8], 'doi-ngu' => ['article', 4]] as $id => [$tag, $count]) {
            $nodes = $xpath->query('//section[@id="'.$id.'"]//'.$tag);
            $this->assertSame($count, $nodes->length, $id);
            if ($id !== 'doi-ngu') {
                foreach ($nodes as $node) {
                    $link = $tag === 'a' ? $node : $xpath->query('.//a', $node)->item(0);
                    $this->get($link->getAttribute('href'))->assertOk();
                }
            }
        }
        $this->assertSame(1, $xpath->query('//div[@class="xd323-newsletter__heading"]/i[@aria-hidden="true"]')->length);
        $generator->delete('XD0323');
        $this->assertDatabaseHas('cms_team_members', ['id' => $manual->id, 'name' => 'Nhân sự riêng']);
    }
}
