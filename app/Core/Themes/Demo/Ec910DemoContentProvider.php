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

class Ec910DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC910';
    private const PRESET_KEY = 'ec910-dola-watch';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'EC910 Dola Watch', 'description' => 'Cửa hàng đồng hồ chính hãng phong cách đen và vàng kim.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC910.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Đồng hồ nam', 'watch-men.webp'],
                ['Đồng hồ nữ', 'watch-women.webp'],
                ['Đồng hồ thể thao', 'sport-black.webp'],
                ['Đồng hồ cơ', 'automatic-rose.webp'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec910-'.$name),
                    'description' => 'Đồng hồ chính hãng, bảo hành minh bạch và tư vấn tận tâm.',
                    'image_url' => '/theme-demo/ec910/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['CASIO MTP-1370D - Nam Quartz', 1607000, 2000000, 0, 'classic-silver.webp'],
                ['CASIO Edifice Chronograph', 3529000, 4100000, 2, 'sport-black.webp'],
                ['TISSOT Tradition Rose Gold', 14700000, 18300000, 3, 'automatic-rose.webp'],
                ['G-SHOCK GA-2000 Sport', 4638000, 5200000, 2, 'diver-blue.webp'],
                ['SEIKO 5 Field Sports', 7090000, 8200000, 0, 'smartwatch-black.webp'],
                ['ORIENT Open Heart', 11760000, 13900000, 3, 'automatic-rose.webp'],
                ['DOXA Calex Sapphire', 33010000, 35900000, 0, 'classic-silver.webp'],
                ['FOSSIL Heritage Automatic', 8590000, 9323000, 1, 'women-pink.webp'],
                ['CITIZEN Eco Drive Classic', 6280000, 7100000, 0, 'classic-silver.webp'],
                ['LONGINES Heritage Moonphase', 45600000, 49900000, 3, 'automatic-rose.webp'],
                ['CASIO G-Shock Carbon Core', 7950000, 8900000, 2, 'sport-black.webp'],
                ['SEIKO Presage Cocktail', 12900000, 14500000, 0, 'diver-blue.webp'],
            ];
            foreach ($products as $index => [$name, $price, $original, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec910-'.$name),
                    'sku' => 'EC910-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $original,
                    'stock' => 30,
                    'short_description' => $index % 2 === 0 ? 'Miễn phí thay pin trọn đời cho tất cả khách hàng.' : 'Tặng kèm gói bảo hành lên đến 5 năm.',
                    'detail_content' => '<p>Đồng hồ chính hãng được tuyển chọn kỹ lưỡng, hoàn thiện tinh tế và bảo hành minh bạch tại Dola Watch.</p>',
                    'image_url' => '/theme-demo/ec910/'.$image,
                    'is_featured' => $index < 8,
                    'is_highlight' => $index < 8,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Tinh hoa thời gian', 'Đồng hồ chính hãng cho mọi phong cách.', 'hero-watches.webp'],
                ['Dấu ấn dành cho quý ông', 'Khám phá bộ sưu tập cơ khí và quartz mới.', 'watch-men.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec910-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec910/'.$image,
                    'link_url' => '#khuyen-mai',
                    'badge' => 'DOLA WATCH',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Khám phá ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Kiến thức đồng hồ',
                'slug' => 'ec910-kien-thuc-dong-ho',
                'description' => 'Kinh nghiệm chọn mua, sử dụng và bảo quản đồng hồ.',
            ]);
            $this->record($postCategory);
            $posts = [
                ['Toàn bộ sự thật về mặt kính sapphire', 'Kính sapphire có độ cứng cao, chống trầy xước và giữ vẻ đẹp bền lâu.', 'classic-silver.webp'],
                ['“Lá chắn” thép không gỉ 904L có tốt như lời đồn?', 'Tìm hiểu vật liệu và quy trình chế tạo thép không gỉ cao cấp.', 'sport-black.webp'],
                ['Kính cứng đồng hồ là gì?', 'Khám phá cấu tạo, công dụng và cách bảo quản mặt kính.', 'diver-blue.webp'],
                ['Kính khoáng đồng hồ và 4 lý do được dùng phổ biến', 'Những ưu điểm của mineral crystal trong sử dụng hằng ngày.', 'classic-silver.webp'],
                ['Cách khử mùi dây da đồng hồ tại nhà', 'Những bước đơn giản giúp dây da luôn sạch và bền.', 'automatic-rose.webp'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec910/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec910-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Dola Watch chia sẻ kiến thức hữu ích để bạn chọn và sử dụng đồng hồ hiệu quả hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC910 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
                    ['label' => 'Thương hiệu', 'url' => route('site.home').'#thuong-hieu'],
                    ['label' => 'Đồng hồ nam', 'url' => route('site.home').'#dong-ho-nam'],
                    ['label' => 'Đồng hồ nữ', 'url' => route('site.catalog.search')],
                    ['label' => 'Kiến thức đồng hồ', 'url' => route('site.home').'#kinh-nghiem'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                ['title' => 'Liên hệ Dola Watch', 'status' => 'published', 'excerpt' => 'Tư vấn đồng hồ và hỗ trợ đơn hàng.', 'body' => '<p>Đội ngũ Dola Watch luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()],
            );
            if ($contact->wasRecentlyCreated) $this->record($contact);

            $profile = SiteProfile::query()->firstOrNew();
            $existingBranding = (array) $profile->branding;
            $customLogo = trim((string) ($existingBranding['logo_url'] ?? ''));
            $profile->forceFill([
                'site_name' => 'Dola Watch',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge($existingBranding, [
                    'company_name' => 'DOLA WATCH',
                    'company_description' => 'Đồng hồ chính hãng, dịch vụ tận tâm và bảo hành minh bạch.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'support@htvietnam.vn',
                    'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
                ], $customLogo !== '' ? ['logo_url' => $customLogo] : []),
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

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
        ThemeDemoRecord::query()->create([
            'theme_key' => self::THEME_KEY,
            'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ]);
    }
}
