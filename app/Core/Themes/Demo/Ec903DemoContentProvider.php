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

class Ec903DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC903';

    private const PRESET_KEY = 'ec903-dealvui';

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
            'label' => 'EC903 DealVui Marketplace',
            'description' => 'Sàn e-voucher đa ngành cho ẩm thực, làm đẹp, giải trí và du lịch, cấu hình bằng Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC903.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Khuyến mãi hot', 'Deal nổi bật được cập nhật mỗi ngày.', '/theme-demo/ec903/deal-seafood.webp'],
                ['Ẩm thực', 'Buffet, nhà hàng và trải nghiệm ẩm thực chọn lọc.', '/theme-demo/ec903/food-lobster.webp'],
                ['Spa & Làm đẹp', 'Dịch vụ thư giãn và chăm sóc sắc đẹp uy tín.', '/theme-demo/ec903/deal-spa.webp'],
                ['Giải trí & Thể thao', 'Vé vui chơi và hoạt động giải trí cho mọi lứa tuổi.', '/theme-demo/ec903/deal-amusement.webp'],
                ['Massage Nam Nữ', 'Liệu trình thư giãn và phục hồi toàn thân.', '/theme-demo/ec903/deal-massage.webp'],
                ['Đào tạo & Hội thảo', 'Khóa học và sự kiện nâng cao kỹ năng.', '/theme-demo/ec903/deal-tea.webp'],
                ['Bệnh viện & Phòng khám', 'Gói khám và chăm sóc sức khỏe chủ động.', '/theme-demo/ec903/deal-skincare.webp'],
                ['Buffet', 'Bàn tiệc phong phú tại khách sạn và nhà hàng.', '/theme-demo/ec903/food-banquet.webp'],
                ['Nha khoa', 'Chăm sóc nụ cười với quy trình chuyên nghiệp.', '/theme-demo/ec903/deal-dental.webp'],
                ['Tour du lịch', 'Kỳ nghỉ và hành trình khám phá đáng nhớ.', '/theme-demo/ec903/deal-tour.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec903-'.$name),
                    'description' => $description,
                    'image_url' => $image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $groups = [
                'HOT' => [
                    ['Buffet hải sản tôm hùm cao cấp', 1050000, 1500000, 1, 'deal-seafood.webp'],
                    ['Spa thư giãn đá nóng 90 phút', 299000, 650000, 2, 'deal-spa.webp'],
                    ['Tour sông nước miền Tây trong ngày', 699000, 990000, 9, 'deal-tour.webp'],
                    ['Vui chơi công viên trọn gói', 255000, 300000, 3, 'deal-amusement.webp'],
                    ['Chăm sóc da chuyên sâu Vitamin C', 149000, 900000, 2, 'deal-skincare.webp'],
                    ['Kỳ nghỉ resort hồ bơi nhiệt đới', 1699000, 2500000, 9, 'deal-resort.webp'],
                    ['Phòng khách sạn thành phố hạng sang', 1399000, 1990000, 9, 'deal-hotel.webp'],
                    ['Massage phục hồi toàn thân', 199000, 550000, 4, 'deal-massage.webp'],
                ],
                'FOOD' => [
                    ['Buffet tôm hùm và hải sản không giới hạn', 1050000, 1502000, 1, 'food-lobster.webp'],
                    ['Buffet dim sum chuẩn vị Á Đông', 519000, 646000, 1, 'food-dimsum.webp'],
                    ['Buffet lẩu hai vị hơn 60 món', 349000, 459000, 7, 'food-hotpot.webp'],
                    ['Tiệc nướng hải sản cao cấp', 635000, 793000, 7, 'food-grill.webp'],
                    ['Bàn tiệc Việt cho gia đình', 770000, 906000, 7, 'food-banquet.webp'],
                    ['Bữa tối du thuyền ngắm thành phố', 1150000, 1450000, 1, 'food-cruise.webp'],
                    ['Buffet brunch cuối tuần', 599000, 750000, 7, 'food-brunch.webp'],
                    ['Buffet bánh ngọt và trà chiều', 379000, 419000, 1, 'food-dessert.webp'],
                ],
                'VEGAN' => [
                    ['Buffet chay Việt thanh lành', 178000, 209000, 1, 'vegan-buffet.webp'],
                    ['Lẩu nấm rau xanh thanh vị', 189000, 219000, 1, 'vegan-hotpot.webp'],
                    ['Thực đơn chay phong cách hiện đại', 99000, 168000, 1, 'vegan-finedining.webp'],
                    ['Brunch chay và trà thảo mộc', 159000, 208000, 1, 'vegan-brunch.webp'],
                ],
                'BEAUTY' => [
                    ['Liệu trình spa đá nóng thư giãn', 299000, 650000, 2, 'deal-spa.webp'],
                    ['Chăm sóc da sáng khỏe chuyên sâu', 149000, 900000, 2, 'deal-skincare.webp'],
                    ['Massage dưỡng sinh toàn thân', 199000, 550000, 4, 'deal-massage.webp'],
                    ['Gói chăm sóc nụ cười chuyên nghiệp', 399000, 750000, 8, 'deal-dental.webp'],
                ],
                'TRAVEL' => [
                    ['Tour khám phá sông nước nhiệt đới', 699000, 990000, 9, 'deal-tour.webp'],
                    ['Vé công viên vui chơi cả ngày', 255000, 300000, 3, 'deal-amusement.webp'],
                    ['Nghỉ dưỡng resort bên hồ bơi', 1699000, 2500000, 9, 'deal-resort.webp'],
                    ['Khách sạn trung tâm thành phố', 1399000, 1990000, 9, 'deal-hotel.webp'],
                ],
            ];
            $productCount = 0;
            $sortOrder = 0;

            foreach ($groups as $group => $definitions) {
                foreach ($definitions as $index => [$name, $price, $originalPrice, $categoryIndex, $image]) {
                    $product = CatalogProduct::query()->create([
                        'catalog_category_id' => $categories[$categoryIndex]->id,
                        'name' => $name,
                        'slug' => Str::slug('ec903-'.$group.'-'.$name),
                        'sku' => 'EC903-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                        'price' => $price,
                        'original_price' => $originalPrice,
                        'stock' => 120,
                        'short_description' => 'E-voucher xác nhận nhanh, sử dụng linh hoạt và có đội ngũ hỗ trợ đặt chỗ.',
                        'detail_content' => '<p>Voucher điện tử được gửi sau khi thanh toán thành công. Vui lòng đặt lịch trước và xuất trình mã xác nhận khi sử dụng dịch vụ.</p>',
                        'image_url' => '/theme-demo/ec903/'.$image,
                        'is_featured' => in_array($group, ['HOT', 'FOOD'], true),
                        'is_highlight' => $group === 'HOT',
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                    ]);
                    $this->record($product);
                    $productCount++;
                }
            }

            foreach ([
                ['Tận hưởng dịch vụ đỉnh cao', 'Deal spa, ẩm thực và nghỉ dưỡng được tuyển chọn với giá đặc biệt.', 'hero-marketplace.webp', '#deal-noi-bat'],
                ['Đại tiệc cuối tuần', 'Khám phá buffet và nhà hàng được yêu thích.', 'promo-dining.webp', '#am-thuc'],
            ] as $index => [$title, $summary, $image, $url]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec903-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec903/'.$image,
                    'link_url' => $url,
                    'badge' => 'E-VOUCHER ĐỘC QUYỀN',
                    'metadata' => ['eyebrow' => 'DEAL ĐỘC QUYỀN', 'summary' => $summary, 'button_label' => 'Đặt ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang DealVui',
                'slug' => 'ec903-cam-nang-dealvui',
                'description' => 'Kinh nghiệm chọn voucher, điểm đến và dịch vụ đáng thử.',
            ]);
            $this->record($postCategory);
            $postDefinitions = [
                ['Bí quyết chọn buffet phù hợp cho buổi họp mặt', 'Chọn không gian, thực đơn và thời gian dùng bữa để cả nhóm có trải nghiệm trọn vẹn.', 'food-banquet.webp'],
                ['Một ngày thư giãn tại spa cần chuẩn bị gì?', 'Những lưu ý đơn giản giúp bạn tận hưởng liệu trình chăm sóc cơ thể hiệu quả hơn.', 'deal-spa.webp'],
                ['Gợi ý chuyến đi cuối tuần gần thành phố', 'Lịch trình ngắn ngày cân bằng giữa khám phá, nghỉ ngơi và thưởng thức ẩm thực.', 'deal-tour.webp'],
                ['Cách sử dụng e-voucher nhanh và an toàn', 'Kiểm tra điều kiện áp dụng, đặt lịch trước và lưu mã xác nhận để giao dịch thuận tiện.', 'hero-marketplace.webp'],
            ];

            foreach ($postDefinitions as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec903/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec903-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>DealVui tổng hợp những thông tin thực tế để bạn chọn và sử dụng dịch vụ thuận tiện hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'EC903 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Deal mới', 'url' => $home.'#deal-noi-bat'],
                    ['label' => 'Deal bán chạy', 'url' => $home.'#deal-noi-bat'],
                    ['label' => 'Ẩm thực', 'url' => $home.'#am-thuc'],
                    ['label' => 'Spa & Làm đẹp', 'url' => $home.'#lam-dep'],
                    ['label' => 'Du lịch', 'url' => $home.'#du-lich'],
                    ['label' => 'Cẩm nang', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ DealVui',
                    'status' => 'published',
                    'excerpt' => 'Hỗ trợ đặt voucher và giải đáp thông tin dịch vụ.',
                    'body' => '<p>Đội ngũ DealVui hỗ trợ tư vấn lựa chọn, thanh toán và sử dụng e-voucher mỗi ngày.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'DealVui E‑Voucher',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'DEALVUI',
                    'company_description' => 'Sàn e-voucher ẩm thực, làm đẹp, giải trí và du lịch với mức giá tốt mỗi ngày.',
                    'support_hotline' => '1900 6760',
                    'support_email' => 'cs@dealvui.vn',
                    'support_location' => 'Tầng 2, 04 Trịnh Đình Thảo, Phường Tân Phú, TP.HCM',
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
                    'products' => $productCount,
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
