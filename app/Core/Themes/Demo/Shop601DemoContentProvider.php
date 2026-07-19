<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Shop601DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SHOP601';
    private const PRESET_KEY = 'shop601-bean-style';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'SHOP601 Bean Style', 'description' => 'Cửa hàng thời trang với bộ sưu tập, flash sale, sản phẩm nổi bật, đánh giá và tin tức.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho SHOP601.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $images = [
                'photo-1566174053879-31528523f8ae', 'photo-1595777457583-95e059d581b8', 'photo-1551028719-00167b16eac5',
                'photo-1572804013309-59a88b7e92f1', 'photo-1539008835657-9e8e9680c956', 'photo-1496747611176-843222e1e57c',
                'photo-1515886657613-9f3515b0c78f', 'photo-1529139574466-a303027c1d8b', 'photo-1490481651871-ab68de25d43d', 'photo-1509631179647-0177331693ae',
            ];
            $image = fn (string $id, int $width = 900): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            $category = CatalogCategory::query()->create(['name' => 'Thời trang nữ', 'slug' => 'shop601-thoi-trang-nu', 'description' => 'Các thiết kế mới dành cho phong cách hiện đại.', 'image_url' => $image($images[0]), 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);

            $products = [
                ['Đầm xòe đính nơ', 460000, 530000, 'BABYDOLL'], ['Đầm cổ tròn cánh tiên', 515000, 535000, 'MANGO'],
                ['Áo khoác blazer', 705000, 850000, 'GRACE'], ['Váy Babydoll viền ren', 590000, 640000, 'LADIES'],
                ['Đầm suông cổ lệch', 720000, 750000, 'BESTY'], ['Áo khoác phối ren', 690000, 845000, 'GRACE VEST'],
                ['Đầm chữ A cách điệu', 445000, 510000, 'BESTY'], ['Đầm sơ mi thêu hoa', 570000, 680000, 'BORAN'],
                ['Áo dệt kim nữ sát nách', 169000, 239000, 'TENNIS'], ['Chân váy xếp ly tennis', 199000, 229000, 'TENNIS'],
            ];
            foreach ($products as $index => [$name, $price, $originalPrice, $brand]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('shop601-'.$name), 'sku' => 'SHOP601-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $originalPrice, 'stock' => 50, 'short_description' => $brand, 'detail_content' => '<p>Thiết kế hiện đại, chất liệu thoải mái và dễ phối trong nhiều dịp.</p>', 'image_url' => $image($images[$index % count($images)]), 'is_featured' => $index < 5, 'is_highlight' => $index < 5, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Săn deal chào thu', 'Phong cách chuẩn gu · Chỉ từ 99K', 'photo-1483985988355-763728e1935b'],
                ['Bộ sưu tập mới đã lên kệ', 'Ưu đãi online đến 50%', 'photo-1445205170230-053b83016050'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'shop601-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 2200), 'link_url' => '#san-pham', 'badge' => 'BEAN Style', 'metadata' => ['eyebrow' => 'BEAN Style', 'summary' => $summary, 'button_label' => 'Khám phá ngay'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            foreach ([
                ['Trang Trang', 'Nhân viên văn phòng', 'Sản phẩm giao rất nhanh, chất lượng tốt và đóng gói chỉn chu.', 'photo-1494790108377-be9c29b29330'],
                ['Gia Kỳ', 'Tiktoker', 'Sản phẩm tốt, ổn áp trong tầm giá. Mình rất hài lòng.', 'photo-1534528741775-53994a69daeb'],
                ['Thúy Hằng', 'Người mẫu ảnh', 'Chất liệu mát mẻ, dễ phối đồ và lên dáng đẹp.', 'photo-1524504388940-b1c1722653e1'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'company' => 'BEAN Style customer', 'quote' => $quote, 'image_url' => $image($photo, 400), 'image_alt' => $name, 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Cẩm nang thời trang', 'slug' => 'shop601-cam-nang-thoi-trang', 'description' => 'Xu hướng và cách phối đồ mới nhất.']);
            $this->record($postCategory);
            foreach ([
                ['Áo khoác công sở nữ ghi điểm phong cách', 'Những item tiện lợi và thanh lịch cho môi trường công sở.'],
                ['Các kiểu váy đầm đi đám cưới đẹp nhất', 'Lựa chọn nổi bật cho những cô nàng hiện đại.'],
                ['Tips phối đồ với quần jean ống rộng', 'Bí quyết trẻ trung, cuốn hút trong mọi dịp.'],
                ['Phụ nữ hiện đại hãy mặc vest', 'Vest là tuyên ngôn của phong cách và sự tự tin.'],
            ] as $index => [$title, $excerpt]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('shop601-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            $homeUrl = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'SHOP601 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => $homeUrl], ['label' => 'Giới thiệu', 'url' => $homeUrl.'#bo-suu-tap'], ['label' => 'Sản phẩm', 'url' => $homeUrl.'#san-pham'], ['label' => 'Mix & Match', 'url' => $homeUrl.'#bo-suu-tap'], ['label' => 'Feedback', 'url' => $homeUrl.'#danh-gia'], ['label' => 'Tin tức', 'url' => route('site.blog.index')], ['label' => 'Liên hệ', 'url' => route('site.contact')]]]);
            $this->record($menu);
            $contactPage = CmsPage::query()->create(['title' => 'Liên hệ', 'slug' => 'contact', 'status' => 'published', 'excerpt' => 'Kết nối với BEAN Style.', 'body' => '<p>Đội ngũ BEAN Style luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()]);
            $this->record($contactPage);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'BEAN Style', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'BEAN Style', 'company_description' => 'Thời trang hiện đại, thanh lịch và dễ ứng dụng.', 'support_hotline' => '1800 6750', 'support_email' => 'support@sapo.vn', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existingPage === null) $this->record($page);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 1, 'products' => 10, 'banners' => 2, 'testimonials' => 3, 'post_categories' => 1, 'posts' => 4, 'pages' => 1, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'testimonials' => 0, 'post_categories' => 0, 'posts' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsTestimonial::class, 'testimonials'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
