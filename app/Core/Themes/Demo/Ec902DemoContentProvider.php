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

class Ec902DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC902';

    private const PRESET_KEY = 'ec902-novaphone';

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
            'label' => 'EC902 NovaPhone',
            'description' => 'Cửa hàng smartphone, tablet, laptop và phụ kiện với homepage cấu hình bằng Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC902.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Smartphone', 'Điện thoại thông minh hiệu năng mạnh mẽ.', '/theme-demo/ec902/phone-blue.webp'],
                ['Máy tính bảng', 'Tablet gọn nhẹ cho học tập và sáng tạo.', '/theme-demo/ec902/tablet-coral.webp'],
                ['Laptop', 'Máy tính xách tay cho công việc hiện đại.', '/theme-demo/ec902/laptop-silver.webp'],
                ['Đồng hồ thông minh', 'Thiết bị đeo theo dõi sức khỏe mỗi ngày.', '/theme-demo/ec902/watch-white.webp'],
                ['Phụ kiện', 'Sạc, tai nghe và phụ kiện đồng bộ thiết bị.', '/theme-demo/ec902/earbuds-white.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec902-'.$name),
                    'description' => $description,
                    'image_url' => $image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['Nova X Pro 1TB', 45590000, 49990000, 0, '/theme-demo/ec902/phone-silver.webp'],
                ['Nova X Plus 512GB', 32790000, 36990000, 0, '/theme-demo/ec902/phone-green.webp'],
                ['Nova X 512GB', 29990000, 33990000, 0, '/theme-demo/ec902/phone-blue.webp'],
                ['Nova 14 Pro 256GB', 27490000, 31990000, 0, '/theme-demo/ec902/phone-graphite.webp'],
                ['NovaTab Pro 11 1TB', 42990000, 43990000, 1, '/theme-demo/ec902/tablet-blue.webp'],
                ['NovaTab Air 256GB', 21790000, 24990000, 1, '/theme-demo/ec902/tablet-coral.webp'],
                ['NovaTab 10 256GB', 14590000, 16790000, 1, '/theme-demo/ec902/tablet-green.webp'],
                ['NovaBook Air 14', 27990000, 31990000, 2, '/theme-demo/ec902/laptop-silver.webp'],
                ['Nova Watch Active', 6390000, 8990000, 3, '/theme-demo/ec902/watch-white.webp'],
                ['Nova Buds Pro', 3190000, 3990000, 4, '/theme-demo/ec902/earbuds-white.webp'],
                ['Sạc không dây NovaPad', 1290000, 1590000, 4, '/theme-demo/ec902/charger-wireless.webp'],
                ['Cốc sạc nhanh Nova 30W', 690000, 990000, 4, '/theme-demo/ec902/charger-wall.webp'],
            ];

            foreach ($productDefinitions as $index => [$name, $price, $originalPrice, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec902-'.$name),
                    'sku' => 'EC902-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 40,
                    'short_description' => 'Thiết bị công nghệ chính hãng, bảo hành minh bạch và hỗ trợ giao nhanh toàn quốc.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn cho hệ sinh thái công nghệ hiện đại, chú trọng hiệu năng, độ hoàn thiện và trải nghiệm sử dụng liền mạch.</p>',
                    'image_url' => $image,
                    'is_featured' => $index < 8,
                    'is_highlight' => $index < 8,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Công nghệ dẫn lối tương lai', 'Nova X Pro mạnh mẽ, bền bỉ và liền mạch trong từng trải nghiệm.', '/theme-demo/ec902/hero-tech.webp'],
                ['Thiết bị thông minh, ưu đãi bứt phá', 'Đồng bộ smartphone, tablet và phụ kiện với mức giá hấp dẫn.', '/theme-demo/ec902/promo-computing.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec902-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => '#san-pham-moi',
                    'badge' => 'NOVA TECHNOLOGY',
                    'metadata' => ['eyebrow' => 'NOVA TECHNOLOGY', 'summary' => $summary, 'button_label' => 'Khám phá ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tin công nghệ EC902',
                'slug' => 'ec902-tin-cong-nghe',
                'description' => 'Tin mới, đánh giá và hướng dẫn sử dụng thiết bị công nghệ.',
            ]);
            $this->record($postCategory);
            $postDefinitions = [
                ['Công nghệ camera trên Nova X thế hệ mới', 'Công nghệ xử lý ảnh mới giúp smartphone ghi lại màu sắc tự nhiên và chi tiết hơn.', '/theme-demo/ec902/story-phone.webp'],
                ['Đánh giá công nghệ màn hình OLED trên Nova X', 'Công nghệ OLED mang đến độ tương phản cao, màu đen sâu và khả năng tiết kiệm năng lượng.', '/theme-demo/ec902/story-review.webp'],
                ['NovaTab và công nghệ hỗ trợ học tập, sáng tạo', 'Bút cảm ứng, màn hình lớn và đa nhiệm biến tablet thành công cụ sáng tạo linh hoạt.', '/theme-demo/ec902/story-tablet.webp'],
                ['NovaCharge USB-C: công nghệ và cách dùng an toàn', 'Hiểu đúng công nghệ sạc nhanh giúp bảo vệ pin và tối ưu thời gian sử dụng thiết bị.', '/theme-demo/ec902/story-charging.webp'],
                ['Xu hướng công nghệ Nova năm 2026', 'Thiết bị nhẹ hơn, thông minh hơn và kết nối liền mạch tiếp tục định hình trải nghiệm số.', '/theme-demo/ec902/hero-tech.webp'],
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
                    'slug' => Str::slug('ec902-'.$title),
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
                'name' => 'EC902 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Giới thiệu', 'url' => route('site.contact')],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Review', 'url' => $home.'#video-review'],
                    ['label' => 'Câu hỏi thường gặp', 'url' => route('site.contact')],
                    ['label' => 'Tra cứu bảo hành', 'url' => route('site.contact')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Liên hệ NovaPhone Technology.', 'body' => '<p>Đội ngũ NovaPhone luôn sẵn sàng tư vấn thiết bị phù hợp với nhu cầu và ngân sách của bạn.</p>', 'publish_at' => now()],
            );
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'NovaPhone Technology',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'NOVAPHONE',
                    'company_description' => 'Thiết bị công nghệ chính hãng, giá tốt, trả góp linh hoạt và giao nhanh toàn quốc.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'support@novaphone.vn',
                    'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
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
