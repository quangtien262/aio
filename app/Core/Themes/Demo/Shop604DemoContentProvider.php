<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
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

class Shop604DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SHOP604';
    private const PRESET_KEY = 'shop604-bean-lingerie';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'SHOP604 Bean Lingerie', 'description' => 'Shop nội y và đồ bơi nữ với sản phẩm, tin tức, đối tác, đánh giá và homepage block động.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho SHOP604.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $catalogImages = [
                '/theme-demo/shop604/product-women-knit.png',
                '/theme-demo/shop604/product-women-rose.png',
                '/theme-demo/shop604/product-men-green.png',
            ];
            $image = fn (string $id, int $width = 1000): string => str_contains($id, '152913') || str_contains($id, '148398')
                ? '/theme-demo/shop604/hero-fashion.png'
                : $catalogImages[abs(crc32($id)) % count($catalogImages)];
            $categories = [];

            foreach ([['Áo ngực', 'Thiết kế tôn dáng, mềm mại và nâng đỡ tự nhiên.'], ['Quần lót', 'Thoải mái trong từng khoảnh khắc thường ngày.'], ['Bikini', 'Tự tin khoe trọn vẻ đẹp quyến rũ và năng động.']] as $index => [$name, $description]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('shop604-'.$name), 'description' => $description,
                    'image_url' => $image(['1434389677669-e08b4cac3105', '1603252110481-7ba873bf42ab', '1622290291468-a28f7a7dc6a8'][$index]),
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category); $categories[] = $category;
            }

            $products = [
                ['Áo ngực nữ thanh lịch', 235000, 289000, 0, '1434389677669-e08b4cac3105'], ['Áo ngực nữ có gọng phối ren', 279000, 399000, 0, '1583496661160-fb5886a0aaaa'],
                ['Áo bralette ren không gọng', 149000, 188000, 0, '1551488831-00ddcb6c6bd3'], ['Áo ngực multiway bốn kiểu', 279000, 399000, 0, '1518611012118-696072aa579a'],
                ['Quần lót nữ ren phối thun', 235000, 279000, 1, '1595777457583-95e059d581b8'], ['Quần lót nữ bikini phối lưới', 169000, 199000, 1, '1617127365659-c47fa864d8bc'],
                ['Quần lót cotton mềm mại', 129000, 169000, 1, '1603252110481-7ba873bf42ab'], ['Combo nội y tiết kiệm', 399000, 520000, 1, '1556821840-3a63f95609a7'],
                ['Bikini hai mảnh kèm chân váy', 439000, 559000, 2, '1622290291468-a28f7a7dc6a8'], ['Bikini ba mảnh đi biển', 440000, 599000, 2, '1519238263530-99bdd11df2ea'],
                ['Đồ bơi nữ dáng váy', 360000, 410000, 2, '1553062407-98eeb64c6a62'], ['Bikini len móc dạng quấn', 480000, 535000, 2, '1514989940723-e8e51635b782'],
            ];

            foreach ($products as $index => [$name, $price, $originalPrice, $categoryIndex, $photo]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('shop604-'.$name),
                    'sku' => 'SHOP604-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $originalPrice,
                    'stock' => 50, 'short_description' => 'BEAN LINGERIE', 'detail_content' => '<p>Chất liệu mềm mại, đường may tinh tế và phom dáng thoải mái cho mọi chuyển động.</p>',
                    'image_url' => $image($photo), 'is_featured' => $index < 5, 'is_highlight' => $index < 8, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([['Lingerie Bras', 'Thiết kế hoàn hảo tôn lên vẻ đẹp nữ tính đầy tinh tế.', '1529139574466-a303027c1d8b'], ['Nét đẹp tự nhiên', 'Chất liệu cao cấp, êm ái và vừa vặn.', '1483985988355-763728e1935b']] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY, 'placement' => 'shop604-hero-slider', 'title' => $title, 'subtitle' => $summary,
                    'image_url' => $image($photo, 2200), 'link_url' => '#san-pham-moi', 'badge' => 'BEAN',
                    'metadata' => ['eyebrow' => 'BỘ SƯU TẬP MỚI', 'summary' => $summary, 'button_label' => 'Mua Ngay'],
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Cẩm nang Bean', 'slug' => 'shop604-cam-nang-bean', 'description' => 'Xu hướng nội y, đồ bơi và bí quyết chọn sản phẩm.']);
            $this->record($postCategory);
            foreach ([['Gợi ý những mẫu bikini dành riêng cho cô nàng ngại ngùng', 'Chọn bikini phù hợp giúp bạn tự tin hơn trong mọi chuyến đi biển.'], ['Những điều tối kỵ khi diện bikini mà nàng nên biết', 'Các lưu ý quan trọng để chọn đúng kiểu dáng và chất liệu.'], ['Bốn chiêu tạo dáng với bikini để thỏa sức tung hoành', 'Những bí quyết đơn giản cho khung hình mùa hè tự nhiên.'], ['Bảy xu hướng đồ bơi hứa hẹn gây sốt hè', 'Các phong cách được yêu thích trong mùa du lịch năm nay.']] as $index => [$title, $excerpt]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('shop604-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach (['HUNKEMÖLLER', 'LA SENZA', 'TRIUMPH', 'WACOAL', "VICTORIA'S SECRET", 'CALVIN KLEIN'] as $index => $name) {
                $partner = CmsPartner::query()->create(['title' => $name, 'slug' => Str::slug('shop604-'.$name), 'description' => 'Đối tác thời trang Bean Lingerie.', 'image_url' => null, 'image_alt' => $name, 'link_url' => '#top', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'SHOP604 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'url' => $home], ['label' => 'Giới thiệu', 'url' => $home.'#phong-cach'],
                ['label' => 'Đồ lót nữ', 'url' => route('site.catalog.search', ['q' => 'đồ lót'])], ['label' => 'Bikini & Đồ bơi', 'url' => route('site.catalog.search', ['q' => 'bikini'])],
                ['label' => 'Flash sale', 'url' => $home.'#flash-sale'], ['label' => 'Combo tiết kiệm', 'url' => route('site.catalog.search', ['q' => 'combo'])], ['label' => 'Hệ thống cửa hàng', 'url' => route('site.contact')],
            ]]);
            $this->record($menu);
            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Liên hệ Bean Lingerie Fashion.', 'body' => '<p>Đội ngũ Bean Lingerie luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()]);
            if ($page->wasRecentlyCreated) $this->record($page);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Bean Lingerie', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['logo_url' => null, 'company_name' => 'Bean Lingerie', 'company_description' => 'Thiết kế nội y và đồ bơi tinh tế, thoải mái và tôn vinh đường cong.', 'support_hotline' => '1800 6750', 'support_email' => 'support@bean.vn', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP. Hồ Chí Minh'])])->save();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 3, 'products' => 12, 'banners' => 2, 'post_categories' => 1, 'posts' => 4, 'partners' => 6, 'pages' => $page->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'partners' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsCategory::class, 'post_categories'], [CmsPartner::class, 'partners'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
