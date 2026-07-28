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

class Ec916DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC916';
    private const PRESET_KEY = 'ec916-bach-hoa-xanh-plus';

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
            'label' => 'EC916 Bách Hóa Xanh Plus',
            'description' => 'Cửa hàng bách hóa đa ngành với ưu đãi thực phẩm, công nghệ, thời trang, làm đẹp và gia dụng.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC916.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Thực phẩm', 'product-grocery.webp'],
                ['Mobile & Tablet', 'product-phone.webp'],
                ['Thời trang', 'product-dress.webp'],
                ['Sức khỏe & Làm đẹp', 'product-skincare.webp'],
                ['Gia dụng', 'product-blender.webp'],
                ['Nhà cửa', 'product-sofa.webp'],
                ['Mẹ & Bé', 'product-baby.webp'],
                ['Phụ kiện số', 'product-headphones.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec916-'.$name),
                    'description' => 'Sản phẩm chính hãng, giá tốt và giao hàng tiện lợi mỗi ngày.',
                    'image_url' => '/theme-demo/ec916/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['EC916-FEATURED-001', 0, 'Giỏ thực phẩm tươi ngon mỗi ngày', 399000, 469000, 'product-grocery.webp'],
                ['EC916-FEATURED-002', 1, 'Điện thoại Nova Air 5G', 6680000, 8900000, 'product-phone.webp'],
                ['EC916-FEATURED-003', 2, 'Đầm Coral thanh lịch', 699000, 890000, 'product-dress.webp'],
                ['EC916-FEATURED-004', 7, 'Tai nghe không dây Deep Sound', 1290000, 1690000, 'product-headphones.webp'],
                ['EC916-BEAUTY-001', 3, 'Bộ dưỡng da Pure Glow', 399000, 790000, 'product-skincare.webp'],
                ['EC916-BEAUTY-002', 3, 'Serum cấp ẩm chuyên sâu', 299000, 590000, 'product-skincare.webp'],
                ['EC916-BEAUTY-003', 3, 'Combo chăm sóc cơ thể', 449000, 850000, 'product-skincare.webp'],
                ['EC916-BEAUTY-004', 3, 'Bộ quà tặng làm đẹp Belle', 599000, 990000, 'product-skincare.webp'],
                ['EC916-BEAUTY-005', 6, 'Hộp chăm sóc mẹ và bé', 349000, 520000, 'product-baby.webp'],
                ['EC916-BEAUTY-006', 6, 'Combo vệ sinh dịu nhẹ cho bé', 259000, 399000, 'product-baby.webp'],
                ['EC916-BEAUTY-007', 4, 'Máy xay dinh dưỡng Daily Mix', 899000, 1190000, 'product-blender.webp'],
                ['EC916-BEAUTY-008', 5, 'Ghế sofa thư giãn HomePlus', 6990000, 8500000, 'product-sofa.webp'],
            ];

            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec916-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 24,
                    'short_description' => 'Sản phẩm chính hãng, chất lượng được kiểm soát và giao hàng nhanh chóng.',
                    'detail_content' => '<p>Bách Hóa Xanh Plus tuyển chọn sản phẩm thiết thực cho gia đình với mức giá minh bạch và chính sách đổi trả rõ ràng.</p>',
                    'image_url' => '/theme-demo/ec916/'.$image,
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['ĐẠI TIỆC MUA SẮM', 'Hàng ngàn ưu đãi – Giá tốt mỗi ngày'],
                ['MỌI NHU CẦU, MỘT ĐIỂM ĐẾN', 'Mua sắm tiện lợi cho cả gia đình'],
            ] as $index => [$badge, $title]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec916-hero-slider',
                    'title' => $title,
                    'subtitle' => 'Hàng chính hãng, giao nhanh tận nơi, thanh toán linh hoạt và đổi trả dễ dàng.',
                    'image_url' => '/theme-demo/ec916/hero-mega-sale.webp',
                    'link_url' => '#noi-bat',
                    'badge' => $badge,
                    'metadata' => ['button_label' => 'Mua ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Mẹo mua sắm EC916',
                'slug' => 'ec916-meo-mua-sam',
                'description' => 'Mẹo chọn sản phẩm, săn ưu đãi và chăm sóc gia đình.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Bí quyết chọn thực phẩm tươi ngon cho cả tuần', 'Cách lên danh sách mua sắm khoa học để tiết kiệm thời gian và giữ trọn dinh dưỡng.', 'promo-grocery.webp'],
                ['Chu trình chăm sóc da tối giản cho người bận rộn', 'Ba bước cơ bản giúp làn da khỏe mạnh mà không cần quá nhiều sản phẩm.', 'promo-beauty.webp'],
                ['Nâng cấp tiện nghi gia đình với ngân sách hợp lý', 'Ưu tiên những thiết bị có công năng thiết thực và chính sách bảo hành rõ ràng.', 'promo-electronics.webp'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec916/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec916-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Bách Hóa Xanh Plus chia sẻ những mẹo nhỏ giúp việc mua sắm hằng ngày nhanh hơn, tiết kiệm hơn và an tâm hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC916 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Danh mục', 'url' => route('site.home').'#danh-muc'],
                    ['label' => 'Ưu đãi', 'url' => route('site.home').'#noi-bat'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ Bách Hóa Xanh Plus',
                    'status' => 'published',
                    'excerpt' => 'Hỗ trợ đơn hàng, đổi trả và thông tin sản phẩm.',
                    'body' => '<p>Đội ngũ Bách Hóa Xanh Plus luôn sẵn sàng hỗ trợ để trải nghiệm mua sắm của bạn thuận tiện và an tâm.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'Bách Hóa Xanh Plus',
                'company_description' => 'Mua sắm thực phẩm, công nghệ, thời trang, làm đẹp và gia dụng tiện lợi mỗi ngày.',
                'support_hotline' => '1900 6750',
                'support_email' => 'support@sapo.vn',
                'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội',
            ];
            $profile->forceFill([
                'site_name' => 'Bách Hóa Xanh Plus',
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
