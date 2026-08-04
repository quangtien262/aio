<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot406ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot406_is_registered_with_expected_blocks(): void
    {
        $theme=app(ThemeRegistry::class)->all()->firstWhere('key','FOOT406');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce',$theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/FOOT406/preview-foot406.svg'));
        $builder=app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('FOOT406'));
        $this->assertSame(['hero_slider','foot406_categories','foot406_promo_products','foot406_promo_duo','foot406_favorites','foot406_latest_posts','foot406_partners'],collect($builder->availableBlocks('FOOT406'))->pluck('block_type')->all());
        $this->assertStringContainsString('translateY(-34px)',file_get_contents(base_path('themes/FOOT406/views/partials/styles.blade.php')));
    }

    public function test_foot406_renders_database_catalog_and_contacts_without_reference_contacts(): void
    {
        SiteProfile::query()->create(['site_name'=>'Tiệm Nước Mát','website_type'=>'ecommerce','active_theme_key'=>'FOOT406','branding'=>['logo_url'=>'/storage/branding/tiem-nuoc.svg','support_hotline'=>'0877 321 654','support_email'=>'hello@tiemnuoc.test','support_location'=>'18 Đường Hoa Phượng, Đà Nẵng','business_hours'=>'07:30 - 22:00 mỗi ngày']]);
        $category=CatalogCategory::query()->create(['name'=>'Trà trái cây','slug'=>'tra-trai-cay','is_active'=>true]);
        CatalogProduct::query()->create(['catalog_category_id'=>$category->id,'name'=>'Trà đào thanh mát','slug'=>'tra-dao-thanh-mat','sku'=>'FOOT406-001','price'=>39000,'stock'=>20,'image_url'=>'/theme-demo/ec903/deal-tea.webp','is_featured'=>true,'is_active'=>true]);
        app(LandingPageBuilder::class)->resolveHome('website-main','FOOT406',true);
        $this->get(route('site.home',['locale'=>'vi']))->assertOk()->assertSee('/storage/branding/tiem-nuoc.svg',false)->assertSee('0877 321 654')->assertSee('hello@tiemnuoc.test')->assertSee('18 Đường Hoa Phượng, Đà Nẵng')->assertSee('07:30 - 22:00 mỗi ngày')->assertSee('Trà đào thanh mát')->assertSee('Trà trái cây')->assertSee('data-block-type="foot406_promo_products"',false)->assertDontSee('1900 9477')->assertDontSee('admin@demo037089.web30s.vn')->assertDontSee('196 Nguyễn Đình Chiểu');
    }
}
