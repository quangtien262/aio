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

class Ec904DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC904';
    private const PRESET_KEY = 'ec904-pocomall';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }

    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'EC904 PocoMall',
            'description' => 'Siêu thị trực tuyến đa ngành với mega-menu, nhóm sản phẩm lớn và homepage cấu hình bằng Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC904.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Điện thoại - Máy tính bảng', 'Thiết bị di động cho công việc và giải trí.', 'phone-front.webp'],
                ['Phụ kiện - Thiết bị số', 'Tai nghe, đồng hồ và phụ kiện thông minh.', 'headset.webp'],
                ['Máy ảnh - Quay phim', 'Máy ảnh và thiết bị sáng tạo nội dung.', 'camera.webp'],
                ['Điện gia dụng - Nhà bếp', 'Thiết bị giúp việc nhà nhẹ nhàng hơn.', 'air-fryer.webp'],
                ['Laptop - Thiết bị IT', 'Laptop mỏng nhẹ và thiết bị văn phòng.', 'laptop.webp'],
                ['Máy chơi game - Trò chơi', 'Thiết bị giải trí tại gia hiện đại.', 'game-console.webp'],
                ['Trang sức - Sành điệu', 'Phụ kiện thanh lịch cho phong cách riêng.', 'earrings-gold.webp'],
                ['Thời trang - Làm đẹp', 'Trang phục và giày dép dễ phối mỗi ngày.', 'sweater-blue.webp'],
                ['Nhà cửa đời sống', 'Nội thất và vật dụng nâng tầm không gian.', 'chair-mustard.webp'],
                ['Âm thanh - Giải trí', 'Loa và thiết bị âm thanh di động.', 'speaker.webp'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec904-'.$name),
                    'description' => $description,
                    'image_url' => '/theme-demo/ec904/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $groups = [
                'TECH' => [
                    ['NovaPhone X Pro 256GB', 21990000, 24990000, 0, 'phone-front.webp'],
                    ['NovaPhone X Graphite 128GB', 16990000, 19990000, 0, 'phone-back.webp'],
                    ['Tai nghe Gaming SoundMax', 1290000, 1690000, 1, 'headset.webp'],
                    ['Máy ảnh Mirrorless Vision M5', 18900000, 21900000, 2, 'camera.webp'],
                    ['Nồi chiên không dầu HomeChef', 1990000, 2690000, 3, 'air-fryer.webp'],
                    ['Laptop NovaBook Air 14', 22990000, 25990000, 4, 'laptop.webp'],
                    ['Máy chơi game NovaStation', 11990000, 13990000, 5, 'game-console.webp'],
                    ['Đồng hồ thông minh Active S', 3290000, 3990000, 1, 'smartwatch.webp'],
                    ['Tai nghe không dây AirSound', 2390000, 2990000, 1, 'earbuds.webp'],
                    ['Loa di động MiniBeat', 1090000, 1390000, 9, 'speaker.webp'],
                    ['Smart TV Vision 55 inch', 12990000, 15990000, 9, 'television.webp'],
                    ['Robot hút bụi CleanBot', 6990000, 8490000, 3, 'robot-vacuum.webp'],
                ],
                'FASHION' => [
                    ['Giày chạy bộ Cloud White', 790000, 1790000, 7, 'shoe-white.webp'],
                    ['Giày thể thao Urban Black', 890000, 1890000, 7, 'shoe-black.webp'],
                    ['Giày cổ cao Pastel Move', 990000, 1990000, 7, 'shoe-pastel.webp'],
                    ['Giày casual Canvas Beige', 690000, 1490000, 7, 'shoe-beige.webp'],
                    ['Áo len xanh Soft Knit', 590000, 990000, 7, 'sweater-blue.webp'],
                    ['Khuyên tai tròn Golden Glow', 490000, 790000, 6, 'earrings-gold.webp'],
                    ['Túi da City Satchel', 1290000, 1890000, 7, 'handbag-tan.webp'],
                    ['Ghế thư giãn Mustard Home', 3690000, 4990000, 8, 'chair-mustard.webp'],
                ],
                'HOT' => [
                    ['NovaPhone X ưu đãi online', 20990000, 24990000, 0, 'phone-front.webp'],
                    ['Tai nghe AirSound giá tốt', 2190000, 2990000, 1, 'earbuds.webp'],
                    ['Giày Cloud White siêu nhẹ', 690000, 1790000, 7, 'shoe-white.webp'],
                    ['Đồng hồ Active S năng động', 2990000, 3990000, 1, 'smartwatch.webp'],
                    ['Nồi chiên HomeChef bán chạy', 1790000, 2690000, 3, 'air-fryer.webp'],
                ],
            ];
            $productCount = 0;
            $sort = 0;
            foreach ($groups as $group => $definitions) {
                foreach ($definitions as $index => [$name, $price, $originalPrice, $categoryIndex, $image]) {
                    $product = CatalogProduct::query()->create([
                        'catalog_category_id' => $categories[$categoryIndex]->id,
                        'name' => $name,
                        'slug' => Str::slug('ec904-'.$group.'-'.$name),
                        'sku' => 'EC904-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                        'price' => $price,
                        'original_price' => $originalPrice,
                        'stock' => 50,
                        'short_description' => 'Sản phẩm tuyển chọn, giao nhanh và hỗ trợ đổi trả minh bạch.',
                        'detail_content' => '<p>Sản phẩm được PocoMall chọn lọc theo tiêu chí chất lượng, thiết kế và trải nghiệm sử dụng. Thông tin bảo hành và giao nhận được công bố rõ ràng.</p>',
                        'image_url' => '/theme-demo/ec904/'.$image,
                        'is_featured' => $group === 'HOT',
                        'is_highlight' => in_array($group, ['HOT', 'TECH'], true),
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]);
                    $this->record($product);
                    $productCount++;
                }
            }

            foreach ([
                ['Siêu sale đa ngành', 'Công nghệ, thời trang và đồ dùng nhà cửa cùng ưu đãi hấp dẫn.', 'hero-super-sale.webp'],
                ['Nhà đẹp, giá vui', 'Nội thất và gia dụng chọn lọc cho không gian hiện đại.', 'promo-home.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec904-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec904/'.$image,
                    'link_url' => '#dien-thoai',
                    'badge' => 'THIÊN ĐƯỜNG MUA SẮM',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Mua ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tin mua sắm PocoMall',
                'slug' => 'ec904-tin-mua-sam',
                'description' => 'Tin công nghệ, tiêu dùng và đời sống mới nhất.',
            ]);
            $this->record($postCategory);
            $postDefinitions = [
                ['Điện thoại màn hình gập ngày càng dễ tiếp cận', 'Thiết kế gọn nhẹ và công nghệ bản lề mới giúp trải nghiệm linh hoạt hơn.', 'news-foldable.webp'],
                ['Cách làm bún bò thơm ngon cho cả gia đình', 'Công thức cân bằng vị ngọt thanh, sả và các loại rau ăn kèm.', 'news-noodles.webp'],
                ['Chọn TV thông minh phù hợp không gian sống', 'Kích thước, độ phân giải và khoảng cách xem là ba yếu tố quan trọng.', 'news-tv.webp'],
                ['Phụ kiện đeo thông minh cho ngày năng động', 'Tai nghe và đồng hồ giúp công việc lẫn luyện tập liền mạch hơn.', 'news-wearables.webp'],
            ];
            foreach ($postDefinitions as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title, 'file_path' => '', 'file_url' => '/theme-demo/ec904/'.$image,
                    'mime_type' => 'image/webp', 'size' => 0, 'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec904-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>PocoMall tổng hợp thông tin hữu ích để bạn lựa chọn sản phẩm phù hợp hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'EC904 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Giới thiệu', 'url' => $home.'#gioi-thieu'],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Tin mới nhất', 'url' => route('site.blog.index')],
                    ['label' => 'Câu hỏi thường gặp', 'url' => route('site.contact')],
                    ['label' => 'Tuyển dụng', 'url' => route('site.contact')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ PocoMall', 'status' => 'published', 'excerpt' => 'Tư vấn sản phẩm và hỗ trợ đơn hàng.', 'body' => '<p>Đội ngũ PocoMall luôn sẵn sàng hỗ trợ bạn chọn sản phẩm, kiểm tra đơn hàng và giải đáp chính sách.</p>', 'publish_at' => now()],
            );
            if ($page->wasRecentlyCreated) $this->record($page);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'PocoMall',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'POCOMALL',
                    'company_description' => 'Thiên đường mua sắm đa ngành với sản phẩm chọn lọc, giá tốt và giao hàng nhanh.',
                    'support_hotline' => '1900 6750',
                    'support_email' => 'support@pocomall.vn',
                    'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội',
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'categories' => count($categoryDefinitions), 'products' => $productCount, 'banners' => 2,
                    'post_categories' => 1, 'posts' => count($postDefinitions), 'media' => count($postDefinitions),
                    'pages' => $page->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
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
            [CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'],
            [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
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
