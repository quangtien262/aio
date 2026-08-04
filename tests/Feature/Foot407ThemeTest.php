<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot407ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot407_is_registered_with_expected_blocks(): void
    {
        $theme=app(ThemeRegistry::class)->all()->firstWhere('key','FOOT407');
        $this->assertNotNull($theme);$this->assertSame('ecommerce',$theme['website_type']);$this->assertFileExists(public_path('theme-previews/FOOT407/cover-foot407.svg'));
        $builder=app(LandingPageBuilder::class);$this->assertTrue($builder->supportsTheme('FOOT407'));
        $this->assertSame(['hero_slider','foot407_benefits','foot407_product_tabs','foot407_why_choose','foot407_gallery','foot407_media_posts','foot407_knowledge_posts','foot407_partners'],collect($builder->availableBlocks('FOOT407'))->pluck('block_type')->all());
        $this->assertStringContainsString('translateY(-34px)',file_get_contents(base_path('themes/FOOT407/views/partials/styles.blade.php')));
    }

    public function test_foot407_renders_database_branding_catalog_and_copyright(): void
    {
        SiteProfile::query()->create(['site_name'=>'An Tâm Wellness','website_type'=>'ecommerce','active_theme_key'=>'FOOT407','branding'=>['logo_url'=>'/storage/branding/an-tam.svg','support_hotline'=>'0866 778 899','support_email'=>'cskh@antam.test','support_location'=>'32 Phố An Nhiên, Hà Nội','copyright_text'=>'Bản quyền nội dung thuộc về An Tâm Wellness.']]);
        $category=CatalogCategory::query()->create(['name'=>'Thảo dược chọn lọc','slug'=>'thao-duoc-chon-loc','is_active'=>true]);CatalogProduct::query()->create(['catalog_category_id'=>$category->id,'name'=>'Hộp quà sức khỏe','slug'=>'hop-qua-suc-khoe','sku'=>'FOOT407-001','price'=>790000,'stock'=>8,'image_url'=>'/theme-demo/ec903/food-source.png','is_featured'=>true,'is_active'=>true]);app(LandingPageBuilder::class)->resolveHome('website-main','FOOT407',true);
        $this->get(route('site.home',['locale'=>'vi']))->assertOk()->assertSee('/storage/branding/an-tam.svg',false)->assertSee('0866 778 899')->assertSee('cskh@antam.test')->assertSee('32 Phố An Nhiên, Hà Nội')->assertSee('Bản quyền nội dung thuộc về An Tâm Wellness.')->assertSee('Hộp quà sức khỏe')->assertSee('data-block-type="foot407_product_tabs"',false)->assertDontSee('1900 9477')->assertDontSee('admin@demo037062.web30s.vn')->assertDontSee('196 Nguyễn Đình Chiểu');
    }
}
