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

class Ec908DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC908';
    private const PRESET_KEY = 'ec908-ego-fitness';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'EC908 Ego Fitness', 'description' => 'Cửa hàng thực phẩm bổ sung và phụ kiện fitness với Landing Page Builder.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho EC908.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Sản phẩm nổi bật', 'whey-black.png'], ['Tăng cân', 'whey-white.png'], ['Tăng cơ', 'whey-black.png'],
                ['Sức bền', 'whey-white.png'], ['Giảm cân', 'whey-black.png'], ['Protein', 'whey-white.png'],
                ['Dầu cá', 'whey-black.png'], ['Phụ kiện', 'accessory-banner.png'], ['Dưỡng chất', 'whey-white.png'], ['Vitamin', 'whey-black.png'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('ec908-'.$name),
                    'description' => 'Dinh dưỡng thể thao chính hãng, nguồn gốc minh bạch.',
                    'image_url' => '/theme-demo/ec908/'.$image, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $definitions = [
                ['BEST', 'Premium Whey Protein chuyên nghiệp', 950000, 1120000, 5, 'whey-black.png'],
                ['BEST', 'Whey tinh khiết 100% Pure', 350000, 399000, 5, 'whey-white.png'],
                ['BEST', 'ISO Whey Zero nhập khẩu', 840000, 910000, 5, 'whey-black.png'],
                ['BEST', 'Whey Protein Isolate cao cấp', 1240000, 1340000, 5, 'whey-white.png'],
                ['BEST', 'Mass Gainer tăng cân bền vững', 1580000, 1790000, 1, 'whey-black.png'],
                ['BEST', 'Creatine Monohydrate sức mạnh', 1440000, 1650000, 2, 'whey-black.png'],
                ['ACCESSORY', 'Bình lắc thể thao cao cấp', 250000, 295000, 7, 'whey-black.png'],
                ['ACCESSORY', 'Bộ dây kháng lực đa năng', 350000, 399000, 7, 'whey-white.png'],
                ['ACCESSORY', 'Găng tay tập gym chống trượt', 420000, 490000, 7, 'whey-black.png'],
                ['ACCESSORY', 'Dây kéo lưng hỗ trợ nâng tạ', 290000, 340000, 7, 'whey-white.png'],
            ];
            foreach ($definitions as $index => [$group, $name, $price, $original, $categoryIndex, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('ec908-'.$name),
                    'sku' => 'EC908-'.$group.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price, 'original_price' => $original, 'stock' => 50,
                    'short_description' => 'Sản phẩm chính hãng, phù hợp mục tiêu luyện tập và giao nhanh trong ngày.',
                    'detail_content' => '<p>Ego Fitness lựa chọn sản phẩm dựa trên thành phần, nguồn gốc và nhu cầu luyện tập thực tế. Hướng dẫn sử dụng và chính sách đổi trả được công bố minh bạch.</p>',
                    'image_url' => '/theme-demo/ec908/'.$image, 'is_featured' => $group === 'BEST',
                    'is_highlight' => true, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([['Hot Deal dinh dưỡng', 'Giảm đến 50% và quà tặng thể thao'], ['Năng lượng bứt phá', 'Ưu đãi whey và mass gainer chính hãng']] as $index => [$title, $summary]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY, 'placement' => 'ec908-hero-slider', 'title' => $title,
                    'subtitle' => $summary, 'image_url' => '/theme-demo/ec908/hero-fitness.png',
                    'link_url' => '#san-pham-ban-chay', 'badge' => 'HOT DEAL',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Mua ngay'],
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Góc sức khỏe Ego Fitness', 'slug' => 'ec908-goc-suc-khoe',
                'description' => 'Kiến thức tập luyện, dinh dưỡng và phục hồi.',
            ]);
            $this->record($postCategory);
            $posts = [
                ['Cẩn thận với các thói quen tập gym khiến làn da xấu đi', 'Tập gym đúng cách giúp cơ thể khỏe mạnh và hạn chế những tác động không mong muốn.', 'health-main.png'],
                ['5 dấu hiệu chứng tỏ bạn đang tập gym sai cách và quá sức', 'Những tín hiệu cơ thể cần được nghỉ ngơi và điều chỉnh cường độ luyện tập.', 'health-triptych.png'],
                ['Bí quyết cho đôi chân thon dài và săn chắc', 'Một giáo án cân bằng giúp cải thiện sức mạnh và độ săn chắc của đôi chân.', 'health-triptych.png'],
                ['Kỷ luật tạo nên vóc dáng của vận động viên thể hình', 'Câu chuyện truyền cảm hứng về dinh dưỡng và luyện tập bền bỉ.', 'health-triptych.png'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title, 'file_path' => '', 'file_url' => '/theme-demo/ec908/'.$image,
                    'mime_type' => 'image/png', 'size' => 0, 'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('ec908-'.$title),
                    'status' => 'published', 'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Ego Fitness tổng hợp kiến thức giúp bạn luyện tập an toàn, ăn uống khoa học và phục hồi hiệu quả.</p>',
                    'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index + 1), 'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'EC908 Main Menu', 'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Sản phẩm +', 'url' => route('site.catalog.search')],
                    ['label' => 'Sản phẩm yêu thích', 'url' => route('site.catalog.search')],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                    ['label' => 'Hệ thống cửa hàng', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);
            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], [
                'title' => 'Liên hệ Ego Fitness', 'status' => 'published',
                'excerpt' => 'Tư vấn dinh dưỡng thể thao và hỗ trợ đơn hàng.',
                'body' => '<p>Đội ngũ Ego Fitness luôn sẵn sàng đồng hành cùng mục tiêu luyện tập của bạn.</p>', 'publish_at' => now(),
            ]);
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }
            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'Ego Fitness', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'Ego Fitness', 'company_description' => 'Dinh dưỡng thể thao và phụ kiện fitness chính hãng.',
                    'support_hotline' => '19006750', 'support_email' => 'support@egofitness.vn',
                    'support_location' => '530 Thụy Khuê - Tây Hồ, Hà Nội',
                ]),
            ])->save();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) {
                $this->record($landing);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'categories' => count($categoryDefinitions), 'products' => count($definitions), 'banners' => 2,
                    'post_categories' => 1, 'posts' => count($posts), 'media' => count($posts),
                    'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1,
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
            'theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class, 'model_id' => $model->getKey(),
        ]);
    }
}
