<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt502ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_nt502_is_registered_with_expected_builder_blocks(): void
    {
        $theme=app(ThemeRegistry::class)->all()->firstWhere('key','NT502');$this->assertNotNull($theme);$this->assertSame('ecommerce',$theme['website_type']);$builder=app(LandingPageBuilder::class);$this->assertTrue($builder->supportsTheme('NT502'));
        $this->assertSame(['hero_slider','nt502_categories','nt502_about','nt502_promotion','nt502_living_room','nt502_bedroom','testimonials','nt502_latest_news'],collect($builder->availableBlocks('NT502'))->pluck('block_type')->all());
        foreach(['nt502_promotion','nt502_living_room','nt502_bedroom'] as $type){$block=collect($builder->availableBlocks('NT502'))->firstWhere('block_type',$type);$this->assertSame('cms_products',data_get($block,'settings_schema.source.options.0.value'));$this->assertArrayHasKey('category_id',data_get($block,'settings_schema'));}
    }

    public function test_nt502_demo_and_storefront_render(): void
    {
        $provider=app(ThemeDemoContentProviderRegistry::class)->forTheme('NT502');$this->assertNotNull($provider);$result=$provider->generate('nt502-dola-furniture');$this->assertSame(10,data_get($result,'counts.products'));$this->assertDatabaseHas('site_banners',['theme_key'=>'NT502','placement'=>'nt502-hero-slider']);$this->assertDatabaseHas('landing_pages',['theme_key'=>'NT502','slug'=>'home','is_home'=>true]);
        $this->get(route('site.home',['locale'=>'vi']))->assertOk()->assertSee('data-block-type="nt502_living_room"',false)->assertSee('data-block-type="nt502_latest_news"',false)->assertSee('DOLA FURNITURE');
        $category=CatalogCategory::query()->firstOrFail();$product=CatalogProduct::query()->firstOrFail();$post=CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category',['locale'=>'vi','slug'=>$category->slug]))->assertOk()->assertSee($category->name);$this->get(route('site.catalog.product',['locale'=>'vi','slug'=>$product->slug]))->assertOk()->assertSee($product->name);$this->get(route('site.catalog.search',['locale'=>'vi','q'=>'Gỗ']))->assertOk()->assertSee('Kết quả tìm kiếm');$this->get(route('site.cart.index',['locale'=>'vi']))->assertOk()->assertSee('Giỏ hàng');$this->get(route('site.blog.index',['locale'=>'vi']))->assertOk()->assertSee('Tin tức');$this->get(route('site.blog.show',['locale'=>'vi','slug'=>$post->slug]))->assertOk()->assertSee($post->title);$this->get(route('site.contact',['locale'=>'vi']))->assertOk()->assertSee('Liên hệ Dola Furniture');
        $this->assertCount(8,LandingPage::query()->where('theme_key','NT502')->where('is_home',true)->firstOrFail()->blocks);
    }

    public function test_nt502_renders_named_landing_route(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NT502')?->generate('nt502-dola-furniture');$page=LandingPage::query()->create(['website_key'=>'website-main','theme_key'=>'NT502','page_type'=>'landing','slug'=>'living-room-sale','status'=>'published','template'=>'home','is_home'=>false,'sort_order'=>1,'settings'=>[],'published_at'=>now()]);LandingPageData::query()->create(['landing_page_id'=>$page->id,'locale'=>'vi','title'=>'Living Room Sale']);$block=LandingPageBlock::query()->create(['landing_page_id'=>$page->id,'theme_key'=>'NT502','block_type'=>'nt502_about','sort_order'=>0,'is_visible'=>true,'anchor_id'=>'gioi-thieu','settings'=>[],'media'=>[]]);LandingPageBlockData::query()->create(['landing_page_block_id'=>$block->id,'locale'=>'vi','title'=>'Dola Furniture','content'=>json_encode(['items'=>[['title'=>'Miễn phí vận chuyển','summary'=>'Nội thành TP.HCM']]],JSON_UNESCAPED_UNICODE)]);
        $outputBufferLevel = ob_get_level();
        $response=$this->get(route('site.landing.show',['locale'=>'vi','slug'=>'living-room-sale']));$response->assertOk()->assertSee('data-block-type="nt502_about"',false);
        while (ob_get_level() > $outputBufferLevel) ob_end_clean();
        $this->assertSame($outputBufferLevel, ob_get_level());
    }
}
