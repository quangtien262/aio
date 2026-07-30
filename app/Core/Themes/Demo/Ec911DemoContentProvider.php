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

class Ec911DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC911';
    private const PRESET_KEY = 'ec911-digitech';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'EC911 DIGITECH', 'description' => 'Cửa hàng máy ảnh, ống kính và thiết bị quay phim với Landing Page Builder.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC911.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Máy ảnh', 'camera-pro.png'], ['Ống kính', 'lens-tele.png'], ['Máy quay phim', 'camera-pro.png'],
                ['Camera hành động', 'action-camera.webp'], ['Phụ kiện nhiếp ảnh', 'lens-tele.png'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('ec911-'.$name), 'description' => 'Thiết bị hình ảnh chính hãng, bảo hành minh bạch.',
                    'image_url' => '/theme-demo/ec911/'.$image, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $definitions = [
                ['LENS', 'Ống kính Prime 24mm f/1.8 Macro', 18900000, 20490000, 1, 'lens-tele.png'],
                ['LENS', 'Ống kính Zoom DX 18-140mm VR', 18900000, 21900000, 1, 'lens-tele.png'],
                ['LENS', 'Ống kính Telephoto 800mm f/6.3', 150000000, 163990000, 1, 'lens-tele.png'],
                ['LENS', 'Ống kính Portrait 85mm f/1.2', 65000000, 69000000, 1, 'lens-tele.png'],
                ['LENS', 'Ống kính AF 75mm f/1.8', 9900000, 11900000, 1, 'lens-tele.png'],
                ['CAMERA', 'Máy ảnh DIGITECH D850 Body', 50990000, 57990000, 0, 'camera-pro.png'],
                ['CAMERA', 'Máy ảnh DIGITECH Creator Pro', 50990000, 57990000, 0, 'camera-pro.png'],
                ['CAMERA', 'Máy ảnh DIGITECH Cinema 6K', 138990000, 159990000, 2, 'camera-pro.png'],
                ['CAMERA', 'Máy ảnh Mirrorless X1', 18590000, 19990000, 0, 'camera-pro.png'],
                ['CAMERA', 'Máy ảnh Compact Travel 77', 35990000, 44000000, 0, 'action-camera.webp'],
            ];
            foreach ($definitions as $index => [$group, $name, $price, $original, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('ec911-'.$name),
                    'sku' => 'EC911-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price, 'original_price' => $original, 'stock' => 50,
                    'short_description' => 'Thiết bị hình ảnh chính hãng, giao nhanh và bảo hành rõ ràng.',
                    'detail_content' => '<p>Sản phẩm DIGITECH được chọn lọc theo chất lượng hình ảnh, độ bền và trải nghiệm sử dụng. Chính sách bảo hành và đổi trả được công bố minh bạch.</p>',
                    'image_url' => '/theme-demo/ec911/'.$image, 'is_featured' => $group === 'LENS', 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([['Máy quay Vlog Creator X1', 'Quay 6K, chống rung và màn hình xoay lật'], ['Thiết bị sáng tạo chuyên nghiệp', 'Khơi nguồn cảm hứng cho mọi nhà sáng tạo']] as $index => [$title, $summary]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY, 'placement' => 'ec911-hero-slider', 'title' => $title, 'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec911/hero-vlog.png', 'link_url' => '#flash-sale', 'badge' => 'DIGITECH',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Khám phá ngay'], 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Tin nhiếp ảnh DIGITECH', 'slug' => 'ec911-tin-nhiep-anh', 'description' => 'Tin máy ảnh, thiết bị quay và kinh nghiệm sáng tạo.']);
            $this->record($postCategory);
            $posts = [
                ['Đánh giá máy ảnh mirrorless X-T5: quay 6K trên thiết kế cổ điển', 'Khám phá khả năng quay phim, lấy nét và màu sắc của mẫu máy ảnh mới.', 'camera-pro.png'],
                ['So sánh gimbal điện thoại và camera chống rung chuyên dụng', 'Những tiêu chí quan trọng để chọn thiết bị ghi hình khi di chuyển.', 'action-camera.webp'],
                ['Bí quyết thiết lập ánh sáng cho studio tại nhà', 'Tạo nguồn sáng mềm, tự nhiên và kiểm soát màu sắc hiệu quả.', 'campaign-cameras.png'],
                ['Bất ngờ với hệ thống camera điều khiển từ xa thế hệ mới', 'Công nghệ tự động hóa mở ra cách kể chuyện hình ảnh hoàn toàn mới.', 'news-camera.webp'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => '/theme-demo/ec911/'.$image, 'mime_type' => str_ends_with($image, '.png') ? 'image/png' : 'image/webp', 'size' => 0, 'alt_text' => $title]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('ec911-'.$title), 'status' => 'published',
                    'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p><p>DIGITECH tổng hợp thông tin hữu ích giúp bạn chọn và sử dụng thiết bị hình ảnh hiệu quả hơn.</p>',
                    'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index + 1), 'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create(['name' => 'EC911 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'url' => route('site.home')], ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
                ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')], ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                ['label' => 'Liên hệ', 'url' => route('site.contact')],
            ]]);
            $this->record($menu);
            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ DIGITECH', 'status' => 'published', 'excerpt' => 'Tư vấn máy ảnh và hỗ trợ đơn hàng.', 'body' => '<p>Đội ngũ DIGITECH luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()]);
            if ($contact->wasRecentlyCreated) $this->record($contact);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'DIGITECH', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'DIGITECH', 'company_description' => 'Máy ảnh, ống kính và thiết bị quay phim chính hãng.',
                    'support_hotline' => '0399162342', 'support_email' => 'support@htvietnam.vn',
                    'support_location' => 'Tầng 6, Tòa nhà Ladeco, 266 Đội Cấn, Hà Nội',
                ]),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return ['preset' => $this->preset(), 'counts' => [
                'categories' => count($categoryDefinitions), 'products' => count($definitions), 'banners' => 2,
                'post_categories' => 1, 'posts' => count($posts), 'media' => count($posts),
                'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0,
            ], 'purged' => $purged];
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
        foreach ([[CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) {
            if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        }
        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
