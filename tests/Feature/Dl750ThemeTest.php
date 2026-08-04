<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dl750ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dl750_is_registered_with_expected_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'DL750');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/DL750/preview-dl750.svg'));
        $this->assertFileExists(public_path('theme-previews/DL750/cover-dl750.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('DL750'));
        $this->assertSame([
            'hero_slider',
            'dl750_categories',
            'dl750_about',
            'dl750_services',
            'dl750_reasons',
            'dl750_products',
            'dl750_gallery',
            'dl750_news',
            'dl750_faq',
            'dl750_partners',
        ], collect($builder->availableBlocks('DL750'))->pluck('block_type')->all());
        $this->assertStringContainsString('translateY(-34px)', file_get_contents(base_path('themes/DL750/views/partials/styles.blade.php')));
    }

    public function test_dl750_renders_database_content_and_branding_without_reference_contacts(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Dã Ngoại Rừng Xanh',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DL750',
            'branding' => [
                'logo_url' => '/storage/branding/rung-xanh.svg',
                'support_hotline' => '0887 750 750',
                'support_email' => 'donghanh@rungxanh.test',
                'support_location' => '25 Đường Sương Mai, Đà Lạt',
                'copyright_text' => 'Bản quyền thuộc về Dã Ngoại Rừng Xanh.',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Lều trại cao cấp', 'slug' => 'leu-trai-cao-cap', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Lều trekking Rừng Xanh', 'slug' => 'leu-trekking-rung-xanh', 'sku' => 'DL750-001', 'price' => 2750000, 'stock' => 9, 'image_url' => '/theme-demo/ec903/camping-tent.webp', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        CmsService::query()->create(['title' => 'Thuê bộ cắm trại trọn gói', 'slug' => 'thue-bo-cam-trai', 'status' => 'published', 'summary' => 'Trang bị đã kiểm tra kỹ trước mỗi chuyến đi.', 'content' => '<p>Nội dung dịch vụ.</p>', 'publish_at' => now(), 'is_featured' => true, 'is_highlight' => true]);
        CmsPost::query()->create(['title' => 'Kinh nghiệm dựng lều khi trời mưa', 'slug' => 'kinh-nghiem-dung-leu', 'status' => 'published', 'excerpt' => 'Các bước chuẩn bị để khu trại luôn khô ráo.', 'body' => '<p>Nội dung bài viết.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        CmsPartner::query()->create(['title' => 'Peak Trail Việt Nam', 'slug' => 'peak-trail-viet-nam', 'status' => 'published', 'image_url' => '/storage/partners/peak-trail.svg', 'publish_at' => now(), 'is_featured' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'DL750', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/rung-xanh.svg', false)
            ->assertSee('0887 750 750')
            ->assertSee('donghanh@rungxanh.test')
            ->assertSee('25 Đường Sương Mai, Đà Lạt')
            ->assertSee('Bản quyền thuộc về Dã Ngoại Rừng Xanh.')
            ->assertSee('Lều trại cao cấp')
            ->assertSee('Lều trekking Rừng Xanh')
            ->assertSee('Thuê bộ cắm trại trọn gói')
            ->assertSee('Kinh nghiệm dựng lều khi trời mưa')
            ->assertSee('Peak Trail Việt Nam')
            ->assertSee('data-block-type="dl750_faq"', false)
            ->assertDontSee('1900 6750')
            ->assertDontSee('support@sapo.vn')
            ->assertDontSee('70 Lữ Gia');
    }
}
