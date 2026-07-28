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

class Ec912DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC912';

    private const PRESET_KEY = 'ec912-sudes-phone';

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
            'label' => 'EC912 Sudes Phone',
            'description' => 'Cửa hàng điện thoại và thiết bị Apple với Landing Page Builder.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC912.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['iPhone', 'phone-graphite.webp'],
                ['Mac', 'laptop-silver.webp'],
                ['iPad', 'tablet-blue.webp'],
                ['Watch', 'watch-white.webp'],
                ['Âm thanh', 'earbuds-white.webp'],
                ['Phụ kiện', 'charger-wireless.webp'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec912-'.$name),
                    'description' => 'Thiết bị Apple chính hãng, bảo hành minh bạch.',
                    'image_url' => '/theme-demo/ec912/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['iPhone 12 64GB - Chính hãng VN/A', 14790000, 24990000, 'phone-green.webp'],
                ['iPhone 14 Pro Max 512GB - Chính hãng VN/A', 35790000, 43990000, 'phone-graphite.webp'],
                ['iPhone 14 Pro Max 128GB - Chính hãng VN/A', 26890000, 34990000, 'phone-graphite.webp'],
                ['iPhone 14 Plus 512GB - Chính hãng VN/A', 29990000, 36990000, 'phone-blue.webp'],
                ['iPhone 14 256GB - Chính hãng VN/A', 24490000, 30990000, 'phone-green.webp'],
                ['iPhone 14 512GB - Chính hãng VN/A', 27990000, 33990000, 'phone-silver.webp'],
                ['iPhone 14 Plus 128GB - Chính hãng VN/A', 21490000, 27990000, 'phone-silver.webp'],
                ['iPhone 14 Pro Max 256GB - Chính hãng VN/A', 29690000, 37990000, 'phone-graphite.webp'],
            ];

            foreach ($products as $index => [$name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[0]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec912-'.$name),
                    'sku' => 'EC912-IPHONE-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 80,
                    'short_description' => 'Sản phẩm chính hãng VN/A, hỗ trợ trả góp 0% và bảo hành rõ ràng.',
                    'detail_content' => '<p>Sản phẩm Apple chính hãng được kiểm tra chất lượng, công khai giá bán và chính sách bảo hành.</p>',
                    'image_url' => '/theme-demo/ec912/'.$image,
                    'is_featured' => $index < 4,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['iPhone 14 Pro Max - Giá cực sốc', 'Ưu đãi hấp dẫn cho dòng iPhone cao cấp'],
                ['Hệ sinh thái Apple chính hãng', 'iPhone, iPad, Mac, Watch và phụ kiện đồng bộ'],
            ] as $index => [$title, $summary]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec912-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec912/hero-tech.webp',
                    'link_url' => '#hot-sale',
                    'badge' => 'SUDES PHONE',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Xem ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tin công nghệ EC912',
                'slug' => 'ec912-tin-cong-nghe',
                'description' => 'Tin tức điện thoại, Apple và thiết bị thông minh.',
            ]);
            $this->record($postCategory);
            $posts = [
                ['Thú thật: iPhone đã đúng khi không đụng đến tính năng này?', 'Góc nhìn thực tế về thiết kế và trải nghiệm dùng iPhone.', 'story-phone.webp'],
                ['Apple in tiền định thế nào: cá kiếm từ cả điện thoại cũ?', 'Cập nhật xu hướng kinh doanh và vòng đời sản phẩm Apple.', 'story-tablet.webp'],
                ['Đây là mẫu iPhone chính hãng phá giá chưa từng có tại Việt Nam', 'Những thay đổi đáng chú ý về giá bán và ưu đãi.', 'story-review.webp'],
                ['Người giàu cũng khóc: iPhone mới lộ giá bán cao đến khó tin', 'Tổng hợp thông tin thị trường điện thoại cao cấp.', 'story-charging.webp'],
            ];

            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec912/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec912-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Sudes Phone tổng hợp thông tin hữu ích giúp khách hàng lựa chọn thiết bị phù hợp.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC912 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
                    ['label' => 'iPhone', 'url' => route('site.catalog.search').'?q=EC912-IPHONE'],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Chính sách', 'url' => route('site.home').'#chinh-sach'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ Sudes Phone',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn sản phẩm và hỗ trợ đơn hàng.',
                    'body' => '<p>Đội ngũ Sudes Phone luôn sẵn sàng hỗ trợ bạn.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'Sudes Phone',
                'company_description' => 'Hệ thống bán lẻ điện thoại, máy tính, smartwatch và phụ kiện chính hãng.',
                'support_hotline' => '1900 6750',
                'support_email' => 'support@sapo.vn',
                'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
            ];
            $profile->forceFill([
                'site_name' => 'Sudes Phone',
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
        $counts = [
            'categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0,
            'posts' => 0, 'media' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0,
        ];

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
