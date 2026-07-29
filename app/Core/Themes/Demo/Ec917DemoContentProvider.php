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

class Ec917DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC917';

    private const PRESET_KEY = 'ec917-ega-furniture';

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
            'label' => 'EC917 EGA Furniture',
            'description' => 'Cửa hàng nội thất hiện đại với danh mục theo phòng, sale sofa và góc cảm hứng.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC917.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Phòng khách', 'room-living-room.webp'],
                ['Phòng ngủ', 'room-bedroom.webp'],
                ['Nhà bếp', 'room-dining-room.webp'],
                ['Phòng làm việc', 'room-office.webp'],
                ['Đèn trang trí', 'product-lamp-black.webp'],
                ['Kệ lưu giữ', 'product-sideboard-walnut.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec917-'.$name),
                    'description' => 'Nội thất được tuyển chọn cho '.$name.' hiện đại.',
                    'image_url' => '/theme-demo/ec917/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['EC917-SOFA-001', 0, 'Sofa băng phòng khách truyền thống QP115', 31200000, 62400000, 'product-sofa-ivory.webp', 22],
                ['EC917-SOFA-002', 0, 'Sofa băng bọc vải phong cách Scandinavian', 33750000, 67500000, 'product-chair-leather.webp', 17],
                ['EC917-SOFA-003', 0, 'Sofa băng bọc vải siêu rộng Lewis Extra QP243', 31200000, 62400000, 'product-sofa-ivory.webp', 58],
                ['EC917-SOFA-004', 0, 'Sofa băng phòng khách truyền thống QP113', 31200000, 62400000, 'product-bed-upholstered.webp', 64],
                ['EC917-SOFA-005', 0, 'Sofa băng phong cách Mid-Century', 22280000, 44560000, 'product-sofa-ivory.webp', 88],
                ['EC917-SOFA-006', 0, 'Sofa băng Michael QP201', 12400000, 24800000, 'product-chair-leather.webp', 72],
                ['EC917-SOFA-007', 0, 'Ghế Sofa The Sky 222', 31200000, 62400000, 'product-bed-upholstered.webp', 65],
                ['EC917-SOFA-008', 0, 'Bộ Sofa Da 3 Băng Góc Phải QP220', 14250000, 28500000, 'product-dining-set.webp', 82],
            ];

            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image, $sold]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec917-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => $sold,
                    'short_description' => 'Thiết kế thanh lịch, vật liệu bền đẹp và tỷ lệ ngồi thoải mái cho không gian hiện đại.',
                    'detail_content' => '<p>Sản phẩm EGA Furniture chú trọng tính thẩm mỹ, độ bền và trải nghiệm sử dụng lâu dài.</p>',
                    'image_url' => '/theme-demo/ec917/'.$image,
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['BLACK FRIDAY', 'Săn ngay deal nội thất khủng', 'Giảm 50% tất cả sản phẩm'],
                ['NỘI THẤT MỚI', 'Hoàn thiện không gian sống của bạn', 'Ưu đãi đặc biệt cho bộ sưu tập mới'],
            ] as $index => [$badge, $title, $subtitle]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec917-hero-slider',
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'image_url' => '/theme-demo/ec917/hero-interior.webp',
                    'link_url' => '#san-pham',
                    'badge' => $badge,
                    'metadata' => ['button_label' => 'MUA NGAY'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Góc cảm hứng EC917',
                'slug' => 'ec917-goc-cam-hung',
                'description' => 'Ý tưởng bài trí, xu hướng và kinh nghiệm chọn nội thất.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Cách trang trí cầu thang gỗ', 'Trang trí cầu thang là một phần quan trọng trong nội thất của một ngôi nhà hiện đại.', 'room-office.webp'],
                ['Vợ chồng và cách chọn giường ngủ', 'Lựa chọn giường ngủ phù hợp giúp cân bằng công năng và cảm xúc trong phòng ngủ.', 'room-bedroom.webp'],
                ['Sofa gia đình - bài trí sao cho hợp phong thủy?', 'Bố trí sofa đúng cách giúp phòng khách đẹp hơn và tạo luồng di chuyển thuận tiện.', 'room-living-room.webp'],
                ['Sofa góc và bí quyết tăng tài lộc cho ngôi nhà', 'Gợi ý chọn vị trí và màu sắc sofa để hoàn thiện không gian sống.', 'room-dining-room.webp'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $filePath = 'theme-demo/ec917/'.$image;
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => $filePath,
                    'file_url' => '/'.$filePath,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec917-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>EGA Furniture chia sẻ những gợi ý thực tế để bạn xây dựng không gian tiện nghi và có cá tính.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC917 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Sản phẩm', 'url' => route('site.home').'#san-pham'],
                    ['label' => 'Phòng', 'url' => route('site.home').'#danh-muc'],
                    ['label' => 'Khuyến mãi', 'url' => route('site.home').'#khuyen-mai'],
                    ['label' => 'Góc cảm hứng', 'url' => route('site.home').'#cam-hung'],
                    ['label' => 'Hướng dẫn thiết lập', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ EGA Furniture',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn sản phẩm, bố trí và hoàn thiện không gian nội thất.',
                    'body' => '<p>Hãy chia sẻ diện tích, phong cách yêu thích và ngân sách để đội ngũ EGA Furniture tư vấn giải pháp phù hợp.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'Siêu thị nội thất EGA',
                'company_description' => 'Thương hiệu nội thất uy tín và chất lượng, mang đến trải nghiệm mua sắm tiện lợi, hiện đại và phong phú.',
                'support_hotline' => '1900 6750',
                'support_email' => 'support@sapo.vn',
                'support_location' => '70 Lữ Gia, Quận 11, Thành phố Hồ Chí Minh',
                'tax_code' => '12345678999',
            ];
            $profile->forceFill([
                'site_name' => 'EGA Furniture',
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
