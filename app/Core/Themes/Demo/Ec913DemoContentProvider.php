<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
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

class Ec913DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC913';

    private const PRESET_KEY = 'ec913-novatech-mall';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
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
            'label' => 'EC913 NovaTech Mall',
            'description' => 'Siêu thị điện tử, điện máy và gia dụng hiện đại với Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC913.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Điện thoại & Tablet', 'phone-blue.webp'],
                ['Laptop & Máy tính', 'laptop-silver.webp'],
                ['TV & Màn hình', 'tv-lifestyle.webp'],
                ['Điện lạnh', 'refrigerator.webp'],
                ['Gia dụng thông minh', 'air-fryer.webp'],
                ['Âm thanh', 'earbuds-white.webp'],
                ['Gaming & Console', 'promo-gaming.webp'],
                ['Camera & Kỹ thuật số', 'phone-graphite.webp'],
                ['Phụ kiện số', 'charger-wireless.webp'],
                ['Thiết bị nhà bếp', 'washing-machine.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec913-'.$name),
                    'description' => 'Sản phẩm chính hãng, bảo hành minh bạch và giao hàng nhanh.',
                    'image_url' => '/theme-demo/ec913/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['EC913-BEST-001', 0, 'Điện thoại Nova X Pro 256GB', 26990000, 29990000, 'phone-graphite.webp', true],
                ['EC913-BEST-002', 0, 'Điện thoại Nova X 128GB', 18990000, 21990000, 'phone-blue.webp', true],
                ['EC913-BEST-003', 1, 'Tablet Air 11 inch Wi-Fi', 15990000, 17990000, 'tablet-blue.webp', true],
                ['EC913-BEST-004', 5, 'Tai nghe không dây SoundPods Pro', 3290000, 3990000, 'earbuds-white.webp', true],
                ['EC913-BEST-005', 4, 'Robot hút bụi thông minh CleanBot S8', 8990000, 10990000, 'robot-vacuum.webp', true],
                ['EC913-LAPTOP-001', 1, 'NovaBook Pro 15 OLED', 32990000, 37990000, 'laptop-silver.webp', false],
                ['EC913-LAPTOP-002', 1, 'NovaBook Air 14', 24990000, 27990000, 'laptop-silver.webp', false],
                ['EC913-LAPTOP-003', 1, 'UltraBook Zen 14', 21990000, 24990000, 'laptop-silver.webp', false],
                ['EC913-LAPTOP-004', 1, 'Gaming Laptop Titan G5', 28990000, 32990000, 'laptop-silver.webp', false],
                ['EC913-LAPTOP-005', 1, 'Laptop Office Flex 13', 15990000, 17990000, 'laptop-silver.webp', false],
            ];

            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image, $featured]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec913-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 80,
                    'short_description' => 'Thiết kế hiện đại, hiệu năng mạnh mẽ và bảo hành chính hãng.',
                    'detail_content' => '<p>Sản phẩm được kiểm tra chất lượng, công khai giá bán và chính sách bảo hành minh bạch.</p>',
                    'image_url' => '/theme-demo/ec913/'.$image,
                    'is_featured' => $featured,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Đại tiệc công nghệ', 'Sắm công nghệ, sống tiện nghi', 'Ưu đãi đến 35%'],
                ['Nâng cấp không gian sống', 'Điện máy thông minh cho mọi nhà', 'Giao nhanh, lắp đặt tận nơi'],
            ] as $index => [$badge, $title, $summary]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec913-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec913/hero-digital-mall.webp',
                    'link_url' => '#ban-chay',
                    'badge' => $badge,
                    'metadata' => ['summary' => $summary, 'button_label' => 'Mua ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Góc công nghệ EC913',
                'slug' => 'ec913-goc-cong-nghe',
                'description' => 'Tin mới, kinh nghiệm chọn mua và sử dụng thiết bị thông minh.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['5 tiêu chí chọn laptop phù hợp cho công việc', 'Cân bằng hiệu năng, màn hình, thời lượng pin và ngân sách trước khi nâng cấp.', 'promo-gaming.webp'],
                ['Biến ngôi nhà thành không gian sống thông minh', 'Những thiết bị gia dụng giúp tiết kiệm thời gian và năng lượng mỗi ngày.', 'promo-appliances.webp'],
                ['Mẹo bảo quản điện thoại và phụ kiện bền hơn', 'Các thói quen đơn giản giúp pin, màn hình và phụ kiện hoạt động ổn định.', 'hero-digital-mall.webp'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec913/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec913-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>NovaTech Mall tổng hợp thông tin thực tế giúp khách hàng lựa chọn và sử dụng thiết bị hiệu quả.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC913 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Khuyến mãi', 'url' => route('site.home').'#khuyen-mai'],
                    ['label' => 'Laptop', 'url' => route('site.home').'#laptop'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ NovaTech Mall',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn sản phẩm, bảo hành và hỗ trợ đơn hàng.',
                    'body' => '<p>Đội ngũ NovaTech Mall luôn sẵn sàng hỗ trợ bạn.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'NovaTech Mall',
                'company_description' => 'Hệ thống bán lẻ điện tử, điện máy và gia dụng thông minh chính hãng.',
                'support_hotline' => '0399162342',
                'support_email' => 'hello@novatech.example',
                'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội',
            ];
            $profile->forceFill([
                'site_name' => 'NovaTech Mall',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => $branding,
            ])->save();

            $existing = LandingPage::query()
                ->where('website_key', $websiteKey)
                ->where('theme_key', self::THEME_KEY)
                ->where('is_home', true)
                ->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) {
                $this->record($landing);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'categories' => count($categoryDefinitions),
                    'products' => count($products),
                    'banners' => 2,
                    'post_categories' => 1,
                    'posts' => count($posts),
                    'media' => count($posts),
                    'pages' => $contact->wasRecentlyCreated ? 1 : 0,
                    'menus' => 1,
                    'landing_pages' => ! $existing && $landing ? 1 : 0,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()
            ->where('theme_key', self::THEME_KEY)
            ->where('preset_key', self::PRESET_KEY)
            ->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = [
            'categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0,
            'posts' => 0, 'media' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0,
        ];

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }

        foreach ([
            [CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'],
            [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
        }

        ThemeDemoRecord::query()
            ->where('theme_key', self::THEME_KEY)
            ->where('preset_key', self::PRESET_KEY)
            ->delete();

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
