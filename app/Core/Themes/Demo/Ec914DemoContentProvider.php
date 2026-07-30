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

class Ec914DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC914';
    private const PRESET_KEY = 'ec914-moc-nhien-craft';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'EC914 Mộc Nhiên Craft', 'description' => 'Cửa hàng mây tre, decor tự nhiên và quà tặng thủ công với hệ chữ riêng.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC914.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Túi mây & phụ kiện', 'product-bag-round.webp'],
                ['Giỏ & khay', 'product-basket-picnic.webp'],
                ['Đèn mây tre', 'product-lamp-sculpture.webp'],
                ['Decor nội thất', 'collection-wall.webp'],
                ['Gương trang trí', 'product-mirror-sun.webp'],
                ['Chậu & giỏ cây', 'product-basket-planter.webp'],
                ['Quà tặng thủ công', 'product-tray-round.webp'],
                ['Bộ sưu tập mới', 'collection-lamps.webp'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('ec914-'.$name),
                    'description' => 'Sản phẩm thủ công từ vật liệu tự nhiên, hoàn thiện bởi người thợ Việt.',
                    'image_url' => '/theme-demo/ec914/'.$image, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['EC914-SALE-001', 1, 'Giỏ hoa đan mây Bella', 1380000, 1450000, 'product-basket-planter.webp', true],
                ['EC914-SALE-002', 1, 'Giỏ picnic Emma', 500000, 670000, 'product-basket-picnic.webp', true],
                ['EC914-SALE-003', 1, 'Khay mây Harvest Moon', 990000, 1600000, 'product-tray-round.webp', true],
                ['EC914-SALE-004', 0, 'Túi mây tròn Mia', 880000, 1090000, 'product-bag-round.webp', true],
                ['EC914-FEATURED-001', 0, 'Túi đan tròn Hoa Mộc', 1300000, 0, 'product-bag-round.webp', true],
                ['EC914-FEATURED-002', 0, 'Túi tote Rafia', 990000, 1230000, 'product-bag-tote.webp', true],
                ['EC914-FEATURED-003', 4, 'Gương mây Mặt Trời', 790000, 890000, 'product-mirror-sun.webp', true],
                ['EC914-FEATURED-004', 5, 'Giỏ cây An Nhiên', 550000, 680000, 'product-basket-planter.webp', true],
                ['EC914-BASKET-001', 1, 'Giỏ picnic Đồng Nội', 1180000, 1350000, 'product-basket-picnic.webp', false],
                ['EC914-BASKET-002', 1, 'Khay trà Mộc Viên', 490000, 620000, 'product-tray-round.webp', false],
                ['EC914-BASKET-003', 1, 'Giỏ tote Ngày Nắng', 920000, 1090000, 'product-bag-tote.webp', false],
                ['EC914-LAMP-001', 2, 'Đèn mây Tre Hai Tầng', 780000, 980000, 'product-lamp-sculpture.webp', false],
                ['EC914-LAMP-002', 2, 'Đèn cầu Ánh Mây', 890000, 990000, 'product-lamp-globe.webp', false],
                ['EC914-LAMP-003', 2, 'Đèn thả Nắng Vàng', 690000, 0, 'product-lamp-sculpture.webp', false],
            ];
            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image, $featured]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name, 'slug' => Str::slug('ec914-'.$name), 'sku' => $sku,
                    'price' => $price, 'original_price' => $originalPrice ?: null, 'stock' => 35,
                    'short_description' => 'Đan thủ công từ mây tre tự nhiên, nhẹ, bền và mang vẻ đẹp riêng của từng sợi vật liệu.',
                    'detail_content' => '<p>Mỗi sản phẩm được hoàn thiện thủ công, giữ lại sắc độ và đường vân tự nhiên của mây tre.</p>',
                    'image_url' => '/theme-demo/ec914/'.$image, 'is_featured' => $featured,
                    'is_highlight' => true, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Chạm vào vẻ đẹp mộc', 'Mỗi món đồ là một câu chuyện mộc mạc'],
                ['Từ đôi tay người thợ Việt', 'Thủ công truyền thống trong không gian sống hiện đại'],
            ] as $index => [$badge, $title]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY, 'placement' => 'ec914-hero-slider',
                    'title' => $title, 'subtitle' => 'Từng sợi mây đan lưu giữ hơi thở thiên nhiên và bàn tay người thợ Việt.',
                    'image_url' => '/theme-demo/ec914/hero-craft.webp', 'link_url' => '#noi-bat', 'badge' => $badge,
                    'metadata' => ['button_label' => 'Khám phá ngay'], 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Chuyện nhà Mộc EC914', 'slug' => 'ec914-chuyen-nha-moc', 'description' => 'Cảm hứng sống và câu chuyện nghề thủ công.']);
            $this->record($postCategory);
            $posts = [
                ['Mây tre đan – chất liệu thân thiện cho ngôi nhà hiện đại', 'Vẻ đẹp nhẹ nhàng, bền vững và dễ kết hợp của vật liệu tự nhiên.', 'collection-craft.webp'],
                ['Cách bảo quản giỏ và túi mây luôn bền đẹp', 'Một vài thói quen đơn giản giúp sản phẩm giữ dáng và màu sắc lâu hơn.', 'product-bag-round.webp'],
                ['Ánh sáng mây tre làm ấm không gian sống', 'Chọn kiểu đèn, kích thước và sắc sáng phù hợp cho từng góc nhà.', 'collection-lamps.webp'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => '/theme-demo/ec914/'.$image, 'mime_type' => 'image/webp', 'size' => 0, 'alt_text' => $title]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('ec914-'.$title),
                    'status' => 'published', 'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Mộc Nhiên chia sẻ những điều nhỏ giúp bạn sống gần thiên nhiên và trân trọng sản phẩm thủ công hơn.</p>',
                    'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index + 1), 'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC914 Main Menu', 'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Về chúng tôi', 'url' => route('site.home').'#cau-chuyen'],
                    ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Bộ sưu tập', 'url' => route('site.home').'#bo-suu-tap'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ Mộc Nhiên Craft', 'status' => 'published', 'excerpt' => 'Tư vấn sản phẩm thủ công và quà tặng.', 'body' => '<p>Chúng tôi luôn sẵn sàng cùng bạn chọn món đồ phù hợp với không gian sống.</p>', 'publish_at' => now()]);
            if ($contact->wasRecentlyCreated) $this->record($contact);

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += ['company_name' => 'Mộc Nhiên Craft', 'company_description' => 'Đồ thủ công từ tre, mây và vật liệu tự nhiên, được hoàn thiện bằng đôi tay của người thợ Việt.', 'support_hotline' => '0399162342', 'support_email' => 'hello@mocnhien.example', 'support_location' => '70 Lữ Gia, Phường Phú Thọ, TP.HCM'];
            $profile->forceFill(['site_name' => 'Mộc Nhiên Craft', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => $branding])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return ['preset' => $this->preset(), 'counts' => ['categories' => count($categoryDefinitions), 'products' => count($products), 'banners' => 2, 'post_categories' => 1, 'posts' => count($posts), 'media' => count($posts), 'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
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
