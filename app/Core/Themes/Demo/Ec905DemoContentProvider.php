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

class Ec905DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC905';
    private const PRESET_KEY = 'ec905-egohome';

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
            'label' => 'EC905 Ego Home',
            'description' => 'Cửa hàng vật liệu hoàn thiện, thiết bị phòng tắm và nội thất với trang chủ cấu hình bằng Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC905.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Bồn cầu & thiết bị vệ sinh', 'Thiết bị vệ sinh hiện đại, dễ sử dụng.', 'product-06.webp'],
                ['Chậu rửa & vòi chậu', 'Chậu sứ và vòi nước cho phòng tắm.', 'product-07.webp'],
                ['Sen tắm & bồn tắm', 'Giải pháp thư giãn và chăm sóc cơ thể.', 'product-08.webp'],
                ['Phụ kiện phòng tắm', 'Phụ kiện đồng bộ cho không gian tiện nghi.', 'product-06.webp'],
                ['Thiết bị nhà bếp', 'Chậu rửa, hút mùi và thiết bị bếp.', 'product-09.webp'],
                ['Sơn nội thất', 'Màu sơn bền đẹp cho không gian bên trong.', 'product-01.webp'],
                ['Sơn ngoại thất', 'Bảo vệ công trình trước thời tiết.', 'product-02.webp'],
                ['Gạch ốp lát', 'Bề mặt gạch, đá và vân gỗ chọn lọc.', 'tile-01.webp'],
                ['Đèn & thiết bị điện', 'Giải pháp ánh sáng ấm áp, tiết kiệm.', 'product-12.webp'],
                ['Lọc nước & chăm sóc nhà', 'Thiết bị hỗ trợ cuộc sống khỏe và tiện nghi.', 'product-11.webp'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec905-'.$name),
                    'description' => $description,
                    'image_url' => '/theme-demo/ec905/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $groups = [
                'PAINT' => [
                    ['Sơn nội thất mịn HomeCare 18L', 2090000, 2390000, 5, 'product-01.webp'],
                    ['Sơn ngoại thất WeatherShield 18L', 2680000, 2980000, 6, 'product-02.webp'],
                    ['Sơn chống thấm AquaGuard 15L', 2450000, 2750000, 6, 'product-03.webp'],
                    ['Sơn bóng cao cấp SatinPro 5L', 1800000, 2100000, 5, 'product-04.webp'],
                    ['Sơn lót kháng kiềm PrimeBase 18L', 1950000, 2250000, 5, 'product-05.webp'],
                ],
                'TILE' => [
                    ['Gạch bê tông sáng Urban 60×60', 260000, 350000, 7, 'tile-01.webp'],
                    ['Gạch vân gỗ Nordic Oak 15×90', 597000, 690000, 7, 'tile-02.webp'],
                    ['Gạch travertine Sandstone 60×120', 475000, 530000, 7, 'tile-03.webp'],
                    ['Gạch onyx ngọc trai Ivory 80×80', 710000, 720000, 7, 'tile-04.webp'],
                    ['Gạch đá graphite Slate 60×60', 288000, 330000, 7, 'tile-05.webp'],
                    ['Gạch marble Frost Grey 60×120', 520000, 590000, 7, 'tile-06.webp'],
                    ['Gạch vân gỗ Walnut 20×120', 610000, 690000, 7, 'tile-07.webp'],
                    ['Gạch limestone Cream 60×60', 390000, 450000, 7, 'tile-08.webp'],
                    ['Gạch slate Cool Grey 30×60', 330000, 380000, 7, 'tile-09.webp'],
                    ['Gạch marble Pearl 80×80', 680000, 720000, 7, 'tile-10.webp'],
                ],
                'HOME' => [
                    ['Bồn cầu treo tường PureLine', 7990000, 8990000, 0, 'product-06.webp'],
                    ['Chậu lavabo sứ thanh lịch', 2890000, 3290000, 1, 'product-07.webp'],
                    ['Sen cây nhiệt độ RainFlow', 6190000, 6900000, 2, 'product-08.webp'],
                    ['Chậu rửa bếp inox chống xước', 4590000, 5200000, 4, 'product-09.webp'],
                    ['Máy hút mùi kính cong AirClean', 7390000, 8100000, 4, 'product-10.webp'],
                    ['Máy lọc nước để bàn FreshPure', 6490000, 7200000, 9, 'product-11.webp'],
                    ['Đèn thả thủy tinh Amber Glow', 1590000, 1890000, 8, 'product-12.webp'],
                ],
            ];
            $productCount = 0;
            $sort = 0;
            foreach ($groups as $group => $definitions) {
                foreach ($definitions as $index => [$name, $price, $originalPrice, $categoryIndex, $image]) {
                    $product = CatalogProduct::query()->create([
                        'catalog_category_id' => $categories[$categoryIndex]->id,
                        'name' => $name,
                        'slug' => Str::slug('ec905-'.$group.'-'.$name),
                        'sku' => 'EC905-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                        'price' => $price,
                        'original_price' => $originalPrice,
                        'stock' => 40,
                        'short_description' => 'Sản phẩm hoàn thiện nhà ở được tuyển chọn, tư vấn rõ ràng và hỗ trợ giao lắp.',
                        'detail_content' => '<p>Sản phẩm được Ego Home tuyển chọn theo tiêu chí bền, đẹp và phù hợp khí hậu Việt Nam. Đội ngũ tư vấn hỗ trợ chọn mẫu, dự toán và hướng dẫn lắp đặt.</p>',
                        'image_url' => '/theme-demo/ec905/'.$image,
                        'is_featured' => in_array($group, ['PAINT', 'TILE'], true),
                        'is_highlight' => $group !== 'HOME',
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]);
                    $this->record($product);
                    $productCount++;
                }
            }

            $banner = SiteBanner::query()->create([
                'theme_key' => self::THEME_KEY,
                'placement' => 'ec905-hero-slider',
                'title' => 'Kiến tạo phòng tắm hiện đại',
                'subtitle' => 'Thiết bị đồng bộ, vật liệu bền đẹp và hỗ trợ thi công tận tâm.',
                'image_url' => '/theme-demo/ec905/hero-bathroom.webp',
                'link_url' => '#son-noi-ngoai',
                'badge' => 'GIẢI PHÁP NHÀ ĐẸP 2026',
                'metadata' => ['summary' => 'Thiết bị đồng bộ, vật liệu bền đẹp và hỗ trợ thi công tận tâm.', 'button_label' => 'Khám phá ngay'],
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($banner);

            $projectCategory = CmsCategory::query()->create([
                'name' => 'Dự án thi công EC905',
                'slug' => 'ec905-du-an-thi-cong',
                'description' => 'Không gian phòng tắm và nhà bếp đã hoàn thiện.',
            ]);
            $this->record($projectCategory);
            $newsCategory = CmsCategory::query()->create([
                'name' => 'Tư vấn nhà đẹp EC905',
                'slug' => 'ec905-tu-van-nha-dep',
                'description' => 'Kinh nghiệm chọn vật liệu và chăm sóc nhà ở.',
            ]);
            $this->record($newsCategory);

            $postDefinitions = [
                [$projectCategory, 'Dự án phòng tắm căn hộ Riverside', 'Không gian sáng, gọn với thiết bị vệ sinh đồng bộ.', 'project-01.webp'],
                [$projectCategory, 'Dự án phòng tắm boutique tại Tây Hồ', 'Gạch xanh trầm kết hợp ánh sáng ấm và phụ kiện kim loại.', 'project-02.webp'],
                [$projectCategory, 'Dự án lắp đặt thiết bị vệ sinh trọn gói', 'Quy trình thi công đúng kỹ thuật, bàn giao sạch sẽ.', 'project-03.webp'],
                [$projectCategory, 'Dự án bếp mở cho nhà phố hiện đại', 'Tối ưu lưu trữ, ánh sáng và luồng di chuyển trong bếp.', 'project-04.webp'],
                [$projectCategory, 'Dự án phòng tắm đá tự nhiên', 'Sen mưa và hốc tường tạo trải nghiệm thư giãn tại nhà.', 'project-05.webp'],
                [$newsCategory, 'Tư vấn chọn sơn nội thất an toàn cho gia đình', 'Nên cân nhắc độ phủ, khả năng lau chùi và hàm lượng phát thải.', 'project-06.webp'],
                [$newsCategory, 'Tư vấn phối gạch cho phòng tắm nhỏ rộng hơn', 'Màu sáng, đường ron gọn và kích thước phù hợp giúp không gian thoáng.', 'project-01.webp'],
                [$newsCategory, 'Tư vấn sử dụng bồn cầu thông minh hiệu quả', 'Các lưu ý về nguồn điện, cấp nước và vệ sinh định kỳ.', 'project-03.webp'],
                [$newsCategory, 'Tư vấn bảo trì sen tắm và vòi nước tại nhà', 'Vệ sinh đầu phun và kiểm tra gioăng giúp thiết bị bền hơn.', 'project-05.webp'],
                [$newsCategory, 'Tư vấn chọn thiết bị bếp cho căn hộ mới', 'Ưu tiên kích thước đồng bộ, tiết kiệm điện và dễ vệ sinh.', 'project-04.webp'],
            ];
            foreach ($postDefinitions as $index => [$category, $title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec905/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $category->id,
                    'title' => $title,
                    'slug' => Str::slug('ec905-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Ego Home chia sẻ kinh nghiệm thực tế để bạn lựa chọn vật liệu, thiết bị và giải pháp thi công phù hợp.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => in_array($index, [0, 5], true),
                ]);
                $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'EC905 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Sơn & gạch', 'url' => $home.'#son-noi-ngoai'],
                    ['label' => 'Dự án', 'url' => $home.'#du-an'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ Ego Home', 'status' => 'published', 'excerpt' => 'Tư vấn vật liệu, thiết bị và hỗ trợ thi công.', 'body' => '<p>Đội ngũ Ego Home sẵn sàng hỗ trợ bạn chọn sản phẩm, lập dự toán và lên phương án giao lắp phù hợp.</p>', 'publish_at' => now()],
            );
            if ($page->wasRecentlyCreated) {
                $this->record($page);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'Ego Home',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'Công ty cổ phần Ego Home',
                    'company_description' => 'Vật liệu hoàn thiện và thiết bị nhà ở được tuyển chọn cho không gian sống bền đẹp.',
                    'support_hotline' => '1900 6750',
                    'support_email' => 'support@egohome.vn',
                    'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội',
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing) {
                foreach ([
                    'ec905_projects' => $projectCategory->id,
                    'ec905_news' => $newsCategory->id,
                ] as $blockType => $categoryId) {
                    $block = $landing->blocks()->where('block_type', $blockType)->first();
                    if ($block) {
                        $block->forceFill([
                            'settings' => array_merge((array) $block->settings, ['category_id' => $categoryId]),
                        ])->save();
                    }
                }
            }
            if ($landing && ! $existing) {
                $this->record($landing);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'categories' => count($categoryDefinitions),
                    'products' => $productCount,
                    'banners' => 1,
                    'post_categories' => 2,
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
