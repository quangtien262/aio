<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot408ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot408_is_registered_with_expected_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'FOOT408');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/FOOT408/preview-foot408.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('FOOT408'));
        $this->assertSame([
            'hero_slider',
            'foot408_welcome',
            'foot408_menu_products',
            'foot408_combo_mosaic',
            'foot408_testimonials',
            'foot408_blog_posts',
        ], collect($builder->availableBlocks('FOOT408'))->pluck('block_type')->all());
        $this->assertStringContainsString('translateY(-34px)', file_get_contents(base_path('themes/FOOT408/views/partials/styles.blade.php')));
    }

    public function test_foot408_renders_database_content_contacts_and_copyright(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Bếp Nhà An Vui',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'FOOT408',
            'branding' => [
                'logo_url' => '/storage/branding/bep-an-vui.svg',
                'support_hotline' => '0888 246 810',
                'support_email' => 'hello@anvui.test',
                'support_location' => '28 Đường Bình An, Đà Nẵng',
                'business_hours' => '08:00 - 22:00 mỗi ngày',
                'copyright_text' => 'Bản quyền thuộc về Bếp Nhà An Vui.',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Món ngon gia đình', 'slug' => 'mon-ngon-gia-dinh', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Gà nướng thảo mộc', 'slug' => 'ga-nuong-thao-moc', 'sku' => 'FOOT408-001', 'price' => 189000, 'stock' => 12, 'image_url' => '/theme-demo/ec903/food-grill.webp', 'is_active' => true]);
        CmsPost::query()->create(['title' => 'Bí quyết chọn món cho bữa tối', 'slug' => 'bi-quyet-chon-mon', 'status' => 'published', 'excerpt' => 'Gợi ý kết hợp món ăn cân bằng và dễ thưởng thức.', 'body' => '<p>Nội dung bài viết.</p>', 'publish_at' => now()]);
        CmsTestimonial::query()->create(['name' => 'Ngọc Hà', 'role' => 'Khách hàng', 'quote' => 'Món ngon, giao đúng giờ và đóng gói rất cẩn thận.', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'FOOT408', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/bep-an-vui.svg', false)
            ->assertSee('0888 246 810')
            ->assertSee('hello@anvui.test')
            ->assertSee('28 Đường Bình An, Đà Nẵng')
            ->assertSee('08:00 - 22:00 mỗi ngày')
            ->assertSee('Bản quyền thuộc về Bếp Nhà An Vui.')
            ->assertSee('Gà nướng thảo mộc')
            ->assertSee('Bí quyết chọn món cho bữa tối')
            ->assertSee('Ngọc Hà')
            ->assertSee('data-block-type="foot408_menu_products"', false)
            ->assertDontSee('Pizza House')
            ->assertDontSee('1900 9477')
            ->assertDontSee('admin@demo037051.web30s.vn')
            ->assertDontSee('196 Nguyễn Đình Chiểu');
    }
}
