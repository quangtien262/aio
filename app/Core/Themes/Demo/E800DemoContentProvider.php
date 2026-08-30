<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPost;
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

class E800DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'E800';
    private const PRESET_KEY = 'e800-sneaker-performance';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'E800 Sneaker Performance', 'description' => 'Cửa hàng sneaker với bộ sưu tập, sản phẩm theo nhu cầu, shop-the-look và tin khuyến mãi.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) throw new InvalidArgumentException('Preset demo không hợp lệ cho E800.');

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $images = [
                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=85',
                'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=1000&q=85',
                'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1000&q=85',
                'https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=1000&q=85',
                'https://images.unsplash.com/photo-1552346154-21d32810aba3?auto=format&fit=crop&w=1000&q=85',
            ];

            $category = CatalogCategory::query()->create(['name' => 'Sneaker hiệu suất', 'slug' => 'e800-sneaker-hieu-suat', 'description' => 'Sneaker chạy bộ, luyện tập và phong cách hằng ngày.', 'image_url' => $images[0], 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);
            $names = ['Velocity Pro Đỏ', 'Urban Pace Trắng', 'Court Motion Xám', 'Trail Core Đen', 'Flex Runner Xanh', 'Daily Move Cam', 'Air Flow Trắng', 'Motion Knit Đỏ', 'City Track Đen', 'Peak Run Xám'];
            foreach ($names as $index => $name) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('e800-'.$name), 'sku' => 'E800-'.str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT), 'price' => 1290000 + ($index * 85000), 'original_price' => 2490000 + ($index * 110000), 'stock' => 40, 'short_description' => $index % 2 ? 'E800 ACTIVE' : 'E800 PERFORMANCE', 'detail_content' => '<p>Đệm đàn hồi, bề mặt lưới thoáng khí và đế bám linh hoạt cho chuyển động hằng ngày.</p>', 'image_url' => $images[$index % count($images)], 'is_featured' => $index < 6, 'is_highlight' => $index < 6, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'e800-hero-slider', 'title' => 'Nơi hiệu suất gặp gỡ phong cách hằng ngày', 'subtitle' => 'Bứt phá từng chuyển động với thiết kế sneaker thế hệ mới.', 'image_url' => '/themes/E800/images/hero-e800.png', 'link_url' => '#san-pham', 'badge' => 'E800', 'metadata' => ['eyebrow' => 'Tự tin thể hiện phong cách', 'summary' => 'Bứt phá từng chuyển động với thiết kế sneaker thế hệ mới.', 'button_label' => 'Khám phá ngay'], 'sort_order' => 0, 'is_active' => true]);
            $this->record($banner);

            $postCategory = CmsCategory::query()->create(['name' => 'Khuyến mãi sneaker', 'slug' => 'e800-khuyen-mai-sneaker', 'description' => 'Ưu đãi và cẩm nang sneaker mới nhất.']);
            $this->record($postCategory);
            foreach (['Payday: Lương về - Sale tới', 'Double Day: Deal cuối mùa', 'Bí quyết chọn giày chạy bộ', 'Cách phối sneaker hằng ngày', 'Chăm sóc sneaker đúng cách'] as $index => $title) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('e800-'.$title), 'status' => 'published', 'excerpt' => 'Khám phá sản phẩm tuyển chọn cùng ưu đãi giới hạn dành riêng cho thành viên E800.', 'body' => '<p>Những lựa chọn mới giúp bạn tối ưu hiệu suất và giữ phong cách trong mọi chuyển động.</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            $homeUrl = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'E800 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => $homeUrl], ['label' => 'Sản phẩm', 'url' => $homeUrl.'#san-pham'], ['label' => 'Khuyến mãi', 'url' => $homeUrl.'#khuyen-mai'], ['label' => 'Blog', 'url' => route('site.blog.index')], ['label' => 'Hướng dẫn thiết lập', 'url' => route('site.contact')]]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'E800 Sneaker', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array)$profile->branding, ['company_name' => 'E800 Sneaker', 'company_description' => 'Sneaker hiệu suất cao cho phong cách sống hiện đại.', 'support_hotline' => '1900 6750', 'support_email' => 'support@htvietnam.vn', 'support_location' => '70 Lữ Gia, Quận 11, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existingPage === null) $this->record($page);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 1, 'products' => 10, 'banners' => 1, 'post_categories' => 1, 'posts' => 5, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsCategory::class, 'post_categories'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
