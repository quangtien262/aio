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

class Ec900DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC900';

    private const PRESET_KEY = 'ec900-smart-home';

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
            'label' => 'EC900 Smart Home',
            'description' => 'Siêu thị điện máy và gia dụng thông minh với homepage cấu hình theo block.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC900.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Điện lạnh', 'Tủ lạnh và thiết bị làm mát hiện đại.', '/theme-demo/ec900/refrigerator.webp'],
                ['Máy giặt & sấy', 'Chăm sóc quần áo sạch sâu và tiết kiệm.', '/theme-demo/ec900/washing-machine.webp'],
                ['Thiết bị làm sạch', 'Robot và máy hút bụi cho nhà gọn gàng.', '/theme-demo/ec900/robot-vacuum.webp'],
                ['Chăm sóc không khí', 'Không khí sạch và dễ chịu cho cả gia đình.', '/theme-demo/ec900/air-purifier.webp'],
                ['Gia dụng bếp', 'Nấu ăn nhanh, ngon và tiện lợi.', '/theme-demo/ec900/air-fryer.webp'],
                ['Máy rửa bát', 'Tự động làm sạch và tối ưu không gian bếp.', '/theme-demo/ec900/dishwasher.webp'],
                ['Tivi & Âm thanh', 'Không gian giải trí sống động tại nhà.', '/theme-demo/ec900/tv-lifestyle.webp'],
                ['Điện gia dụng', 'Thiết bị thiết yếu cho nhịp sống hiện đại.', '/theme-demo/ec900/home-promo.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec900-'.$name),
                    'description' => $description,
                    'image_url' => $image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['Tủ lạnh Side By Side FrostPlus 711L', 17400000, 23500000, 0, '/theme-demo/ec900/refrigerator.webp'],
                ['Tủ lạnh Multi Door FreshZone 510L', 26400000, 29700000, 0, '/theme-demo/ec900/refrigerator.webp'],
                ['Máy giặt Inverter SmartCare 11kg', 9900000, 11300000, 1, '/theme-demo/ec900/washing-machine.webp'],
                ['Máy sấy thông hơi DryCare 7kg', 6100000, 6900000, 1, '/theme-demo/ec900/washing-machine.webp'],
                ['Robot hút bụi CleanMate X20+', 8990000, 12700000, 2, '/theme-demo/ec900/robot-vacuum.webp'],
                ['Robot lau nhà HomePilot Pro', 7490000, 9990000, 2, '/theme-demo/ec900/robot-vacuum.webp'],
                ['Máy lọc không khí AirPure 360', 5490000, 8260000, 3, '/theme-demo/ec900/air-purifier.webp'],
                ['Máy lọc không khí CompactCare 25W', 3050000, 4500000, 3, '/theme-demo/ec900/air-purifier.webp'],
                ['Nồi chiên không dầu HeatFlow 5L', 1990000, 3490000, 4, '/theme-demo/ec900/air-fryer.webp'],
                ['Nồi chiên điện tử KitchenJoy 4L', 2490000, 3890000, 4, '/theme-demo/ec900/air-fryer.webp'],
                ['Máy rửa chén MiniWash 6 bộ', 6100000, 9800000, 5, '/theme-demo/ec900/dishwasher.webp'],
                ['Máy rửa chén độc lập AquaClean 13 bộ', 23890000, 33490000, 5, '/theme-demo/ec900/dishwasher.webp'],
            ];

            foreach ($productDefinitions as $index => [$name, $price, $originalPrice, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec900-'.$name),
                    'sku' => 'EC900-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 50,
                    'short_description' => 'Thiết bị gia dụng chính hãng, bảo hành tận tâm.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn cho tổ ấm hiện đại với thiết kế đẹp, vận hành tiết kiệm và dịch vụ hậu mãi rõ ràng.</p>',
                    'image_url' => $image,
                    'is_featured' => $index < 6,
                    'is_highlight' => $index < 6,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Đặc quyền gia dụng thông minh', 'Bảo hành tận tâm, trả góp 0% lãi suất.', '/theme-demo/ec900/hero-appliances.webp'],
                ['Nâng cấp tổ ấm, nhẹ việc mỗi ngày', 'Ưu đãi đồng bộ thiết bị cho gia đình hiện đại.', '/theme-demo/ec900/home-promo.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec900-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => '#san-pham-ban-chay',
                    'badge' => 'ECOMAX',
                    'metadata' => ['eyebrow' => 'ECOMAX', 'summary' => $summary, 'button_label' => 'Mua ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tư vấn điện máy',
                'slug' => 'ec900-tu-van-dien-may',
                'description' => 'Kiến thức chọn mua và sử dụng thiết bị gia dụng.',
            ]);
            $this->record($postCategory);

            $postDefinitions = [
                ['Nên mua máy hút ẩm hay máy lọc không khí cho gia đình?', 'So sánh công dụng và cách chọn thiết bị phù hợp với chất lượng không khí trong nhà.', '/theme-demo/ec900/air-purifier.webp'],
                ['Cách chọn thiết bị sưởi phù hợp với không gian gia đình', 'Những tiêu chí quan trọng về công suất, diện tích phòng và an toàn khi sử dụng.', '/theme-demo/ec900/home-promo.webp'],
                ['Có nên bật quạt trong phòng điều hòa để tiết kiệm điện?', 'Cách kết hợp thiết bị làm mát để phòng dễ chịu hơn mà vẫn tối ưu điện năng.', '/theme-demo/ec900/tv-lifestyle.webp'],
                ['Máy sấy quần áo hãng nào tốt và nên mua loại nào?', 'Phân biệt các công nghệ sấy và chọn dung tích phù hợp với nhu cầu gia đình.', '/theme-demo/ec900/washing-machine.webp'],
            ];

            foreach ($postDefinitions as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => $image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec900-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => true,
                ]);
                $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'EC900 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Giới thiệu', 'url' => $home.'#danh-muc-noi-bat'],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Xả kho giá sốc', 'url' => $home.'#san-pham-ban-chay'],
                    ['label' => 'Trung tâm bảo hành', 'url' => route('site.contact')],
                    ['label' => 'Hệ thống cửa hàng', 'url' => route('site.contact')],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Liên hệ Ecomax.', 'body' => '<p>Đội ngũ Ecomax luôn sẵn sàng tư vấn sản phẩm phù hợp cho gia đình bạn.</p>', 'publish_at' => now()],
            );
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'Ecomax Smart Home',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'ECOMAX',
                    'company_description' => 'Điện máy và gia dụng thông minh chính hãng cho mọi tổ ấm.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'support@ecomax.vn',
                    'support_location' => '70 Lữ Gia, Phường 15, Quận 11, Thành phố Hồ Chí Minh',
                ]),
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
                    'products' => count($productDefinitions),
                    'banners' => 2,
                    'post_categories' => 1,
                    'posts' => count($postDefinitions),
                    'media' => count($postDefinitions),
                    'pages' => $page->wasRecentlyCreated ? 1 : 0,
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
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'media' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }

        foreach ([
            [CmsPost::class, 'posts'],
            [CmsMedia::class, 'media'],
            [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'],
            [CatalogProduct::class, 'products'],
            [CatalogCategory::class, 'categories'],
            [CmsMenu::class, 'menus'],
            [SiteBanner::class, 'banners'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
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
