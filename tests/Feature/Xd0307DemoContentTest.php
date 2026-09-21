<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\{CmsMenu, CmsPost, CmsService, CmsTeamMember, CmsTestimonial, LandingPage, LandingPageBlockData, Site, SiteProfile};
use App\Support\LegacyTextEncoding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0307DemoContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_generation_pipeline_keeps_complete_theme_menu_and_local_images(): void
    {
        $generator = app(\App\Core\Themes\ThemeDemoContentGenerator::class);
        foreach (range(1, 2) as $run) {
            $result = $generator->generate('XD0307', 'xd0307-cleaning-services');
            $this->assertSame(4, $result['counts']['products']);
            $this->assertSame(3, $result['counts']['projects']);
            $menu = CmsMenu::where('name', 'XD0307 Main Menu')->firstOrFail();
            $this->assertSame(['Trang chủ', 'Giới thiệu', 'Dịch vụ', 'Sản phẩm', 'Dự án', 'Tin tức', 'Liên hệ'], array_column($menu->items, 'label'));
            foreach ($menu->items as $item) {
                $this->get($item['url'])->assertSuccessful();
            }
            foreach (\App\Models\CmsProject::all() as $project) {
                $this->get(route('site.projects.show', ['locale' => 'vi', 'slug' => $project->slug]))->assertOk()->assertSee($project->title);
            }
            if (getenv('XD0307_PREVIEW') && $run === 1) {
                file_put_contents(storage_path('framework/testing/xd0307-home.html'), $this->get(route('site.home', ['locale' => 'vi']))->getContent());
            }
            foreach (\App\Models\CatalogProduct::all() as $product) {
                $this->assertFileExists(public_path($product->image_url));
                $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
            }
            foreach (\App\Models\SiteBanner::where('theme_key', 'XD0307')->get() as $banner) {
                $this->assertFileExists(public_path($banner->image_url));
            }
            foreach (CmsPost::all() as $post) {
                $this->assertNotNull($post->featuredMedia);
                $this->assertFileExists(public_path($post->featuredMedia->file_url));
            }
            $this->assertSame(4, \App\Models\CatalogProduct::count());
        }
    }

    public function test_demo_is_complete_utf8_and_safe_to_regenerate(): void
    {
        $custom = CmsService::create(['title' => 'Dịch vụ riêng', 'slug' => 'custom-service', 'status' => 'draft']);
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0307');
        foreach (range(1, 2) as $run) {
            $result = $provider->generate($provider->defaultPreset());
            $this->assertSame(3, $result['counts']['posts']);
            $this->assertSame(3, CmsTeamMember::count());
            $this->assertSame(3, CmsTestimonial::count());
            $this->assertSame(3, CmsPost::count());
            $this->assertSame(5, CmsService::count());
            foreach ([CmsService::all(), CmsTeamMember::all(), CmsTestimonial::all(), CmsPost::all(), LandingPageBlockData::all(), CmsMenu::all()] as $records) {
                $json = $records->toJson(JSON_UNESCAPED_UNICODE);
                $this->assertTrue(mb_check_encoding($json, 'UTF-8'));
                $this->assertSame($json, app(LegacyTextEncoding::class)->repair($json));
                $this->assertStringNotContainsString('Bizmax', $json);
                $this->assertStringNotContainsString('tư vấn pháp lý', $json);
            }
            $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
            foreach (['Vệ sinh nhà ở định kỳ', 'Nguyễn Minh Anh', 'Lên lịch vệ sinh nhà ở theo từng khu vực'] as $text) {
                $response->assertSee($text);
            }
            $page = LandingPage::where('theme_key', 'XD0307')->firstOrFail();
            $this->assertCount(9, $page->blocks);
        }
        $provider->delete();
        $this->assertDatabaseHas('cms_services', ['id' => $custom->id]);
        $this->assertSame(0, CmsTeamMember::count());
        $this->assertSame(0, CmsTestimonial::count());
        $this->assertSame(0, CmsPost::count());
    }

    public function test_legacy_encoding_repair_supports_xd0307(): void
    {
        Site::create(['domain' => 'xd0307.test', 'website_key' => 'cleaning', 'theme_key' => 'XD0307', 'status' => 'active']);
        $text = 'Dịch vụ vệ sinh';
        $bad = mb_convert_encoding(mb_convert_encoding($text, 'UTF-8', 'Windows-1252'), 'UTF-8', 'Windows-1252');
        $profile = SiteProfile::create(['website_key' => 'cleaning', 'site_name' => $bad, 'active_theme_key' => 'XD0307']);
        $this->artisan('themes:repair-encoding', ['domain' => 'xd0307.test', '--write' => true])->assertSuccessful();
        $this->assertDatabaseHas('site_profiles', ['id' => $profile->id, 'site_name' => $text]);
    }
}
