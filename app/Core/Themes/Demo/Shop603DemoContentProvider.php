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

class Shop603DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SHOP603';
    private const PRESET_KEY = 'shop603-alena-fashion';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'SHOP603 Alena Fashion', 'description' => 'Shop thời trang Alena với sản phẩm, tin tức, đối tác và homepage block động.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho SHOP603.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $catalogImages = [
                '/theme-demo/shop603/product-women-knit.png',
                '/theme-demo/shop603/product-women-rose.png',
                '/theme-demo/shop603/product-men-green.png',
            ];
            $image = fn (string $id, int $width = 1000): string => str_contains($id, '152913') || str_contains($id, '148398')
                ? '/theme-demo/shop603/hero-fashion.png'
                : $catalogImages[abs(crc32($id)) % count($catalogImages)];
            $categories = [];

            foreach ([['Thời trang nữ', 'Áo, váy và phụ kiện nữ thanh lịch.'], ['Thời trang nam', 'Trang phục nam trẻ trung, hiện đại.'], ['Thời trang trẻ em', 'Thiết kế thoải mái cho bé trai và bé gái.']] as $index => [$name, $description]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('shop603-'.$name), 'description' => $description,
                    'image_url' => $image(['1434389677669-e08b4cac3105', '1603252110481-7ba873bf42ab', '1622290291468-a28f7a7dc6a8'][$index]),
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category); $categories[] = $category;
            }

            $products = [
                ['Áo len nữ thanh lịch', 110000, 130000, 0, '1434389677669-e08b4cac3105'], ['Chân váy nữ cá tính', 70000, 0, 0, '1583496661160-fb5886a0aaaa'],
                ['Áo khoác nữ pastel', 300000, 500000, 0, '1551488831-00ddcb6c6bd3'], ['Đồ thể thao nữ', 0, 0, 0, '1518611012118-696072aa579a'],
                ['Váy nữ tối giản', 150000, 0, 0, '1595777457583-95e059d581b8'], ['Đồ ngủ nam họa tiết', 130000, 0, 1, '1617127365659-c47fa864d8bc'],
                ['Bộ đồ hè nam', 200000, 0, 1, '1603252110481-7ba873bf42ab'], ['Áo hoodie nam năng động', 280000, 350000, 1, '1556821840-3a63f95609a7'],
                ['Sơ mi trẻ em', 0, 0, 2, '1622290291468-a28f7a7dc6a8'], ['Bộ hai dây bé gái', 80000, 0, 2, '1519238263530-99bdd11df2ea'],
                ['Balo chống gù', 0, 0, 2, '1553062407-98eeb64c6a62'], ['Giày trẻ em năng động', 220000, 280000, 2, '1514989940723-e8e51635b782'],
            ];

            foreach ($products as $index => [$name, $price, $originalPrice, $categoryIndex, $photo]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('shop603-'.$name),
                    'sku' => 'SHOP603-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $originalPrice,
                    'stock' => 50, 'short_description' => 'ALENA FASHION', 'detail_content' => '<p>Thiết kế chọn lọc, dễ phối và phù hợp với nhịp sống hiện đại.</p>',
                    'image_url' => $image($photo), 'is_featured' => $index < 5, 'is_highlight' => $index < 8, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([['Tuần lễ mặc đẹp - Mùa lễ hội', 'Mua 2 sản phẩm tặng 20%', '1529139574466-a303027c1d8b'], ['Bộ sưu tập Alena mới', 'Phong cách cho cả gia đình', '1483985988355-763728e1935b']] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY, 'placement' => 'shop603-hero-slider', 'title' => $title, 'subtitle' => $summary,
                    'image_url' => $image($photo, 2200), 'link_url' => '#san-pham-hot', 'badge' => 'ALENA',
                    'metadata' => ['eyebrow' => 'TUẦN LỄ MẶC ĐẸP', 'summary' => $summary, 'button_label' => 'Khám phá ngay'],
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Tin tức thời trang', 'slug' => 'shop603-tin-thoi-trang', 'description' => 'Xu hướng và bí quyết phối đồ.']);
            $this->record($postCategory);
            foreach ([['Cách phối đồ hè nam', 'Sơ mi họa tiết, quần denim và giày sneaker cho ngày hè năng động.'], ['Cách phối đồ với quần thể thao nam', 'Những gợi ý cân bằng giữa năng động và thanh lịch.'], ['Cách phối đồ sơ mi nam', 'Các công thức phối sơ mi đơn giản nhưng hiệu quả.']] as $index => [$title, $excerpt]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('shop603-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach (['CHANEL', 'LOUIS VUITTON', 'GIVENCHY', 'BALENCIAGA', 'HERMÈS', 'YSL'] as $index => $name) {
                $partner = CmsPartner::query()->create(['title' => $name, 'slug' => Str::slug('shop603-'.$name), 'description' => 'Đối tác thời trang Alena.', 'image_url' => null, 'image_alt' => $name, 'link_url' => '#top', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'SHOP603 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'url' => $home], ['label' => 'Thời trang Nam', 'url' => route('site.catalog.search', ['q' => 'nam'])],
                ['label' => 'Sản phẩm', 'url' => $home.'#san-pham-hot'], ['label' => 'Bé trai', 'url' => route('site.catalog.search', ['q' => 'bé trai'])],
                ['label' => 'Bé gái', 'url' => route('site.catalog.search', ['q' => 'bé gái'])], ['label' => 'Tin tức', 'url' => route('site.blog.index')], ['label' => 'Liên hệ', 'url' => route('site.contact')],
            ]]);
            $this->record($menu);
            $page = CmsPage::query()->create(['title' => 'Liên hệ', 'slug' => 'contact', 'status' => 'published', 'excerpt' => 'Liên hệ Alena Fashion.', 'body' => '<p>Đội ngũ Alena luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()]);
            $this->record($page);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Alena Fashion', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['logo_url' => null, 'company_name' => 'Alena', 'company_description' => 'Shop thời trang và phụ kiện Alena.', 'support_hotline' => '1900 6750', 'support_email' => 'hello@alena.vn', 'support_location' => 'Tầng 6, Tòa nhà Ladeco, 266 Đội Cấn, Phường Liễu Giai, Quận Ba Đình, TP Hà Nội'])])->save();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 3, 'products' => 12, 'banners' => 2, 'post_categories' => 1, 'posts' => 3, 'partners' => 6, 'pages' => 1, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
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
