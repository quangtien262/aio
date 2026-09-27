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

class Ec915DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC915';

    private const PRESET_KEY = 'ec915-nd-interior';

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
            'label' => 'EC915 ND Interior',
            'description' => 'Studio thiết kế, thi công và bán sản phẩm nội thất cao cấp với hiệu ứng xuất hiện khi cuộn.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC915.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Phòng khách', 'room-living-room.webp'],
                ['Phòng ngủ', 'room-bedroom.webp'],
                ['Văn phòng', 'room-office.webp'],
                ['Phòng bếp', 'room-dining-room.webp'],
                ['Đèn trang trí', 'product-lamp-black.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec915-'.$name),
                    'description' => 'Thiết kế hiện đại, vật liệu chọn lọc và hoàn thiện theo tiêu chuẩn ND Interior.',
                    'image_url' => '/theme-demo/ec915/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['EC915-PRODUCT-001', 0, 'Sofa Haven 3 chỗ ngồi', 11000000, 16000000, 'product-sofa-ivory.webp'],
                ['EC915-PRODUCT-002', 0, 'Ghế lounge Caramel', 9500000, 15000000, 'product-chair-leather.webp'],
                ['EC915-PRODUCT-003', 3, 'Bộ bàn ăn Walnut 06 ghế', 28000000, 34000000, 'product-dining-set.webp'],
                ['EC915-PRODUCT-004', 1, 'Giường bọc nệm Sandstone', 18000000, 23000000, 'product-bed-upholstered.webp'],
                ['EC915-PRODUCT-005', 2, 'Bàn làm việc Oakline', 8650000, 9900000, 'product-desk-oak.webp'],
                ['EC915-PRODUCT-006', 4, 'Đèn thả Noir Dome', 4200000, 5200000, 'product-lamp-black.webp'],
                ['EC915-PRODUCT-007', 0, 'Tủ sideboard Walnut', 13990000, 15990000, 'product-sideboard-walnut.webp'],
                ['EC915-PRODUCT-008', 0, 'Bàn trà Travertine', 7800000, 9200000, 'product-table-stone.webp'],
            ];

            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec915-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 24,
                    'short_description' => 'Tỷ lệ cân đối, vật liệu cao cấp và sắc độ trung tính dành cho không gian hiện đại.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn theo tiêu chuẩn hoàn thiện của ND Interior, chú trọng công năng, độ bền và cảm giác sử dụng.</p>',
                    'image_url' => '/theme-demo/ec915/'.$image,
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['CHÀO MỪNG BẠN ĐẾN VỚI CHÚNG TÔI', 'ND Interior – Chuyên thi công & cung cấp sản phẩm nội thất cao cấp'],
                ['THIẾT KẾ VÌ TRẢI NGHIỆM SỐNG', 'Không gian tinh tế bắt đầu từ một giải pháp đúng'],
            ] as $index => [$badge, $title]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec915-hero-slider',
                    'title' => $title,
                    'subtitle' => 'Thiết kế, thi công trọn gói và sản phẩm nội thất cao cấp cho nhà ở, căn hộ, văn phòng và showroom.',
                    'image_url' => '/theme-demo/ec915/hero-interior.webp',
                    'link_url' => '#gioi-thieu',
                    'badge' => $badge,
                    'metadata' => ['button_label' => 'Khám phá dự án'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Xu hướng nội thất EC915',
                'slug' => 'ec915-xu-huong-noi-that',
                'description' => 'Kiến thức thiết kế, vật liệu và giải pháp tối ưu không gian sống.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Modern Luxury – sự kết hợp giữa giá trị lịch sử và cuộc sống hiện đại', 'Tìm điểm cân bằng giữa đường nét sang trọng, vật liệu tự nhiên và tiện nghi đương đại.', 'room-living-room.webp',
                    '<h2>Bắt đầu từ bảng màu nền</h2><p>Chọn màu kem, nâu gỗ hoặc xám ấm làm nền để các món đồ có đường nét nổi bật không cạnh tranh với nhau. Thử mẫu màu dưới ánh sáng ban ngày và ánh đèn trước khi quyết định.</p><h2>Tạo điểm nhấn có chọn lọc</h2><p>Một chiếc ghế da hoặc bàn đá có thể làm điểm nhấn cho phòng khách. Giữ lối đi thoáng, đo kích thước đồ nội thất và ưu tiên sự thoải mái khi sử dụng hằng ngày.</p>'],
                ['5 nguyên tắc bố trí văn phòng giúp tăng cảm hứng làm việc', 'Ánh sáng, tỷ lệ và lưu thông là ba nền tảng tạo nên một nơi làm việc hiệu quả.', 'room-office.webp',
                    '<h2>Sắp xếp theo thói quen làm việc</h2><p>Đặt bàn ở vị trí ít bị gián đoạn, bố trí vật dụng thường dùng trong tầm với và dành một khu riêng cho tài liệu. Chừa khoảng trống để kéo ghế và di chuyển thuận tiện.</p><h2>Hoàn thiện năm yếu tố cơ bản</h2><p>Kết hợp ánh sáng tự nhiên, đèn bàn, ghế có thể điều chỉnh, nơi lưu trữ gọn gàng và cách đi dây an toàn. Kiểm tra độ chói trên màn hình trước khi cố định vị trí bàn.</p>'],
                ['Không gian bếp mở: kết nối gia đình bằng thiết kế', 'Một căn bếp được quy hoạch tốt có thể trở thành nơi sinh hoạt chung của ngôi nhà.', 'room-dining-room.webp',
                    '<h2>Phân chia công năng rõ ràng</h2><p>Sắp xếp khu sơ chế, nấu nướng và bàn ăn theo trình tự sử dụng. Tránh để ghế ăn hoặc cánh tủ mở ra chắn lối đi chính giữa bếp và phòng khách.</p><h2>Đồng bộ vật liệu và ánh sáng</h2><p>Dùng một vài màu chủ đạo xuyên suốt các khu vực. Chọn bề mặt dễ vệ sinh quanh nơi nấu và bổ sung ánh sáng riêng cho bàn ăn để tạo cảm giác ấm cúng.</p>'],
                ['Phòng ngủ nhỏ: chọn nội thất để không gian thoáng hơn', 'Gợi ý lựa chọn giường, tủ và ánh sáng theo diện tích thực tế.', 'room-bedroom.webp',
                    '<h2>Đo trước khi chọn giường</h2><p>Đánh dấu kích thước giường trên mặt sàn, kiểm tra khoảng mở cửa và lối đi hai bên. Một chiếc giường vừa vặn sẽ hữu ích hơn mẫu lớn khiến phòng khó di chuyển.</p><h2>Tận dụng nơi lưu trữ</h2><p>Cân nhắc ngăn kéo dưới giường hoặc tủ cao phù hợp chiều cao phòng. Dùng rèm sáng màu và đèn đầu giường gọn để giảm số đồ đặt trên sàn.</p>'],
                ['Chọn sofa cho phòng khách: cân bằng kích thước và chất liệu', 'Những điểm cần đối chiếu trước khi chọn sofa cho gia đình.', 'product-sofa-ivory.webp',
                    '<h2>Kiểm tra kích thước và đường vận chuyển</h2><p>Đo phòng khách, cửa ra vào, hành lang và thang máy trước khi đặt mua. Thử vị trí sofa bằng băng đánh dấu để đánh giá lối đi và khoảng cách tới bàn trà.</p><h2>Chọn chất liệu theo cách sử dụng</h2><p>Gia đình có trẻ nhỏ nên tìm hiểu khả năng vệ sinh và hướng dẫn chăm sóc của từng loại vải. Ngồi thử để kiểm tra độ sâu, độ cao và cảm giác tựa lưng.</p>'],
                ['Ánh sáng trang trí: tạo điểm nhấn cho bàn ăn', 'Phối hợp đèn thả với ánh sáng chung để bàn ăn dễ sử dụng và hài hòa.', 'product-lamp-black.webp',
                    '<h2>Chọn tỷ lệ phù hợp</h2><p>Đối chiếu kích thước chao đèn với mặt bàn và chiều cao trần. Kiểm tra góc nhìn khi ngồi để đèn không che khuất người đối diện hoặc gây chói mắt.</p><h2>Phối hợp nhiều nguồn sáng</h2><p>Đèn thả có thể kết hợp đèn trần hoặc đèn hắt để căn phòng không quá tối ở các góc. Nhờ đơn vị lắp đặt kiểm tra khả năng chịu tải và đường điện trước khi thi công.</p>'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image, $body]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec915/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec915-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.e($excerpt).'</p>'.$body,
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index < 3,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC915 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Dự án', 'url' => route('site.home').'#danh-muc'],
                    ['label' => 'Quy trình', 'url' => route('site.home').'#quy-trinh'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ ND Interior',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn thiết kế, thi công và sản phẩm nội thất.',
                    'body' => '<p>Hãy chia sẻ nhu cầu, ngân sách và phong cách bạn yêu thích. Đội ngũ ND Interior sẽ cùng bạn xây dựng phương án phù hợp.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'ND Interior',
                'company_description' => 'Thiết kế, thi công và cung cấp sản phẩm nội thất cao cấp cho không gian sống hiện đại.',
                'support_hotline' => '0399162342',
                'support_email' => 'support@htvietnam.vn',
                'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội',
            ];
            $profile->forceFill([
                'site_name' => 'ND Interior',
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
