<?php

namespace App\Core\Themes;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ThemeDemoContentGenerator
{
    public function __construct(
        private readonly CmsMenuLocationRegistry $menuLocationRegistry,
    ) {
    }

    public function presets(): array
    {
        return array_map(
            fn (array $preset): array => Arr::only($preset, ['key', 'label', 'description']),
            $this->presetDefinitions(),
        );
    }

    public function generate(string $themeKey, string $presetKey): array
    {
        $preset = collect($this->presetDefinitions())->firstWhere('key', $presetKey);

        if (! is_array($preset)) {
            throw new InvalidArgumentException('Preset demo content không hợp lệ.');
        }

        $siteProfile = SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            [
                'website_type' => 'ecommerce',
                'active_theme_key' => $themeKey,
                'is_setup_completed' => false,
                'completed_steps' => [],
                'branding' => [],
            ],
        );

        $timestamp = Carbon::now();

        return DB::transaction(function () use ($preset, $siteProfile, $themeKey, $timestamp): array {
            $this->replaceMenuLocations();
            $this->clearExistingDemoContent();

            $newsCategory = CmsCategory::query()->create([
                'name' => 'Tin '.$preset['short_label'],
                'slug' => Str::slug('tin-'.$preset['key']),
                'description' => 'Chuyên mục cập nhật nội dung demo cho theme '.$themeKey,
                'meta_title' => 'Tin tức '.$preset['label'],
                'meta_description' => 'Tin tức và nội dung demo cho '.$preset['label'],
            ]);

            $this->seedPages($preset, $timestamp);
            $postCount = $this->seedPosts($preset, $newsCategory->id, $timestamp);
            $categoryMap = $this->seedCatalog($preset, $timestamp);
            $menuCount = $this->seedMenus($preset, $categoryMap);
            $bannerCount = $this->seedBanners($preset, $themeKey, $timestamp);

            $siteProfile->forceFill([
                'website_type' => 'ecommerce',
                'active_theme_key' => $themeKey,
            ])->save();

            return [
                'preset' => Arr::only($preset, ['key', 'label', 'description']),
                'counts' => [
                    'pages' => 2,
                    'posts' => $postCount,
                    'menus' => $menuCount,
                    'catalog_categories' => CatalogCategory::query()->count(),
                    'catalog_products' => CatalogProduct::query()->count(),
                    'banners' => $bannerCount,
                ],
            ];
        });
    }

    private function replaceMenuLocations(): void
    {
        $locations = collect($this->menuLocationRegistry->all())
            ->concat([
                ['label' => 'Primary Navigation', 'value' => 'primary-navigation'],
                ['label' => 'Product Navigation', 'value' => 'product-navigation'],
            ])
            ->unique('value')
            ->values()
            ->all();

        $this->menuLocationRegistry->save($locations);
    }

    private function clearExistingDemoContent(): void
    {
        SiteBanner::query()->delete();
        CmsMenu::query()->delete();
        CmsPost::query()->delete();
        CmsPage::query()->delete();
        CmsCategory::query()->delete();
        CatalogProduct::query()->delete();
        CatalogCategory::query()->delete();
    }

    private function seedPages(array $preset, Carbon $timestamp): void
    {
        $pages = [
            [
                'title' => 'Giới thiệu',
                'slug' => 'gioi-thieu',
                'excerpt' => 'Hồ sơ năng lực demo cho '.$preset['label'],
                'body' => '<h2>'.$preset['company_name'].'</h2><p>'.$preset['description'].'</p><p>Website demo này được tạo để review theme '.$preset['theme_flavor'].' và khả năng mapping dữ liệu thật từ CMS/Catalog.</p>',
            ],
            [
                'title' => 'Liên hệ',
                'slug' => 'lien-he',
                'excerpt' => 'Kênh liên hệ tư vấn và CSKH',
                'body' => '<h2>Liên hệ tư vấn</h2><p>Hotline: 1900 6760</p><p>Email: hello@'.$preset['domain'].'</p><p>Địa chỉ: '.$preset['address'].'</p>',
            ],
        ];

        foreach ($pages as $index => $page) {
            CmsPage::query()->create([
                'title' => $page['title'],
                'slug' => $page['slug'],
                'status' => 'published',
                'excerpt' => $page['excerpt'],
                'body' => $page['body'],
                'meta_title' => $page['title'].' | '.$preset['company_name'],
                'meta_description' => $page['excerpt'],
                'template' => 'default',
                'publish_at' => $timestamp->copy()->subDays(10 - $index),
            ]);
        }
    }

    private function seedPosts(array $preset, int $categoryId, Carbon $timestamp): int
    {
        $titles = [
            'Top deal mới tuần này cho '.$preset['short_label'],
            '5 xu hướng mua sắm '.$preset['short_label'].' đang tăng mạnh',
            'Gợi ý chọn sản phẩm nổi bật cho chiến dịch cuối tuần',
            'Cách tối ưu landing page bán '.$preset['short_label'].' theo mùa',
        ];

        foreach ($titles as $index => $title) {
            CmsPost::query()->create([
                'title' => $title,
                'slug' => Str::slug($title),
                'status' => 'published',
                'excerpt' => 'Nội dung demo cho ngành '.$preset['label'].' nhằm kiểm tra block tin tức của theme.',
                'body' => '<p>'.$preset['description'].'</p><p>Bài viết demo số '.($index + 1).' dùng để hiển thị tin mới trên website.</p>',
                'meta_title' => $title,
                'meta_description' => 'Tin tức demo cho '.$preset['label'],
                'category_id' => $categoryId,
                'publish_at' => $timestamp->copy()->subDays($index + 1),
            ]);
        }

        return count($titles);
    }

    /**
     * @return array<int, array{parent: CatalogCategory, children: array<int, CatalogCategory>}>
     */
    private function seedCatalog(array $preset, Carbon $timestamp): array
    {
        $categoryMap = [];
        $featuredCounter = 0;

        foreach ($preset['departments'] as $parentIndex => $department) {
            $parent = CatalogCategory::query()->create([
                'name' => $department['name'],
                'slug' => Str::slug($preset['key'].'-'.$department['name']),
                'description' => 'Danh mục '.$department['name'].' cho preset '.$preset['label'],
                'image_url' => $this->imageUrl($preset['key'].'-cat-'.$parentIndex, 320, 320),
                'sort_order' => $parentIndex,
                'is_active' => true,
            ]);

            $children = [];
            foreach ($department['children'] as $childIndex => $childName) {
                $child = CatalogCategory::query()->create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'slug' => Str::slug($preset['key'].'-'.$department['name'].'-'.$childName),
                    'description' => 'Nhóm '.$childName.' thuộc '.$department['name'],
                    'image_url' => $this->imageUrl($preset['key'].'-child-'.$parentIndex.'-'.$childIndex, 320, 320),
                    'sort_order' => $childIndex,
                    'is_active' => true,
                ]);

                $children[] = $child;

                foreach (range(1, 4) as $productIndex) {
                    $productName = $this->buildProductName($preset, $department['name'], $childName, $productIndex);
                    $price = $this->buildPrice($parentIndex, $childIndex, $productIndex);
                    $isFeatured = $featuredCounter < 8;
                    $createdAt = $timestamp->copy()->subMinutes(($parentIndex * 10) + ($childIndex * 4) + $productIndex);

                    $product = CatalogProduct::query()->create([
                        'catalog_category_id' => $child->id,
                        'name' => $productName,
                        'slug' => Str::slug($productName),
                        'sku' => strtoupper(Str::slug($preset['key'].'-'.$parentIndex.'-'.$childIndex.'-'.$productIndex, '-')),
                        'price' => $price,
                        'original_price' => $price + (($productIndex + 1) * 150000),
                        'stock' => 20 + ($parentIndex * 3) + $productIndex,
                        'short_description' => 'Mẫu demo cho '.$childName.' trong preset '.$preset['label'].'.',
                        'detail_content' => $this->buildProductDetailContent($preset, $department['name'], $childName, $productName),
                        'highlights' => $this->buildProductHighlights($preset, $department['name'], $childName),
                        'usage_terms' => $this->buildUsageTerms($preset, $department['name']),
                        'usage_location' => $this->buildUsageLocation($preset),
                        'image_url' => $this->imageUrl($preset['key'].'-product-'.$parentIndex.'-'.$childIndex.'-'.$productIndex, 640, 420),
                        'sold_count' => 3 + ($parentIndex * 2) + $productIndex,
                        'deal_end_at' => $timestamp->copy()->addDays(10 + $parentIndex + $productIndex),
                        'is_featured' => $isFeatured,
                        'sort_order' => $productIndex,
                        'is_active' => true,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    foreach ($this->buildGalleryImages($preset, $parentIndex, $childIndex, $productIndex) as $galleryIndex => $galleryImage) {
                        CatalogProductImage::query()->create([
                            'catalog_product_id' => $product->id,
                            'image_url' => $galleryImage,
                            'sort_order' => $galleryIndex,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                    }

                    if ($isFeatured) {
                        $featuredCounter++;
                    }
                }
            }

            $categoryMap[] = ['parent' => $parent, 'children' => $children];
        }

        return $categoryMap;
    }

    private function seedMenus(array $preset, array $categoryMap): int
    {
        $productItems = collect($categoryMap)->map(function (array $entry, int $index): array {
            /** @var CatalogCategory $parent */
            $parent = $entry['parent'];

            return [
                'label' => $parent->name,
                'url' => '/danh-muc/'.$parent->slug,
                'target' => '_self',
                'icon' => $index === 0 ? '🔥' : '▣',
                'highlight' => $index === 0,
                'children' => collect($entry['children'])
                    ->map(fn (CatalogCategory $child): array => [
                        'label' => $child->name,
                        'url' => '/danh-muc/'.$child->slug,
                        'target' => '_self',
                    ])
                    ->all(),
            ];
        })->all();

        CmsMenu::query()->create([
            'name' => 'Primary Navigation',
            'location' => 'primary-navigation',
            'items' => [
                ['label' => 'Tin tức', 'url' => '/tin-tuc', 'target' => '_self'],
                ['label' => 'Giới thiệu', 'url' => '/gioi-thieu', 'target' => '_self'],
                ['label' => 'Liên hệ', 'url' => '/lien-he', 'target' => '_self'],
            ],
        ]);

        CmsMenu::query()->create([
            'name' => 'Product Navigation',
            'location' => 'product-navigation',
            'items' => $productItems,
        ]);

        return 2;
    }

    private function seedBanners(array $preset, string $themeKey, Carbon $timestamp): int
    {
        $records = [
            [
                'placement' => 'hero-main',
                'title' => $preset['hero_title'],
                'subtitle' => $preset['hero_subtitle'],
                'badge' => $preset['hero_badge'],
                'metadata' => [
                    'eyebrow' => $preset['hero_eyebrow'],
                    'summary' => $preset['description'],
                    'button_label' => 'Mua ngay',
                ],
                'image_url' => $this->imageUrl($preset['key'].'-hero-main', 960, 520),
                'link_url' => '#featured',
                'sort_order' => 0,
            ],
        ];

        foreach (array_slice($preset['departments'], 0, 4) as $index => $department) {
            $departmentSlug = Str::slug($preset['key'].'-'.$department['name']);
            $records[] = [
                'placement' => 'hero-side',
                'title' => $department['name'],
                'subtitle' => 'Ưu đãi mới cho '.$department['children'][0],
                'badge' => null,
                'metadata' => [],
                'image_url' => $this->imageUrl($preset['key'].'-hero-side-'.$index, 360, 180),
                'link_url' => '/danh-muc/'.$departmentSlug,
                'sort_order' => $index,
            ];
        }

        foreach ($records as $record) {
            SiteBanner::query()->create([
                'theme_key' => $themeKey,
                'placement' => $record['placement'],
                'title' => $record['title'],
                'subtitle' => $record['subtitle'],
                'image_url' => $record['image_url'],
                'link_url' => $record['link_url'],
                'badge' => $record['badge'],
                'metadata' => $record['metadata'],
                'sort_order' => $record['sort_order'],
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        return count($records);
    }

    private function buildProductName(array $preset, string $departmentName, string $childName, int $productIndex): string
    {
        $suffixes = ['Pro', 'Max', 'Plus', 'Edition'];

        return trim($childName.' '.$preset['product_prefix'].' '.$suffixes[$productIndex - 1].' '.(64 + ($productIndex * 64)).'GB');
    }

    private function buildPrice(int $parentIndex, int $childIndex, int $productIndex): int
    {
        return 390000 + ($parentIndex * 170000) + ($childIndex * 80000) + ($productIndex * 45000);
    }

    private function buildGalleryImages(array $preset, int $parentIndex, int $childIndex, int $productIndex): array
    {
        return [
            $this->imageUrl($preset['key'].'-product-gallery-'.$parentIndex.'-'.$childIndex.'-'.$productIndex.'-1', 960, 720),
            $this->imageUrl($preset['key'].'-product-gallery-'.$parentIndex.'-'.$childIndex.'-'.$productIndex.'-2', 960, 720),
            $this->imageUrl($preset['key'].'-product-gallery-'.$parentIndex.'-'.$childIndex.'-'.$productIndex.'-3', 960, 720),
            $this->imageUrl($preset['key'].'-product-gallery-'.$parentIndex.'-'.$childIndex.'-'.$productIndex.'-4', 960, 720),
        ];
    }

    private function buildProductHighlights(array $preset, string $departmentName, string $childName): string
    {
        return implode(PHP_EOL, [
            'Ưu đãi nổi bật cho nhóm '.$childName.' thuộc ngành '.$departmentName.'.',
            'Phù hợp để test bố cục deal nhiều khối như banner, card và trang detail.',
            'Có thể dùng ngay để review gallery nhiều ảnh và nội dung dài của theme '.$preset['company_name'].'.',
        ]);
    }

    private function buildUsageTerms(array $preset, string $departmentName): string
    {
        return implode(PHP_EOL, [
            'Thời hạn ưu đãi linh hoạt theo chiến dịch của '.$preset['company_name'].'.',
            'Khuyến nghị liên hệ trước để xác nhận tình trạng áp dụng cho nhóm '.$departmentName.'.',
            'Vui lòng cung cấp mã SKU khi cần CSKH xử lý đơn hoặc hậu mãi nhanh.',
            'Không áp dụng đồng thời với các chương trình giảm giá nội bộ khác nếu không có ghi chú riêng.',
        ]);
    }

    private function buildUsageLocation(array $preset): string
    {
        return implode(PHP_EOL, [
            $preset['company_name'],
            $preset['address'],
            'Hotline: 1900 6760',
            'Email: hello@'.$preset['domain'],
        ]);
    }

    private function buildProductDetailContent(array $preset, string $departmentName, string $childName, string $productName): string
    {
        return implode(PHP_EOL.PHP_EOL, [
            $productName.' là dữ liệu demo được sinh cho preset '.$preset['label'].', giúp kiểm thử đầy đủ luồng hiển thị trang chi tiết sản phẩm theo phong cách deal page.',
            'Sản phẩm thuộc nhóm '.$childName.' trong ngành '.$departmentName.', vì vậy phần nội dung dài được thiết kế để hiển thị đẹp ở các block mô tả, điều kiện sử dụng và vị trí áp dụng trên theme TH0001.',
            'Sếp có thể sửa trực tiếp phần mô tả này, gallery ảnh, số lượng đã mua và thời gian kết thúc deal trong admin Catalog để biến trang từ demo thành nội dung vận hành thật.',
        ]);
    }

    private function imageUrl(string $seed, int $width, int $height): string
    {
        return sprintf('https://picsum.photos/seed/%s/%d/%d', $seed, $width, $height);
    }

    private function presetDefinitions(): array
    {
        return [
            [
                'key' => 'electronics-superstore',
                'label' => 'Điện máy công nghệ',
                'short_label' => 'điện máy',
                'description' => 'Preset tập trung vào điện thoại, laptop, thiết bị gia dụng và phụ kiện công nghệ để test theme ecommerce nhiều block.',
                'company_name' => 'AIO Tech Market',
                'domain' => 'aiotechmarket.demo',
                'address' => '332 Lũy Bán Bích, Tân Phú, TP.HCM',
                'theme_flavor' => 'deal điện máy tốc độ cao',
                'hero_eyebrow' => 'Flash sale công nghệ',
                'hero_title' => 'Deal sốc cho điện thoại, laptop và điện gia dụng',
                'hero_subtitle' => 'Giá tốt mỗi ngày, giao nhanh toàn quốc cho khách hàng công nghệ.',
                'hero_badge' => 'Chỉ từ 399K',
                'product_prefix' => 'Flex',
                'departments' => [
                    ['name' => 'Điện thoại', 'children' => ['Android', 'iPhone', 'Gaming Phone']],
                    ['name' => 'Laptop', 'children' => ['Văn phòng', 'Gaming', 'Ultrabook']],
                    ['name' => 'Máy tính bảng', 'children' => ['Tablet Android', 'iPad', 'Tablet học tập']],
                    ['name' => 'Phụ kiện', 'children' => ['Tai nghe', 'Sạc dự phòng', 'Cáp sạc']],
                    ['name' => 'Âm thanh', 'children' => ['Loa Bluetooth', 'Micro', 'Soundbar']],
                    ['name' => 'TV & Giải trí', 'children' => ['Smart TV', 'Máy chiếu', 'Android Box']],
                    ['name' => 'Điện lạnh', 'children' => ['Điều hòa', 'Máy lọc không khí', 'Quạt thông minh']],
                    ['name' => 'Gia dụng', 'children' => ['Nồi chiên', 'Máy hút bụi', 'Máy pha cà phê']],
                    ['name' => 'Camera', 'children' => ['Camera wifi', 'Camera hành trình', 'Camera an ninh']],
                    ['name' => 'Thiết bị mạng', 'children' => ['Router', 'Mesh wifi', 'Bộ kích sóng']],
                ],
            ],
            [
                'key' => 'phones-accessories',
                'label' => 'Điện thoại và phụ kiện',
                'short_label' => 'điện thoại',
                'description' => 'Preset cho showroom điện thoại, phụ kiện và dịch vụ bảo hành mở rộng.',
                'company_name' => 'Mobile Hub',
                'domain' => 'mobilehub.demo',
                'address' => '85 Nguyễn Thị Minh Khai, Quận 1, TP.HCM',
                'theme_flavor' => 'showroom mobile',
                'hero_eyebrow' => 'Mobile deal',
                'hero_title' => 'Lên đời smartphone và phụ kiện chính hãng',
                'hero_subtitle' => 'Combo máy mới, trả góp linh hoạt và hậu mãi tại cửa hàng.',
                'hero_badge' => 'Giảm đến 35%',
                'product_prefix' => 'Nova',
                'departments' => [
                    ['name' => 'Smartphone', 'children' => ['Flagship', 'Tầm trung', 'Giá tốt']],
                    ['name' => 'Ốp lưng', 'children' => ['Chống sốc', 'Trong suốt', 'Da cao cấp']],
                    ['name' => 'Tai nghe', 'children' => ['True Wireless', 'Over-ear', 'Gaming Earbuds']],
                    ['name' => 'Sạc', 'children' => ['Sạc nhanh', 'Sạc không dây', 'Củ cáp combo']],
                    ['name' => 'Đồng hồ', 'children' => ['Smartwatch', 'Vòng tay', 'Đồng hồ trẻ em']],
                    ['name' => 'Loa mini', 'children' => ['Bluetooth', 'Loa karaoke', 'Loa du lịch']],
                    ['name' => 'Thiết bị ghi hình', 'children' => ['Gimbal', 'Selfie stick', 'Đèn livestream']],
                    ['name' => 'Bảo hành', 'children' => ['Gói rơi vỡ', 'Bảo hành pin', 'Thu cũ đổi mới']],
                    ['name' => 'Máy cũ', 'children' => ['Like new', 'Refurbished', 'Outlet']],
                    ['name' => 'Phụ kiện xe', 'children' => ['Giá đỡ', 'Sạc ô tô', 'Bluetooth car']],
                ],
            ],
            [
                'key' => 'computer-workstation',
                'label' => 'Máy tính và workstation',
                'short_label' => 'máy tính',
                'description' => 'Preset cho doanh nghiệp bán máy tính, workstation và thiết bị văn phòng.',
                'company_name' => 'Compute Center',
                'domain' => 'computecenter.demo',
                'address' => '12 Duy Tân, Cầu Giấy, Hà Nội',
                'theme_flavor' => 'workstation store',
                'hero_eyebrow' => 'Work smarter',
                'hero_title' => 'Thiết bị máy tính tối ưu cho văn phòng và sáng tạo',
                'hero_subtitle' => 'Danh mục dựng sẵn để test block chuyên ngành máy tính.',
                'hero_badge' => 'Quà tặng doanh nghiệp',
                'product_prefix' => 'Core',
                'departments' => [
                    ['name' => 'Laptop doanh nghiệp', 'children' => ['14 inch', '15 inch', '2-in-1']],
                    ['name' => 'PC đồng bộ', 'children' => ['Mini PC', 'Office PC', 'All in one']],
                    ['name' => 'Workstation', 'children' => ['3D Render', 'AI Training', 'Video Editing']],
                    ['name' => 'Màn hình', 'children' => ['2K', '4K', 'Ultrawide']],
                    ['name' => 'Thiết bị nhập', 'children' => ['Bàn phím', 'Chuột', 'Webcam']],
                    ['name' => 'Lưu trữ', 'children' => ['SSD', 'NAS', 'Ổ cứng di động']],
                    ['name' => 'Mạng văn phòng', 'children' => ['Switch', 'Router', 'Firewall']],
                    ['name' => 'Máy in', 'children' => ['Laser', 'Màu', 'In tem']],
                    ['name' => 'Phòng họp', 'children' => ['Camera hội nghị', 'Loa hội nghị', 'Bảng tương tác']],
                    ['name' => 'Server mini', 'children' => ['Tower', 'Rack 1U', 'Backup']],
                ],
            ],
            [
                'key' => 'travel-deals',
                'label' => 'Du lịch và trải nghiệm',
                'short_label' => 'du lịch',
                'description' => 'Preset cho website tour, combo nghỉ dưỡng và vé trải nghiệm cuối tuần.',
                'company_name' => 'Travel Burst',
                'domain' => 'travelburst.demo',
                'address' => '25 Trần Hưng Đạo, Hoàn Kiếm, Hà Nội',
                'theme_flavor' => 'travel flash deal',
                'hero_eyebrow' => 'Combo cuối tuần',
                'hero_title' => 'Săn tour, vé vui chơi và combo nghỉ dưỡng giá tốt',
                'hero_subtitle' => 'Layout phù hợp website deal du lịch nhiều banner và nhiều ngành hàng.',
                'hero_badge' => '2N1Đ từ 1.99M',
                'product_prefix' => 'Trip',
                'departments' => [
                    ['name' => 'Tour miền Bắc', 'children' => ['Sa Pa', 'Hạ Long', 'Hà Giang']],
                    ['name' => 'Tour miền Trung', 'children' => ['Đà Nẵng', 'Huế', 'Quy Nhơn']],
                    ['name' => 'Tour miền Nam', 'children' => ['Phú Quốc', 'Vũng Tàu', 'Cần Thơ']],
                    ['name' => 'Vé vui chơi', 'children' => ['Công viên', 'Thủy cung', 'Show diễn']],
                    ['name' => 'Resort', 'children' => ['Biển', 'Núi', 'City stay']],
                    ['name' => 'Du thuyền', 'children' => ['Hạ Long', 'Sài Gòn', 'Sông Hàn']],
                    ['name' => 'Spa du lịch', 'children' => ['Massage', 'Detox', 'Wellness']],
                    ['name' => 'Ẩm thực địa phương', 'children' => ['Buffet', 'Hải sản', 'Cafe view đẹp']],
                    ['name' => 'Visa & dịch vụ', 'children' => ['Hàn Quốc', 'Nhật Bản', 'Schengen']],
                    ['name' => 'Team building', 'children' => ['1 ngày', '2 ngày', 'MICE']],
                ],
            ],
            [
                'key' => 'cosmetics-beauty',
                'label' => 'Mỹ phẩm và làm đẹp',
                'short_label' => 'mỹ phẩm',
                'description' => 'Preset cho ecommerce mỹ phẩm, skincare, spa package và thiết bị beauty.',
                'company_name' => 'Beauty Bloom',
                'domain' => 'beautybloom.demo',
                'address' => '89 Võ Văn Tần, Quận 3, TP.HCM',
                'theme_flavor' => 'beauty commerce',
                'hero_eyebrow' => 'Glow every day',
                'hero_title' => 'Skincare, makeup và combo spa cho khách hàng nữ',
                'hero_subtitle' => 'Tổ chức block danh mục, sản phẩm nổi bật và tin tư vấn làm đẹp.',
                'hero_badge' => 'Voucher từ 89K',
                'product_prefix' => 'Glow',
                'departments' => [
                    ['name' => 'Chăm sóc da', 'children' => ['Làm sạch', 'Dưỡng ẩm', 'Chống nắng']],
                    ['name' => 'Trang điểm', 'children' => ['Nền', 'Môi', 'Mắt']],
                    ['name' => 'Nước hoa', 'children' => ['Nữ', 'Nam', 'Unisex']],
                    ['name' => 'Thiết bị beauty', 'children' => ['Máy rửa mặt', 'Máy nâng cơ', 'Máy xông']],
                    ['name' => 'Chăm sóc tóc', 'children' => ['Dầu gội', 'Ủ tóc', 'Tinh dầu']],
                    ['name' => 'Body care', 'children' => ['Sữa tắm', 'Dưỡng thể', 'Tẩy da chết']],
                    ['name' => 'Spa tại nhà', 'children' => ['Mask', 'Detox', 'Massage']],
                    ['name' => 'Dành cho nam', 'children' => ['Skincare nam', 'Wax tóc', 'Sữa rửa mặt']],
                    ['name' => 'Quà tặng', 'children' => ['Gift set', 'Mini size', 'Best seller']],
                    ['name' => 'Thực phẩm đẹp da', 'children' => ['Collagen', 'Vitamin', 'Detox']],
                ],
            ],
            [
                'key' => 'industrial-chemicals',
                'label' => 'Hóa chất và vật tư công nghiệp',
                'short_label' => 'hóa chất',
                'description' => 'Preset cho doanh nghiệp phân phối hóa chất, dung môi, vật tư phòng lab.',
                'company_name' => 'Lab Supply Pro',
                'domain' => 'labsupply.demo',
                'address' => 'Lô C2 KCN Tân Bình, TP.HCM',
                'theme_flavor' => 'industrial catalog',
                'hero_eyebrow' => 'Nguồn hàng công nghiệp',
                'hero_title' => 'Hóa chất, vật tư lab và thiết bị sản xuất theo ngành',
                'hero_subtitle' => 'Dùng để test theme B2B với nhiều danh mục kỹ thuật.',
                'hero_badge' => 'Báo giá trong 2h',
                'product_prefix' => 'Lab',
                'departments' => [
                    ['name' => 'Dung môi', 'children' => ['Ethanol', 'Acetone', 'IPA']],
                    ['name' => 'Hóa chất xử lý nước', 'children' => ['PAC', 'Polymer', 'Chlorine']],
                    ['name' => 'Phòng thí nghiệm', 'children' => ['Becher', 'Pipet', 'Tủ hút']],
                    ['name' => 'An toàn lao động', 'children' => ['Găng tay', 'Kính bảo hộ', 'Mặt nạ']],
                    ['name' => 'Thiết bị đo', 'children' => ['pH meter', 'Conductivity', 'Cân điện tử']],
                    ['name' => 'Hóa mỹ phẩm nền', 'children' => ['Glycerin', 'Surfactant', 'Tinh dầu']],
                    ['name' => 'Bao bì hóa chất', 'children' => ['Can nhựa', 'Phuy', 'IBC']],
                    ['name' => 'Hóa chất thực phẩm', 'children' => ['Phụ gia', 'Chất bảo quản', 'Màu thực phẩm']],
                    ['name' => 'Xi mạ', 'children' => ['Muối', 'Phụ gia bể', 'Thiết bị lọc']],
                    ['name' => 'Tư vấn kỹ thuật', 'children' => ['SDS', 'MSDS', 'Quy trình']],
                ],
            ],
            [
                'key' => 'construction-materials',
                'label' => 'Xây dựng và nội thất',
                'short_label' => 'xây dựng',
                'description' => 'Preset cho công ty vật liệu xây dựng, nội thất hoàn thiện và phụ kiện công trình.',
                'company_name' => 'Build Mart',
                'domain' => 'buildmart.demo',
                'address' => 'QL1A, Thủ Đức, TP.HCM',
                'theme_flavor' => 'building materials hub',
                'hero_eyebrow' => 'Nguồn hàng công trình',
                'hero_title' => 'Vật liệu hoàn thiện, nội thất và thiết bị công trình',
                'hero_subtitle' => 'Danh mục lớn để test nhiều block sản phẩm theo ngành.',
                'hero_badge' => 'Chiết khấu đại lý',
                'product_prefix' => 'Stone',
                'departments' => [
                    ['name' => 'Gạch ốp lát', 'children' => ['Phòng khách', 'Nhà tắm', 'Ngoại thất']],
                    ['name' => 'Thiết bị vệ sinh', 'children' => ['Bồn cầu', 'Lavabo', 'Sen vòi']],
                    ['name' => 'Sơn nước', 'children' => ['Nội thất', 'Ngoại thất', 'Chống thấm']],
                    ['name' => 'Nội thất bếp', 'children' => ['Chậu rửa', 'Máy hút mùi', 'Bếp từ']],
                    ['name' => 'Cửa & khóa', 'children' => ['Khóa điện tử', 'Cửa nhôm', 'Phụ kiện']],
                    ['name' => 'Vật liệu thô', 'children' => ['Xi măng', 'Thép', 'Cát đá']],
                    ['name' => 'Đèn trang trí', 'children' => ['Đèn thả', 'Đèn âm trần', 'Đèn tường']],
                    ['name' => 'Sàn', 'children' => ['Gỗ công nghiệp', 'Nhựa SPC', 'Thảm']],
                    ['name' => 'Ngoại thất', 'children' => ['Giàn phơi', 'Mái hiên', 'Lan can']],
                    ['name' => 'Dụng cụ thi công', 'children' => ['Khoan', 'Cắt gạch', 'Máy mài']],
                ],
            ],
            [
                'key' => 'tech-accessories',
                'label' => 'Phụ kiện công nghệ',
                'short_label' => 'phụ kiện',
                'description' => 'Preset gọn cho cửa hàng phụ kiện công nghệ, gaming gear và đồ thông minh.',
                'company_name' => 'Accessory Station',
                'domain' => 'accessorystation.demo',
                'address' => '248 Cầu Giấy, Hà Nội',
                'theme_flavor' => 'accessory flash sale',
                'hero_eyebrow' => 'Gear & gadget',
                'hero_title' => 'Tai nghe, gear, sạc nhanh và đồ smart-home bán chạy',
                'hero_subtitle' => 'Preset phù hợp cho layout hot deal, sản phẩm nhỏ nhưng danh mục dày.',
                'hero_badge' => 'Combo từ 129K',
                'product_prefix' => 'Pulse',
                'departments' => [
                    ['name' => 'Gaming Gear', 'children' => ['Chuột', 'Bàn phím', 'Lót chuột']],
                    ['name' => 'Sạc nhanh', 'children' => ['GaN', 'MagSafe', 'Củ cáp']],
                    ['name' => 'Âm thanh cá nhân', 'children' => ['Earbuds', 'Headphones', 'DAC']],
                    ['name' => 'Camera mini', 'children' => ['Webcam', 'Action cam', 'Livestream']],
                    ['name' => 'Nhà thông minh', 'children' => ['Ổ cắm', 'Đèn thông minh', 'Cảm biến']],
                    ['name' => 'Phụ kiện laptop', 'children' => ['Hub USB-C', 'Giá đỡ', 'Quạt tản']],
                    ['name' => 'Balo & túi', 'children' => ['Balo', 'Túi sleeve', 'Chống sốc']],
                    ['name' => 'Phụ kiện xe hơi', 'children' => ['Sạc xe', 'Cam hành trình', 'Giá đỡ']],
                    ['name' => 'Thiết bị văn phòng', 'children' => ['Bút trình chiếu', 'Docking', 'Ổ cắm kéo dài']],
                    ['name' => 'Quà công nghệ', 'children' => ['Gift set', 'Mini gadget', 'Best seller']],
                ],
            ],
        ];
    }
}
