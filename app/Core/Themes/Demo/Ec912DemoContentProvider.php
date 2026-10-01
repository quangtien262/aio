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

class Ec912DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC912';

    private const PRESET_KEY = 'ec912-sudes-phone';

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
            'label' => 'EC912 Sudes Phone',
            'description' => 'Cửa hàng điện thoại và thiết bị Apple với Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC912.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['iPhone', 'phone-graphite.webp'],
                ['Mac', 'laptop-silver.webp'],
                ['iPad', 'tablet-blue.webp'],
                ['Watch', 'watch-white.webp'],
                ['Âm thanh', 'earbuds-white.webp'],
                ['Phụ kiện', 'charger-wireless.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec912-'.$name),
                    'description' => 'Thiết bị Apple chính hãng, bảo hành minh bạch.',
                    'image_url' => '/theme-demo/ec912/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['iPhone 12 64GB - Chính hãng VN/A', 14790000, 24990000, 'phone-green.webp'],
                ['iPhone 14 Pro Max 512GB - Chính hãng VN/A', 35790000, 43990000, 'phone-graphite.webp'],
                ['iPhone 14 Pro Max 128GB - Chính hãng VN/A', 26890000, 34990000, 'phone-graphite.webp'],
                ['iPhone 14 Plus 512GB - Chính hãng VN/A', 29990000, 36990000, 'phone-blue.webp'],
                ['iPhone 14 256GB - Chính hãng VN/A', 24490000, 30990000, 'phone-green.webp'],
                ['iPhone 14 512GB - Chính hãng VN/A', 27990000, 33990000, 'phone-silver.webp'],
                ['iPhone 14 Plus 128GB - Chính hãng VN/A', 21490000, 27990000, 'phone-silver.webp'],
                ['iPhone 14 Pro Max 256GB - Chính hãng VN/A', 29690000, 37990000, 'phone-graphite.webp'],
            ];

            $products = array_map(fn (array $product): array => [...$product, 0], $products);
            $products = array_merge($products, [
                ['MacBook Air 13 inch - Bạc', 24990000, 27990000, 'laptop-silver.webp', 1],
                ['MacBook Air 15 inch - Bạc', 29990000, 32990000, 'laptop-silver.webp', 1],
                ['iPad Air 64GB - Xanh', 14990000, 16990000, 'tablet-blue.webp', 2],
                ['Apple Watch - Dây trắng', 8990000, 9990000, 'watch-white.webp', 3],
                ['AirPods - Hộp sạc trắng', 3990000, 4490000, 'earbuds-white.webp', 4],
                ['Đế sạc không dây', 990000, 1290000, 'charger-wireless.webp', 5],
            ]);
            $summaries = [
                'Điện thoại cho liên lạc, chụp ảnh và giải trí hằng ngày.',
                'Máy tính gọn nhẹ cho học tập và công việc di động.',
                'Máy tính bảng cho ghi chú, đọc tài liệu và sáng tạo.',
                'Đồng hồ thông minh cho thông báo và theo dõi vận động.',
                'Tai nghe không dây cho nghe nhạc và cuộc gọi.',
                'Phụ kiện sạc giúp bàn làm việc gọn gàng hơn.',
            ];
            foreach ($products as $index => [$name, $price, $originalPrice, $image, $categoryIndex]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec912-'.$name),
                    'sku' => 'EC912-'.($categoryIndex === 0 ? 'IPHONE' : strtoupper(Str::slug($categories[$categoryIndex]->name))).'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 80,
                    'short_description' => $summaries[$categoryIndex],
                    'detail_content' => '<h2>Trải nghiệm sử dụng</h2><p>'.$summaries[$categoryIndex].'</p><h2>Lựa chọn phù hợp</h2><p>Đối chiếu dung lượng, kích thước và khả năng tương thích với thiết bị đang dùng trước khi lựa chọn.</p><h2>Thông tin mua hàng</h2><p>Sản phẩm và giá trong bộ dữ liệu này dùng để minh họa website demo. Liên hệ cửa hàng để xác nhận cấu hình, phụ kiện, tồn kho và điều kiện bảo hành thực tế.</p>',
                    'image_url' => '/theme-demo/ec912/'.$image,
                    'is_featured' => $index < 4,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['iPhone 14 Pro Max - Giá cực sốc', 'Ưu đãi hấp dẫn cho dòng iPhone cao cấp'],
                ['Hệ sinh thái Apple chính hãng', 'iPhone, iPad, Mac, Watch và phụ kiện đồng bộ'],
            ] as $index => [$title, $summary]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec912-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec912/hero-tech.webp',
                    'link_url' => '#hot-sale',
                    'badge' => 'SUDES PHONE',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Xem ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tin công nghệ EC912',
                'slug' => 'ec912-tin-cong-nghe',
                'description' => 'Tin tức điện thoại, Apple và thiết bị thông minh.',
            ]);
            $this->record($postCategory);
            $posts = [
                ['Chọn dung lượng iPhone phù hợp với nhu cầu', 'Cân nhắc ảnh, video và ứng dụng để lựa chọn dung lượng lưu trữ.', 'story-phone.webp'],
                ['iPad hay MacBook cho học tập và làm việc?', 'So sánh cách sử dụng, tính di động và phụ kiện cần thiết.', 'story-tablet.webp'],
                ['Những điều cần kiểm tra khi nhận điện thoại', 'Kiểm tra ngoại hình, cấu hình và chứng từ trước khi sử dụng.', 'story-review.webp'],
                ['Sắp xếp góc sạc gọn gàng cho nhiều thiết bị', 'Chọn phụ kiện tương thích và bố trí dây sạc thuận tiện.', 'story-charging.webp'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec912/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec912-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<h2>Nhu cầu sử dụng</h2><p>'.$excerpt.'</p><p>Liệt kê các tác vụ thường xuyên và những thiết bị bạn đang dùng. Việc này giúp xác định đâu là tính năng cần thiết trước khi so sánh sản phẩm.</p><h2>Kiểm tra trước khi chọn</h2><p>Đọc thông số của đúng phiên bản, đối chiếu khả năng tương thích và trải nghiệm trực tiếp nếu có thể. Kiểm tra phụ kiện đi kèm, điều kiện bảo hành và tổng chi phí.</p><h2>Trao đổi với cửa hàng</h2><p>Chuẩn bị câu hỏi về cấu hình, tồn kho và hỗ trợ sau mua. Đây là bài viết minh họa cho website demo, không phải thông báo ưu đãi hay chính sách bán hàng.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => true,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC912 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
                    ['label' => 'iPhone', 'url' => route('site.catalog.search').'?q=EC912-IPHONE'],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Chính sách', 'url' => route('site.home').'#chinh-sach'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ Sudes Phone',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn sản phẩm và hỗ trợ đơn hàng.',
                    'body' => '<p>Đội ngũ Sudes Phone luôn sẵn sàng hỗ trợ bạn.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'Sudes Phone',
                'company_description' => 'Hệ thống bán lẻ điện thoại, máy tính, smartwatch và phụ kiện chính hãng.',
                'support_hotline' => '0399162342',
                'support_email' => 'support@htvietnam.vn',
                'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
            ];
            $profile->forceFill([
                'site_name' => 'Sudes Phone',
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
