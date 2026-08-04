<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\ContentTranslation;
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

class Nt504DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'NT504';

    private const PRESET_KEY = 'nt504-wolf-paint';

    public function __construct(
        private readonly LandingPageBuilder $builder,
        private readonly SiteContext $siteContext,
    ) {}

    public function themeKey(): string
    {
        return self::THEME_KEY;
    }

    public function defaultPreset(): string
    {
        return self::PRESET_KEY;
    }

    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'NT504 Wolf Paint',
            'description' => 'Cửa hàng sơn, chống thấm và vật liệu hoàn thiện với dữ liệu demo đầy đủ.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho NT504.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $paintImage = '/theme-demo/nt504/paint-categories.png';

            $categoryNames = [
                'Sơn nội thất', 'Sơn ngoại thất', 'Sơn chống thấm', 'Sơn lót & Chất phủ',
                'Sơn đặc biệt', 'Bột trét tường', 'Dụng cụ thi công', 'Sơn lót chống kiềm',
            ];
            $categories = [];
            foreach ($categoryNames as $index => $name) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('nt504-'.$name),
                    'description' => 'Giải pháp '.$name.' bền đẹp cho mọi công trình.',
                    'image_url' => $paintImage,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['Sơn nội thất Premium Silk', 605000, 900000, 0],
                ['Sơn nội thất Easy Clean', 465000, 590000, 0],
                ['Sơn ngoại thất Weather Shield', 1078000, 1290000, 1],
                ['Sơn ngoại thất chống phai màu', 890000, 1050000, 1],
                ['Sơn chống thấm WaterGuard', 4950000, 5350000, 2],
                ['Sơn chống thấm đa năng', 440000, 520000, 2],
                ['Sơn lót kháng kiềm cao cấp', 720000, 820000, 3],
                ['Sơn lót & phủ 2 trong 1', 835000, 970000, 3],
                ['Sơn hiệu ứng bê tông', 1280000, 1450000, 4],
                ['Bột trét tường nội ngoại thất', 295000, 340000, 5],
                ['Bộ con lăn thi công chuyên nghiệp', 185000, 220000, 6],
                ['Sơn lót chống kiềm Ultra Primer', 690000, 790000, 7],
            ];
            foreach ($products as $index => [$name, $price, $oldPrice, $categoryIndex]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('nt504-'.$name),
                    'sku' => 'NT504-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $oldPrice,
                    'stock' => 30,
                    'short_description' => 'WOLF PAINT',
                    'detail_content' => '<p>Công thức bền màu, an toàn, độ che phủ cao và dễ thi công.</p>',
                    'image_url' => $paintImage,
                    'is_featured' => $index < 8,
                    'is_highlight' => $index < 8,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $banner = SiteBanner::query()->create([
                'theme_key' => self::THEME_KEY,
                'placement' => 'nt504-hero-slider',
                'title' => 'Sơn nhà đẹp bắt đầu từ một màu sắc đúng',
                'subtitle' => 'Bảng màu thời thượng, bền đẹp vượt trội và thân thiện với môi trường.',
                'image_url' => '/theme-demo/nt504/hero.png',
                'link_url' => '#san-pham',
                'badge' => 'BST MÀU SẮC MỚI 2026',
                'metadata' => ['button_label' => 'Khám phá bộ sưu tập'],
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($banner);

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tin tức & Kiến thức sơn',
                'slug' => 'nt504-kien-thuc-son',
                'description' => 'Xu hướng màu sắc và hướng dẫn thi công sơn.',
            ]);
            $this->record($postCategory);
            $posts = [
                ['Phong cách Zen là gì? 20 mẫu phối màu hợp kiến trúc', '/theme-demo/nt504/hero.png'],
                ['Phong cách Wabi Sabi và gợi ý màu sắc phù hợp', '/theme-demo/nt504/spaces.png'],
                ['Sơn chống cháy là gì? Phân loại và cơ chế hoạt động', '/theme-demo/nt504/promo.png'],
                ['Quy trình sơn chống thấm ngoài trời chuẩn kỹ thuật', '/theme-demo/nt504/paint-categories.png'],
            ];
            foreach ($posts as $index => [$title, $image]) {
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('nt504-'.$title),
                    'status' => 'published',
                    'excerpt' => 'Kiến thức thực tế giúp chọn màu, chọn sơn và hoàn thiện không gian bền đẹp.',
                    'body' => '<p>Khám phá hướng dẫn chi tiết từ chuyên gia Wolf Paint.</p>',
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => true,
                ]);
                $this->record($post);
            }

            $homeUrl = route('site.home', [], false);
            $menu = CmsMenu::query()->create([
                'name' => 'NT504 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $homeUrl],
                    ['label' => 'Giới thiệu', 'url' => $homeUrl.'#footer'],
                    ['label' => 'Sản phẩm', 'url' => $homeUrl.'#san-pham'],
                    ['label' => 'Bảng màu', 'url' => $homeUrl.'#khong-gian'],
                    ['label' => 'Tư vấn màu', 'url' => route('site.contact', [], false)],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index', [], false)],
                    ['label' => 'Liên hệ', 'url' => route('site.contact', [], false)],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], [
                'title' => 'Liên hệ',
                'status' => 'published',
                'excerpt' => 'Nhận tư vấn màu sắc và giải pháp sơn.',
                'body' => '<p>Đội ngũ Wolf Paint luôn sẵn sàng đồng hành cùng công trình của bạn.</p>',
                'publish_at' => now(),
            ]);
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $existingBranding = $profile->exists ? $profile->globalBranding() : [];
            $profile->forceFill([
                'site_name' => 'Wolf Paint',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge($existingBranding, [
                    'company_name' => 'Wolf Paint',
                    'company_description' => 'Giải pháp sơn cao cấp, bền đẹp và an toàn cho mọi công trình.',
                    'support_hotline' => '1900 6750',
                    'support_email' => 'support@wolfpaint.vn',
                    'support_location' => '70 Lữ Gia, Thành phố Hồ Chí Minh',
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->builder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) {
                $this->record($landing);
            }

            return [
                'preset' => $this->preset(),
                'counts' => ['categories' => 8, 'products' => 12, 'banners' => 1, 'post_categories' => 1, 'posts' => 4, 'pages' => $page->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        $websiteKey = $this->siteContext->websiteKey();
        foreach ([[CmsPost::class, 'posts', 'cms_post'], [CmsCategory::class, 'post_categories', 'cms_category'], [CmsPage::class, 'pages', 'cms_page'], [CatalogProduct::class, 'products', 'catalog_product'], [CatalogCategory::class, 'categories', 'catalog_category'], [CmsMenu::class, 'menus', 'cms_menu'], [SiteBanner::class, 'banners', 'site_banner']] as [$model, $key, $resourceType]) {
            if ($modelIds = $ids($model)) {
                ContentTranslation::query()
                    ->where('website_key', $websiteKey)
                    ->where('resource_type', $resourceType)
                    ->whereIn('resource_id', $modelIds)
                    ->delete();
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create([
            'theme_key' => self::THEME_KEY,
            'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ]);
    }
}
