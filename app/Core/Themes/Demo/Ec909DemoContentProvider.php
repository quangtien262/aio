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

class Ec909DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC909';
    private const PRESET_KEY = 'ec909-euro-sound';

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
            'label' => 'EC909 Euro Sound',
            'description' => 'Cửa hàng thiết bị âm thanh cao cấp với sản phẩm, danh mục và bài viết động.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC909.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['All Products', 'Khám phá tất cả thiết bị âm thanh cao cấp.', 'headphone-black.png'],
                ['Headphones', 'Đắm mình trong âm thanh.', 'headphone-burgundy.png'],
                ['Earphones', 'Thiết kế nhỏ gọn, âm thanh tuyệt vời.', 'earbuds-silver.png'],
                ['Speakers', 'Âm thanh sống động cho mọi không gian.', 'speaker-oak.png'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $description, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('ec909-'.$name),
                    'description' => $description,
                    'image_url' => '/theme-demo/ec909/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['HEADPHONE', 'Cloud Dream', 26329000, 28329000, 1, 'headphone-burgundy.png'],
                ['HEADPHONE', 'Nebula Whisper', 9724000, 10724000, 1, 'headphone-beige.png'],
                ['HEADPHONE', 'Oasis Flow', 7906000, 9906000, 1, 'headphone-black.png'],
                ['EARPHONE', 'Trio Athletica', 5270000, 6750000, 2, 'earbuds-silver.png'],
                ['EARPHONE', 'Stealth Precision', 6590000, 7990000, 2, 'earbuds-silver.png'],
                ['EARPHONE', 'Aqua Sprint', 9200000, 11300000, 2, 'earbuds-silver.png'],
                ['RECOMMEND', 'Air Beats Black', 13151000, 14151000, 1, 'headphone-black.png'],
                ['RECOMMEND', 'Cover for Echo Sphere', 2690000, 3490000, 3, 'speaker-oak.png'],
                ['RECOMMEND', 'Echo Elegance', 88000000, 99490000, 3, 'speaker-oak.png'],
                ['RECOMMEND', 'Studio One Wireless', 18990000, 21990000, 1, 'headphone-beige.png'],
            ];
            foreach ($productDefinitions as $index => [$group, $name, $price, $original, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('ec909-'.$name),
                    'sku' => 'EC909-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $original,
                    'stock' => 30,
                    'short_description' => 'Thiết kế tinh giản, vật liệu cao cấp và chất âm được cân chỉnh chi tiết.',
                    'detail_content' => '<p>Euro Sound tuyển chọn thiết bị dựa trên chất lượng hoàn thiện, trải nghiệm nghe và độ bền sử dụng. Sản phẩm được hỗ trợ tư vấn, bảo hành và đổi trả minh bạch.</p>',
                    'image_url' => '/theme-demo/ec909/'.$image,
                    'is_featured' => in_array($group, ['HEADPHONE', 'RECOMMEND'], true),
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Âm thanh stereo sống động – Kết nối đôi, trải nghiệm gấp bội', 'Khám phá thiết bị âm thanh cho mọi hành trình.'],
                ['Tĩnh lặng trong từng nốt nhạc', 'Tai nghe chống ồn với thiết kế tối giản.'],
            ] as $index => [$title, $summary]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ec909-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => '/theme-demo/ec909/hero-underwater.png',
                    'link_url' => '#goi-y',
                    'badge' => 'EURO SOUND',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Xem ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Tin tức Euro Sound',
                'slug' => 'ec909-tin-tuc-am-thanh',
                'description' => 'Kiến thức, trải nghiệm và xu hướng thiết bị âm thanh.',
            ]);
            $this->record($postCategory);
            $posts = [
                ['JBL Tune 520BT và Tune 720BT khác biệt thực sự nằm ở đâu?', 'So sánh chi tiết chất âm, thiết kế và thời lượng pin để lựa chọn phù hợp.', 'news-audio.png'],
                ['Wave Beam hay Wave Buds? So sánh trước khi xuống tiền', 'Những khác biệt đáng chú ý giữa hai dòng tai nghe true wireless phổ biến.', 'earbud-feature.png'],
                ['Top loa bluetooth karaoke bass mạnh, quẩy cực sung', 'Gợi ý thiết bị cho tiệc tại nhà và những chuyến đi cuối tuần.', 'stereo-feature.png'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => '/theme-demo/ec909/'.$image,
                    'mime_type' => 'image/png',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ec909-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Euro Sound tổng hợp thông tin thực tế, dễ hiểu để bạn lựa chọn thiết bị phù hợp với nhu cầu và không gian nghe.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC909 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Nổi bật⌄', 'url' => '#goi-y'],
                    ['label' => 'Sản phẩm⌄', 'url' => route('site.catalog.search')],
                    ['label' => 'Giới thiệu', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Hỗ trợ⌄', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], [
                'title' => 'Liên hệ Euro Sound',
                'status' => 'published',
                'excerpt' => 'Tư vấn sản phẩm âm thanh và hỗ trợ đơn hàng.',
                'body' => '<p>Đội ngũ Euro Sound sẵn sàng tư vấn thiết bị phù hợp với nhu cầu nghe nhạc, làm việc, giải trí và gaming.</p>',
                'publish_at' => now(),
            ]);
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'Euro Sound',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'Euro Sound',
                    'company_description' => 'Thiết bị âm thanh chính hãng, thiết kế tinh tế và công nghệ hiện đại.',
                    'support_hotline' => '0773915520',
                    'support_email' => 'support@htvietnam.vn',
                    'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
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
                    'products' => count($productDefinitions),
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
        foreach ([
            [CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'],
            [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
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
