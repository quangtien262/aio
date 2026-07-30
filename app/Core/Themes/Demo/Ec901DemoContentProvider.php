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

class Ec901DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC901';

    private const PRESET_KEY = 'ec901-tempo-watch';

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
            'label' => 'EC901 Tempo Watch',
            'description' => 'Cửa hàng đồng hồ cao cấp với homepage cấu hình hoàn toàn theo Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC901.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Đồng hồ nam', 'Đồng hồ cơ khí và quartz dành cho quý ông.', '/theme-demo/ec901/watch-men.webp'],
                ['Đồng hồ nữ', 'Thiết kế thanh lịch dành cho phái đẹp.', '/theme-demo/ec901/watch-women.webp'],
                ['Đồng hồ trẻ em', 'Những mẫu đồng hồ trẻ trung và dễ sử dụng.', '/theme-demo/ec901/women-pink.webp'],
                ['Phụ kiện đồng hồ', 'Dây đeo và phụ kiện chăm sóc đồng hồ.', '/theme-demo/ec901/automatic-rose.webp'],
                ['Smart watch', 'Đồng hồ thông minh cho nhịp sống năng động.', '/theme-demo/ec901/smartwatch-black.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec901-'.$name),
                    'description' => $description,
                    'image_url' => $image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['Tempo Classic Silver 40mm', 4050000, 4590000, 0, '/theme-demo/ec901/classic-silver.webp'],
                ['Aurelius Open Heart Rose 42mm', 12890000, 16990000, 0, '/theme-demo/ec901/automatic-rose.webp'],
                ['Norden Sport Chronograph 44mm', 24500000, 28990000, 0, '/theme-demo/ec901/sport-black.webp'],
                ['Vela Lady Pearl 28mm', 3589000, 4200000, 1, '/theme-demo/ec901/women-pink.webp'],
                ['Tempo Smart One 44mm', 6890000, 7900000, 4, '/theme-demo/ec901/smartwatch-black.webp'],
                ['Monarch Diver Blue 41mm', 23690000, 29530000, 0, '/theme-demo/ec901/diver-blue.webp'],
                ['Aster Steel Moonphase 40mm', 2764000, 3455000, 0, '/theme-demo/ec901/classic-silver.webp'],
                ['Tempo Black Edition 42mm', 3250000, 3790000, 0, '/theme-demo/ec901/sport-black.webp'],
                ['Aurelius Skeleton Elite 41mm', 46000000, 49900000, 0, '/theme-demo/ec901/automatic-rose.webp'],
                ['Vela Rose Petite 26mm', 7500000, 8800000, 1, '/theme-demo/ec901/women-pink.webp'],
                ['Norden Heritage Automatic 40mm', 9600000, 11500000, 0, '/theme-demo/ec901/diver-blue.webp'],
                ['Monarch GMT Steel 42mm', 15900000, 18900000, 0, '/theme-demo/ec901/classic-silver.webp'],
            ];

            foreach ($productDefinitions as $index => [$name, $price, $originalPrice, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec901-'.$name),
                    'sku' => 'EC901-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 30,
                    'short_description' => 'Đồng hồ thiết kế tinh tế, bảo hành chính hãng và giao hàng toàn quốc.',
                    'detail_content' => '<p>Mẫu đồng hồ được tuyển chọn cho phong cách hiện đại, chú trọng độ hoàn thiện, khả năng vận hành ổn định và trải nghiệm đeo thoải mái.</p>',
                    'image_url' => $image,
                    'is_featured' => $index < 6,
                    'is_highlight' => $index < 6,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Khoảnh khắc tạo nên phong cách', 'Bộ sưu tập cơ khí dành cho những dấu ấn khác biệt.', '/theme-demo/ec901/hero-watches.webp'],
                ['Bản lĩnh trên từng chuyển động', 'Ưu đãi đặc biệt cho bộ sưu tập đồng hồ nam mới.', '/theme-demo/ec901/watch-men.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec901-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => '#deal-chop-nhoang',
                    'badge' => 'TEMPO SIGNATURE',
                    'metadata' => ['eyebrow' => 'TEMPO SIGNATURE', 'summary' => $summary, 'button_label' => 'Khám phá ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tạp chí đồng hồ',
                'slug' => 'ec901-tap-chi-dong-ho',
                'description' => 'Kiến thức chọn mua, sử dụng và bảo quản đồng hồ.',
            ]);
            $this->record($postCategory);
            $postDefinitions = [
                ['Cách chọn đồng hồ phù hợp với cổ tay', 'Kích thước mặt, độ dài dây và phong cách là ba yếu tố giúp chiếc đồng hồ cân đối hơn.', '/theme-demo/ec901/watch-women.webp'],
                ['Bảo quản đồng hồ cơ đúng cách', 'Những thói quen đơn giản giúp bộ máy cơ vận hành ổn định và giữ vẻ đẹp lâu dài.', '/theme-demo/ec901/watch-men.webp'],
                ['Xu hướng đồng hồ tối giản năm 2026', 'Mặt số tinh gọn, màu trung tính và đường nét thanh mảnh tiếp tục được yêu thích.', '/theme-demo/ec901/hero-watches.webp'],
                ['Phân biệt đồng hồ cơ và đồng hồ quartz', 'Hiểu rõ nguyên lý, độ chính xác và chi phí bảo dưỡng trước khi lựa chọn.', '/theme-demo/ec901/promo-main.webp'],
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
                    'slug' => Str::slug('ec901-'.$title),
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
                'name' => 'EC901 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Thương hiệu nổi bật', 'url' => $home.'#thuong-hieu'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Hệ thống cửa hàng', 'url' => route('site.contact')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Liên hệ Tempo Watch.', 'body' => '<p>Đội ngũ Tempo luôn sẵn sàng tư vấn mẫu đồng hồ phù hợp với phong cách và ngân sách của bạn.</p>', 'publish_at' => now()],
            );
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'Tempo Watch Store',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'TEMPO',
                    'company_description' => 'Đồng hồ tuyển chọn cho phong cách hiện đại và những dấu ấn khác biệt.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'support@tempo.vn',
                    'support_location' => 'Xuân Thủy, Cầu Giấy, Hà Nội',
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
