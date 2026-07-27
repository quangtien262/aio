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

class Ec906DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC906';
    private const PRESET_KEY = 'ec906-ega-minimart';

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
            'label' => 'EC906 EGA Mini Mart',
            'description' => 'Siêu thị gia đình với flash sale, chăm sóc nhà cửa, mẹ và bé, đồ dùng nhà bếp và trang chủ cấu hình bằng Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC906.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Chăm sóc gia đình', 'Sản phẩm giặt xả và làm sạch không gian sống.', 'home-care.png'],
                ['Đồ dùng nhà bếp', 'Dụng cụ nấu ăn và làm bánh tiện dụng.', 'kitchen-tools.png'],
                ['Mẹ và bé', 'Tã bỉm, bình sữa và vật dụng chăm sóc bé.', 'baby-care.png'],
                ['Sữa và dinh dưỡng', 'Nguồn dinh dưỡng chọn lọc cho cả gia đình.', 'nutrition.png'],
                ['Vệ sinh nhà cửa', 'Giải pháp lau dọn nhanh và an toàn.', 'home-care.png'],
                ['Chăm sóc quần áo', 'Giặt sạch và lưu hương dài lâu.', 'home-care.png'],
                ['Dụng cụ làm bánh', 'Khuôn, cán bột và phụ kiện làm bánh.', 'kitchen-tools.png'],
                ['Đồ dùng tiện ích', 'Vật dụng nhỏ giúp việc nhà nhẹ nhàng hơn.', 'kitchen-tools.png'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec906-'.$name),
                    'description' => $description,
                    'image_url' => '/theme-demo/ec906/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['HOME', 'Nước lau sàn tinh dầu thảo mộc', 30000, 45000, 0, 'home-care.png'],
                ['HOME', 'Nước rửa chén hương quế và bồ hòn', 121000, 129000, 0, 'home-care.png'],
                ['HOME', 'Nước xả vải hương hoa oải hương', 167000, 182000, 5, 'home-care.png'],
                ['HOME', 'Túi nước xả vải thanh khiết 1.7L', 140000, 175000, 5, 'home-care.png'],
                ['HOME', 'Nước giặt thiên nhiên dịu nhẹ', 159000, 270000, 5, 'home-care.png'],
                ['HOME', 'Sáp thơm phòng khử mùi Pure Aroma', 41000, 65000, 4, 'home-care.png'],
                ['HOME', 'Tẩy cặn vòi sen đa năng', 50000, 79000, 4, 'home-care.png'],
                ['HOME', 'Bộ làm sạch nhà cửa tiện lợi', 89000, 129000, 4, 'home-care.png'],
                ['KITCHEN', 'Chảo chiên trứng tạo hình', 20000, 30000, 1, 'kitchen-tools.png'],
                ['KITCHEN', 'Khuôn silicone làm bánh donut', 35000, 50000, 6, 'kitchen-tools.png'],
                ['KITCHEN', 'Cây lăn bột chống dính', 75000, 149000, 6, 'kitchen-tools.png'],
                ['KITCHEN', 'Cán bột gỗ tay cầm dài', 35000, 40000, 6, 'kitchen-tools.png'],
                ['KITCHEN', 'Khay làm kem silicone dễ thương', 36000, 70000, 1, 'kitchen-tools.png'],
                ['KITCHEN', 'Dụng cụ ép bột tiện dụng', 69000, 99000, 6, 'kitchen-tools.png'],
                ['KITCHEN', 'Khuôn làm bánh xếp 2 trong 1', 33000, 77000, 6, 'kitchen-tools.png'],
                ['KITCHEN', 'Máy đánh trứng cầm tay mini', 52000, 80000, 1, 'kitchen-tools.png'],
                ['BABY', 'Bộ chăm sóc bé sơ sinh', 269000, 329000, 2, 'baby-care.png'],
                ['BABY', 'Tã bỉm mềm mại thấm hút tốt', 319000, 399000, 2, 'baby-care.png'],
                ['NUTRITION', 'Sữa dinh dưỡng cho cả gia đình', 420000, 475000, 3, 'nutrition.png'],
                ['NUTRITION', 'Thức uống yến mạch nguyên chất', 89000, 105000, 3, 'nutrition.png'],
            ];
            foreach ($productDefinitions as $index => [$group, $name, $price, $originalPrice, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec906-'.$name),
                    'sku' => 'EC906-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 80,
                    'short_description' => 'Sản phẩm chọn lọc cho gia đình, giao nhanh và hỗ trợ đổi trả minh bạch.',
                    'detail_content' => '<p>EGA Mini Mart chọn sản phẩm dựa trên tiêu chí tiện dụng, an toàn và giá hợp lý. Thông tin giao nhận và đổi trả được công bố rõ ràng.</p>',
                    'image_url' => '/theme-demo/ec906/'.$image,
                    'is_featured' => $index < 8,
                    'is_highlight' => $index < 16,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Đại tiệc khuyến mãi', 'Giảm giá lên đến 65%', 'hero-minimart.png'],
                ['Tuần lễ gia đình xanh', 'Sản phẩm thiết yếu cho tổ ấm với giá thật vui.', 'hero-minimart.png'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec906-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec906/'.$image,
                    'link_url' => '#flash-sale',
                    'badge' => 'DUY NHẤT TẠI EGA MINI MART',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Mua ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang gia đình EGA',
                'slug' => 'ec906-cam-nang-gia-dinh',
                'description' => 'Mẹo chăm sóc sức khỏe và không gian sống.',
            ]);
            $this->record($postCategory);
            $postDefinitions = [
                ['Các loại nước chống lão hóa hiệu quả nên uống mỗi ngày', 'Những thức uống giàu dưỡng chất giúp cơ thể khỏe mạnh và tươi trẻ.', 'nutrition.png'],
                ['Trái cây mùa đông giúp giảm cân hiệu quả', 'Lựa chọn thực phẩm theo mùa để cân bằng dinh dưỡng cho gia đình.', 'nutrition.png'],
                ['Cách chọn rau củ quả sạch, tươi ngon và an toàn', 'Những dấu hiệu đơn giản giúp bạn chọn thực phẩm chất lượng.', 'home-care.png'],
                ['10 mẹo giúp người bận rộn giữ nhà luôn sạch sẽ', 'Các thói quen nhỏ giúp không gian sống luôn thoáng sạch.', 'home-care.png'],
            ];
            foreach ($postDefinitions as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec906/'.$image,
                    'mime_type' => 'image/png',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec906-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>EGA Mini Mart tổng hợp những kinh nghiệm hữu ích để việc chăm sóc gia đình trở nên nhẹ nhàng hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'EC906 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Giới thiệu', 'url' => $home.'#gioi-thieu'],
                    ['label' => 'Khuyến mãi', 'url' => $home.'#flash-sale'],
                    ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'],
                    ['label' => 'Kiểm tra đơn hàng', 'url' => route('site.catalog.search')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                    ['label' => 'Hướng dẫn thiết lập', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contactPage = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ EGA Mini Mart', 'status' => 'published', 'excerpt' => 'Tư vấn sản phẩm và hỗ trợ đơn hàng.', 'body' => '<p>Đội ngũ EGA Mini Mart luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()],
            );
            if ($contactPage->wasRecentlyCreated) $this->record($contactPage);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'EGA Mini Mart',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'EGA Mini Mart',
                    'company_description' => 'Siêu thị gia đình hiện đại với sản phẩm chọn lọc, giá tốt và giao hàng nhanh.',
                    'support_hotline' => '1900 6750',
                    'support_email' => 'support@egamart.vn',
                    'support_location' => '70 Lữ Gia, Quận 11, TP. Hồ Chí Minh',
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'categories' => count($categoryDefinitions),
                    'products' => count($productDefinitions),
                    'banners' => 2,
                    'post_categories' => 1,
                    'posts' => count($postDefinitions),
                    'media' => count($postDefinitions),
                    'pages' => $contactPage->wasRecentlyCreated ? 1 : 0,
                    'menus' => 1,
                    'landing_pages' => ! $existing && $landing ? 1 : 0,
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
            [CmsPost::class, 'posts'],
            [CmsMedia::class, 'media'],
            [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'],
            [CatalogProduct::class, 'products'],
            [CatalogCategory::class, 'categories'],
            [CmsMenu::class, 'menus'],
            [SiteBanner::class, 'banners'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        }
        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete();

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
