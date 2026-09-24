<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dl750ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_categories_have_distinct_local_images_on_homepage(): void
    {
        app(\App\Core\Themes\Demo\ThemeDemoContentProviderRegistry::class)->forTheme('DL750')->generate('dl750-complete');
        $categories = CatalogCategory::query()->where('slug', 'like', 'dl750-%')->get();
        $this->assertCount(8, $categories);
        $this->assertCount(8, $categories->pluck('image_url')->unique());
        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        foreach ($categories as $category) {
            $this->assertFileExists(public_path(ltrim($category->image_url, '/')));
            $response->assertSee('src="'.$category->image_url.'"', false)->assertSee($category->name);
        }
        if ($path = getenv('DL750_CATEGORIES_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }

    public function test_product_detail_renders_gallery_purchase_and_related_content(): void
    {
        SiteProfile::create(['site_name' => 'Forest Camp', 'website_type' => 'ecommerce', 'active_theme_key' => 'DL750']);
        $category = CatalogCategory::create(['name' => 'Balo du lịch', 'slug' => 'balo', 'is_active' => true]);
        $product = CatalogProduct::create([
            'catalog_category_id' => $category->id, 'name' => 'Balo du lịch', 'slug' => 'balo-du-lich',
            'sku' => 'DL750-BALO', 'price' => 500000, 'original_price' => 650000, 'stock' => 10, 'is_active' => true,
            'image_url' => '/theme-demo/xd-shared/travel-2.jpg',
            'short_description' => 'Hành trang gọn nhẹ cho chuyến đi của bạn.',
            'detail_content' => '<h2>Thiết kế cho hành trình</h2><p>Thông tin chi tiết từ CMS.</p>',
            'highlights' => "Dễ sắp xếp hành lý\nThuận tiện mang theo", 'usage_terms' => 'Bảo quản nơi khô ráo.',
        ]);
        $product->images()->create(['image_url' => '/theme-demo/xd-shared/travel-3.jpg', 'sort_order' => 0]);
        CatalogProduct::create(['catalog_category_id' => $category->id, 'name' => 'Balo đồng hành', 'slug' => 'balo-dong-hanh', 'sku' => 'RELATED', 'price' => 450000, 'is_active' => true]);
        CatalogProduct::create(['catalog_category_id' => $category->id, 'name' => 'Sản phẩm ẩn', 'slug' => 'hidden-product', 'sku' => 'HIDDEN', 'is_active' => false]);

        $response = $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))
            ->assertOk()->assertSee('data-dl-main-image', false)->assertSee('data-dl-thumbnail', false)
            ->assertSee('DL750-BALO')->assertSee('500.000đ')->assertSee('650.000đ')->assertSee('-23%')
            ->assertSee('Dễ sắp xếp hành lý')->assertSee('Thông tin chi tiết từ CMS.')
            ->assertSee('Bảo quản nơi khô ráo.')->assertSee('Balo đồng hành')->assertDontSee('Sản phẩm ẩn')
            ->assertSee(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), false);
        if (getenv('DL750_PRODUCT_PREVIEW')) {
            file_put_contents(getenv('DL750_PRODUCT_PREVIEW'), $response->getContent());
        }
        $this->from(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))
            ->post(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), ['quantity' => 2])
            ->assertRedirect()->assertSessionHas('cart_success');
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))
            ->assertOk()->assertSee('role="status"', false);
    }

    public function test_product_detail_handles_missing_optional_content(): void
    {
        SiteProfile::create(['site_name' => 'Forest Camp', 'website_type' => 'ecommerce', 'active_theme_key' => 'DL750']);
        $product = CatalogProduct::create(['name' => 'Trang bị mới', 'slug' => 'trang-bi-moi', 'sku' => 'NEW', 'price' => 0, 'is_active' => true]);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))
            ->assertOk()->assertSee('Liên hệ báo giá')
            ->assertSee('Liên hệ với chúng tôi để biết thêm thông tin về sản phẩm.')
            ->assertDontSee('data-dl-thumbnail aria-pressed', false)->assertDontSee('class="dl-pdp-discount"', false)
            ->assertDontSee('id="dl-related-title"', false);
    }

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
        CmsProject::query()->create(['title' => 'Hành trình Rừng Xanh', 'slug' => 'hanh-trinh-rung-xanh', 'status' => 'published', 'summary' => 'Khám phá thiên nhiên.', 'content' => '<p>Nội dung hành trình.</p>', 'publish_at' => now()]);
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

        foreach ([
            ['site.services.show', ['slug' => 'thue-bo-cam-trai'], 'Thuê bộ cắm trại trọn gói', 'Nội dung dịch vụ.'],
            ['site.blog.show', ['slug' => 'kinh-nghiem-dung-leu'], 'Kinh nghiệm dựng lều khi trời mưa', 'Nội dung bài viết.'],
            ['site.projects.show', ['slug' => 'hanh-trinh-rung-xanh'], 'Hành trình Rừng Xanh', 'Nội dung hành trình.'],
            ['site.services.index', [], 'Thuê bộ cắm trại trọn gói', '/vi/ser/thue-bo-cam-trai'],
            ['site.blog.index', [], 'Kinh nghiệm dựng lều khi trời mưa', '/vi/n/kinh-nghiem-dung-leu'],
            ['site.projects.index', [], 'Hành trình Rừng Xanh', '/vi/prj/hanh-trinh-rung-xanh'],
            ['site.catalog.search', [], 'Lều trekking Rừng Xanh', '/vi/san-pham/leu-trekking-rung-xanh'],
            ['site.catalog.category', ['slug' => 'leu-trai-cao-cap'], 'Lều trại cao cấp', 'Lều trekking Rừng Xanh'],
            ['site.catalog.product', ['slug' => 'leu-trekking-rung-xanh'], 'Lều trekking Rừng Xanh', '2.750.000'],
        ] as [$routeName, $parameters, $title, $content]) {
            $response = $this->get(route($routeName, ['locale' => 'vi', ...$parameters]))
                ->assertOk()
                ->assertSee('class="dl-header"', false)
                ->assertSee('class="dl-footer"', false)
                ->assertSee('/storage/branding/rung-xanh.svg', false)
                ->assertSee($title)
                ->assertSee($content, false)
                ->assertDontSee('f405-page', false);

            $this->assertSame(1, substr_count($response->getContent(), '<!doctype html>'), $routeName);
        }
    }

    public function test_all_dl750_subpage_views_render_one_shared_shell(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'DL750');
        $data = [
            'activeTheme' => $theme,
            'siteProfile' => new SiteProfile(['site_name' => 'DL750 Layout Test']),
            'themeShellData' => ['branding' => ['company_name' => 'DL750 Layout Test', 'logo_url' => '/layout-test-logo.svg']],
            'entry' => ['title' => 'Nội dung đã xuất bản', 'body' => '<p>Nội dung trang con.</p>'],
            'productModel' => new CatalogProduct(['slug' => 'test-product', 'name' => 'Test product']),
            'product' => ['title' => 'Test product', 'price' => 100000],
            'category' => ['name' => 'Test category'],
            'pageTitle' => 'Trang con DL750',
            'listingItems' => [],
            'products' => [],
        ];

        foreach (['cms', 'service', 'project', 'news-detail', 'contact', 'news', 'services', 'projects', 'category', 'search', 'product', 'cart', 'checkout', 'checkout-success'] as $view) {
            $html = view('theme-dl750::'.$view, $data)->render();

            $this->assertSame(1, substr_count($html, 'class="dl-header"'), $view);
            $this->assertSame(1, substr_count($html, 'class="dl-footer"'), $view);
            $this->assertSame(1, substr_count($html, '<!doctype html>'), $view);
            $this->assertStringContainsString('/layout-test-logo.svg', $html, $view);
            $this->assertStringContainsString('data-dl-menu', $html, $view);
            $this->assertStringContainsString('data-xd-auth-modal', $html, $view);
            $this->assertStringNotContainsString('f405-page', $html, $view);
        }
    }
}
