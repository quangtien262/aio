<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
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

class Shop602DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SHOP602';
    private const PRESET_KEY = 'shop602-wolf-yoga';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'SHOP602 Wolf Yoga', 'description' => 'Cửa hàng thời trang và phụ kiện Yoga với homepage cấu hình theo block.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) throw new InvalidArgumentException('Preset demo không hợp lệ cho SHOP602.');

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $image = fn (string $id, int $width = 1000): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=86";
            $categories = [];
            foreach ([['Đồ tập Yoga', 'Trang phục co giãn, thoáng khí cho mọi bài tập.'], ['Phụ kiện Yoga', 'Thảm, túi và phụ kiện hỗ trợ luyện tập.']] as $index => [$name, $description]) {
                $category = CatalogCategory::query()->create(['name' => $name, 'slug' => Str::slug('shop602-'.$name), 'description' => $description, 'image_url' => $image($index ? '1592432678016-e910b452f9a2' : '1518611012118-696072aa579a'), 'sort_order' => $index, 'is_active' => true]);
                $this->record($category); $categories[] = $category;
            }
            $products = [
                ['Set đồ tập yoga nữ Flow', 650000, 900000, 0, '1506629082955-511b1aa562c8'], ['Áo bra thể thao nữ Cushion', 349000, 500000, 0, '1518611012118-696072aa579a'],
                ['Quần legging yoga nâng dáng', 475000, 500000, 0, '1599447421416-3414500d18a5'], ['Áo tanktop tập luyện Air', 427500, 450000, 0, '1538805060514-97d9cc17730c'],
                ['Set đồ tập phối viền Harmony', 900000, 950000, 0, '1544367567-0f2fcb009e0b'], ['Túi trống thể thao Origin', 790000, 950000, 1, '1553062407-98eeb64c6a62'],
                ['Thảm tập Yoga cân bằng', 420000, 550000, 1, '1592432678016-e910b452f9a2'], ['Bóng massage thư giãn', 190000, 230000, 1, '1599447292180-45fd84092ef4'],
                ['Đai lưng chạy bộ Speed', 320000, 390000, 1, '1552674605-db6ffd4facb5'], ['Túi đựng giày thể thao', 490000, 550000, 1, '1553062407-98eeb64c6a62'],
            ];
            foreach ($products as $index => [$name, $price, $originalPrice, $categoryIndex, $photo]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('shop602-'.$name), 'sku' => 'SHOP602-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $originalPrice, 'stock' => 50, 'short_description' => 'WOLF YOGA', 'detail_content' => '<p>Thiết kế thoải mái, chất liệu bền bỉ và phù hợp cho hành trình tập luyện mỗi ngày.</p>', 'image_url' => $image($photo), 'is_featured' => $index < 5, 'is_highlight' => $index < 5, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }
            foreach ([['Sống khỏe mạnh, sống an yên', 'Cân bằng thân - tâm - trí.', '1544367567-0f2fcb009e0b'], ['Bộ sưu tập Yoga mới', 'Năng động hôm nay, tỏa sáng mỗi ngày.', '1518611012118-696072aa579a']] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'shop602-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 2200), 'link_url' => '#san-pham-moi', 'badge' => 'WOLF YOGA', 'metadata' => ['eyebrow' => 'WOLF YOGA', 'summary' => $summary, 'button_label' => 'Mua sắm ngay'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }
            $postCategory = CmsCategory::query()->create(['name' => 'Cẩm nang Yoga', 'slug' => 'shop602-cam-nang-yoga', 'description' => 'Kiến thức luyện tập và sống khỏe.']); $this->record($postCategory);
            foreach ([['Bài tập yoga chữa mất ngủ', 'Giải pháp tự nhiên giúp ngủ sâu và thư giãn hệ thần kinh.'], ['Các bài tập yoga cho bà bầu 3 tháng đầu', 'Những bài tập an toàn, dễ thực hiện tại nhà.'], ['Ashtanga yoga là gì?', 'Tìm hiểu lợi ích và các bài tập trong bộ môn này.'], ['Các tư thế yoga khó là gì?', 'Một số hướng dẫn và lưu ý khi thực hiện.']] as $index => [$title, $excerpt]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('shop602-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]); $this->record($post);
            }
            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'SHOP602 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => $home], ['label' => 'Giới thiệu', 'url' => $home.'#cam-ket'], ['label' => 'Yoga Deal', 'url' => $home.'#flash-sale'], ['label' => 'Sản phẩm', 'url' => $home.'#san-pham-moi'], ['label' => 'Bộ sưu tập', 'url' => $home.'#kham-pha'], ['label' => 'Đồ thể thao', 'url' => $home.'#phu-kien'], ['label' => 'Tư vấn', 'url' => $home.'#lien-he']]]); $this->record($menu);
            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Liên hệ Wolf Yoga.', 'body' => '<p>Đội ngũ Wolf Yoga luôn sẵn sàng đồng hành cùng bạn.</p>', 'publish_at' => now()]); if ($page->wasRecentlyCreated) $this->record($page);
            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Wolf Yoga', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'WOLF YOGA', 'company_description' => 'Đồng hành cùng bạn trên hành trình sống khỏe, sống đẹp và hạnh phúc.', 'support_hotline' => '1900 6750', 'support_email' => 'support@htvietnam.vn', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, Thành phố Hồ Chí Minh'])])->save();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true); if ($landing && ! $existing) $this->record($landing);
            return ['preset' => $this->preset(), 'counts' => ['categories' => 2, 'products' => 10, 'banners' => 2, 'post_categories' => 1, 'posts' => 4, 'pages' => $page->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete(); return $counts;
    }

    private function record(Model $model): void { ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]); }
}
