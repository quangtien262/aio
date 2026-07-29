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

class Book920DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'BOOK920';
    private const PRESET_KEY = 'book920-bookle';

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
            'label' => 'BOOK920 Bookle',
            'description' => 'Nhà sách trực tuyến hiện đại với tuyển tập sách đa dạng, ưu đãi rõ ràng và trải nghiệm mua sắm thân thiện.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho BOOK920.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Văn học', 'book-1.webp'],
                ['Kinh tế', 'book-2.webp'],
                ['Tâm lý', 'book-3.webp'],
                ['Giáo dục', 'book-4.webp'],
                ['Truyện tranh', 'book-5.webp'],
                ['Kỹ năng sống', 'book-6.webp'],
                ['Lịch sử', 'book-7.webp'],
                ['Thiếu nhi', 'book-8.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('book920-'.$name),
                    'description' => 'Những đầu sách được tuyển chọn kỹ, phù hợp cho nhiều lứa tuổi và sở thích đọc.',
                    'image_url' => '/theme-demo/book920/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['BOOK920-FEATURED-001', 0, 'Trăng Và Những Mùa Hoa', 146000, 210000, 'book-1.webp'],
                ['BOOK920-FEATURED-002', 1, 'Tư Duy Tăng Trưởng Bền Vững', 290000, 340000, 'book-2.webp'],
                ['BOOK920-FEATURED-003', 2, 'Thấu Hiểu Chính Mình', 178000, 235000, 'book-3.webp'],
                ['BOOK920-FEATURED-004', 3, 'Nghệ Thuật Học Tập', 165000, 220000, 'book-4.webp'],
                ['BOOK920-FEATURED-005', 4, 'Học Viện Siêu Anh Hùng', 99000, 145000, 'book-5.webp'],
                ['BOOK920-FEATURED-006', 5, 'Con Đường Mới', 135000, 180000, 'book-6.webp'],
                ['BOOK920-FEATURED-007', 6, 'Dòng Chảy Lịch Sử Việt', 198000, 260000, 'book-7.webp'],
                ['BOOK920-FEATURED-008', 7, 'Cậu Bé Đọc Sách Trên Trăng', 89000, 125000, 'book-8.webp'],
                ['BOOK920-HOT-009', 0, 'Một Centimet Giữa Anh Và Em', 146000, 251000, 'book-1.webp'],
                ['BOOK920-HOT-010', 1, 'Nguyên Tắc Tự Do Tài Chính', 268000, 310000, 'book-2.webp'],
                ['BOOK920-HOT-011', 3, 'Giáo Trình Tâm Lý Học Đại Cương', 50000, 70000, 'book-3.webp'],
                ['BOOK920-HOT-012', 4, 'Lớp Học Ám Sát - Tập 21', 18000, 22000, 'book-5.webp'],
            ];

            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('book920-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 24,
                    'short_description' => 'Ấn bản được Bookle tuyển chọn, đóng gói cẩn thận và giao hàng nhanh chóng.',
                    'detail_content' => '<p>Bookle mang đến những cuốn sách giàu giá trị với thông tin minh bạch, mức giá hợp lý và chính sách đổi trả rõ ràng.</p>',
                    'image_url' => '/theme-demo/book920/'.$image,
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['KHÔNG GIAN ĐỌC CẢM HỨNG', 'Khám phá thế giới sách tại Bookle'],
                ['MỖI TRANG SÁCH, MỘT HÀNH TRÌNH', 'Tìm cuốn sách tiếp theo dành cho bạn'],
            ] as $index => [$badge, $title]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'book920-hero-slider',
                    'title' => $title,
                    'subtitle' => 'Sách được tuyển chọn kỹ, giao hàng toàn quốc, thanh toán linh hoạt và đổi trả thuận tiện.',
                    'image_url' => '/theme-demo/book920/hero-bookstore.png',
                    'link_url' => '#sach-noi-bat',
                    'badge' => $badge,
                    'metadata' => ['button_label' => 'Mua ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Góc đọc Bookle',
                'slug' => 'book920-goc-doc',
                'description' => 'Cảm hứng đọc sách, giới thiệu tác phẩm và những câu chuyện đáng nhớ.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Tận dụng quỹ thời gian với hai giờ đầu tiên', 'Những cuốn sách giúp bạn bắt đầu ngày mới chủ động và hiệu quả.', 'book-6.webp'],
                ['Thói quen nhỏ làm nên thành công lớn', 'Các ý tưởng thực tế để xây dựng một đời sống giàu cảm hứng.', 'book-2.webp'],
                ['Những hỗn loạn chất chồng bên trong người trẻ', 'Góc nhìn nhẹ nhàng về sức khỏe tinh thần và hành trình trưởng thành.', 'book-3.webp'],
                ['Chuyện kể từ Sài Gòn', 'Một chuyến đi qua ký ức đô thị bằng những trang viết gần gũi.', 'book-7.webp'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/book920/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);

                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('book920-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Bookle chia sẻ những góc nhìn, thói quen và gợi ý nhỏ để hành trình đọc sách của bạn luôn mới mẻ và giàu cảm hứng.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'BOOK920 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Dịch vụ', 'url' => route('site.home').'#dich-vu'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ Bookle',
                    'status' => 'published',
                    'excerpt' => 'Hỗ trợ đơn hàng, đổi trả và tư vấn chọn sách phù hợp.',
                    'body' => '<p>Đội ngũ Bookle luôn sẵn sàng hỗ trợ để trải nghiệm mua sách của bạn thuận tiện, dễ chịu và an tâm.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'Bookle',
                'company_description' => 'Không gian sách ấm cúng, thân thiện như một thư viện cá nhân dành cho mọi độc giả.',
                'support_hotline' => '1900 9477',
                'support_email' => 'admin@demo037210.web30s.vn',
                'support_location' => '344 Huỳnh Tấn Phát, Phường Bình Thuận, Quận 7, TP.HCM',
            ];
            $profile->forceFill([
                'site_name' => 'Bookle',
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
            if ($landing) {
                $newsBlock = $landing->blocks()->where('block_type', 'latest_posts')->first();
                if ($newsBlock) {
                    $newsBlock->forceFill([
                        'settings' => [
                            ...(array) $newsBlock->settings,
                            'category_id' => $postCategory->id,
                            'featured_only' => false,
                        ],
                    ])->save();
                }
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
