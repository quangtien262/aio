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
use App\Models\LocalizedRoute;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Foot409DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'FOOT409';

    private const PRESET_KEY = 'foot409-fast-food';

    public function __construct(private readonly LandingPageBuilder $builder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }

    public function defaultPreset(): string { return self::PRESET_KEY; }

    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'FOOT409 Fast Food', 'description' => 'Website đồ ăn nhanh với menu, combo, khuyến mãi và dữ liệu đặt món đầy đủ.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho FOOT409.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Gà rán', '/theme-demo/foot409/hero-fried-chicken.png'],
                ['Mì Ý', '/theme-demo/ec903/food-brunch.webp'],
                ['Pizza', '/theme-demo/foot409/promo-pizza.png'],
                ['Cơm', '/theme-demo/ec903/food-banquet.webp'],
                ['Salad', '/theme-demo/ec903/vegan-brunch.webp'],
                ['Bánh', '/theme-demo/ec903/food-dessert.webp'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create(['name' => $name, 'slug' => Str::slug('foot409-'.$name), 'description' => 'Khám phá các món '.$name.' được chuẩn bị tươi ngon mỗi ngày.', 'image_url' => $image, 'sort_order' => $index, 'is_active' => true]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['Gà rán giòn cay (4 miếng)', 129000, 0, 0, '/theme-demo/foot409/hero-fried-chicken.png'],
                ['Burger Zinger (1 phần)', 51000, 65000, 0, '/theme-demo/foot409/promo-burger.png'],
                ['Burger gà quay Flava', 47000, 60000, 0, '/theme-demo/foot409/promo-feast.png'],
                ['Burger tôm giòn', 42000, 50000, 0, '/theme-demo/foot409/promo-burger.png'],
                ['Gà rán giòn cay (2 miếng)', 69000, 0, 0, '/theme-demo/foot409/hero-fried-chicken.png'],
                ['Gà rán gia đình (6 miếng)', 159000, 0, 0, '/theme-demo/foot409/promo-feast.png'],
                ['Combo pizza hải sản và Coca', 159000, 189000, 2, '/theme-demo/foot409/promo-combo.png'],
                ['Combo gà burger hai người', 159000, 179000, 0, '/theme-demo/foot409/promo-feast.png'],
                ['Mì Ý sốt bò bằm', 59000, 69000, 1, '/theme-demo/ec903/food-brunch.webp'],
                ['Cơm gà sốt cay', 65000, 75000, 3, '/theme-demo/ec903/food-banquet.webp'],
                ['Salad rau củ tươi', 49000, 0, 4, '/theme-demo/ec903/vegan-brunch.webp'],
                ['Bánh ngọt chocolate', 39000, 45000, 5, '/theme-demo/ec903/food-dessert.webp'],
            ];
            foreach ($products as $index => [$name, $price, $oldPrice, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('foot409-'.$name),
                    'sku' => 'FOOT409-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $oldPrice ?: null,
                    'stock' => 30,
                    'short_description' => 'Món ngon nóng giòn, chuẩn bị sau khi khách đặt.',
                    'detail_content' => '<p>Nguyên liệu được chọn lọc và chế biến theo quy trình an toàn. Phù hợp dùng tại chỗ hoặc giao tận nơi.</p>',
                    'image_url' => $image,
                    'is_featured' => $index < 8,
                    'is_highlight' => $index < 8,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $banner = SiteBanner::query()->create([
                'theme_key' => self::THEME_KEY,
                'placement' => 'foot409-hero-slider',
                'title' => 'Gà cay giòn tan',
                'subtitle' => 'Deal nóng hổi, giao nhanh tận nơi',
                'image_url' => '/theme-demo/foot409/hero-fried-chicken.png',
                'link_url' => '#mon-moi',
                'badge' => 'TỚI FOOT409',
                'metadata' => ['button_label' => 'Đặt ngay'],
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($banner);

            $postCategory = CmsCategory::query()->create(['name' => 'Khuyến mãi & Ẩm thực', 'slug' => 'foot409-khuyen-mai', 'description' => 'Tin ưu đãi và câu chuyện món ngon.']);
            $this->record($postCategory);
            $posts = [
                ['Ngày hội tín đồ ăn vặt: Giảm giá đặc biệt cho các món chiên giòn', '/theme-demo/foot409/hero-fried-chicken.png'],
                ['Thứ 2 đầy năng lượng: Ưu đãi đặc biệt cho bữa sáng nhanh gọn', '/theme-demo/ec903/food-brunch.webp'],
                ['Tiệc tùng linh đình: Khuyến mãi combo pizza và nước ngọt', '/theme-demo/foot409/promo-pizza.png'],
                ['Deal sốc nửa giá: Thưởng thức mì Ý ngon mê ly', '/theme-demo/ec903/food-banquet.webp'],
            ];
            foreach ($posts as $index => [$title, $image]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('foot409-'.$title), 'status' => 'published', 'excerpt' => 'Ưu đãi mới dành cho khách hàng yêu thích món ngon và những bữa ăn tiện lợi.', 'body' => '<p>Khám phá chương trình trong thời gian giới hạn và đặt món ngay hôm nay.</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            $homeUrl = route('site.home', [], false);
            $menu = CmsMenu::query()->create(['name' => 'FOOT409 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Đặt hàng nhanh', 'url' => $homeUrl.'#mon-moi'],
                ['label' => 'Menu', 'url' => $homeUrl.'#thuc-don'],
                ['label' => 'Chương trình khuyến mãi', 'url' => $homeUrl.'#uu-dai-doi'],
                ['label' => 'Đặt bàn', 'url' => route('site.contact', [], false)],
                ['label' => 'Tin tức', 'url' => route('site.blog.index', [], false)],
                ['label' => 'Giới thiệu', 'url' => $homeUrl.'#footer'],
            ]]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Đặt bàn và nhận hỗ trợ.', 'body' => '<p>Đội ngũ của chúng tôi luôn sẵn sàng hỗ trợ đơn hàng và đặt bàn.</p>', 'publish_at' => now()]);
            if ($page->wasRecentlyCreated) { $this->record($page); }

            $profile = SiteProfile::query()->firstOrNew();
            $existingBranding = $profile->exists ? $profile->globalBranding() : [];
            $profile->forceFill([
                'site_name' => $profile->site_name ?: 'EGA Food',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge($existingBranding, [
                    'company_name' => data_get($existingBranding, 'company_name', $profile->site_name ?: 'EGA Food'),
                    'company_description' => data_get($existingBranding, 'company_description', 'Chuỗi cửa hàng đồ ăn nhanh với thực đơn đa dạng, phục vụ nhanh chóng và tiện lợi.'),
                    'support_hotline' => data_get($existingBranding, 'support_hotline', '1900 6750'),
                    'support_email' => data_get($existingBranding, 'support_email', 'support@example.vn'),
                    'support_location' => data_get($existingBranding, 'support_location', '70 Lữ Gia, Thành phố Hồ Chí Minh'),
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->builder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) { $this->record($landing); }

            return ['preset' => $this->preset(), 'counts' => ['categories' => 6, 'products' => 12, 'banners' => 1, 'post_categories' => 1, 'posts' => 4, 'pages' => $page->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LocalizedRoute::query()->where('website_key', $this->siteContext->websiteKey())->where('resource_type', 'landing_page')->whereIn('resource_id', array_map('strval', $pageIds))->delete();
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        $websiteKey = $this->siteContext->websiteKey();
        foreach ([[CmsPost::class, 'posts', 'cms_post'], [CmsCategory::class, 'post_categories', 'cms_category'], [CmsPage::class, 'pages', 'cms_page'], [CatalogProduct::class, 'products', 'catalog_product'], [CatalogCategory::class, 'categories', 'catalog_category'], [CmsMenu::class, 'menus', 'cms_menu'], [SiteBanner::class, 'banners', 'site_banner']] as [$model, $key, $resourceType]) {
            if ($modelIds = $ids($model)) {
                ContentTranslation::query()->where('website_key', $websiteKey)->where('resource_type', $resourceType)->whereIn('resource_id', $modelIds)->delete();
                LocalizedRoute::query()->where('website_key', $websiteKey)->where('resource_type', $resourceType)->whereIn('resource_id', array_map('strval', $modelIds))->delete();
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
