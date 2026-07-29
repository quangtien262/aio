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

class Dn351DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'DN351';
    private const PRESET_KEY = 'dn351-meatlers-market';

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
            'label' => 'DN351 Meatlers Market',
            'description' => 'Cửa hàng thực phẩm cao cấp với thịt, hải sản, rau sạch, bài viết ẩm thực và landing page DN351.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho DN351.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Hải sản', 'category-seafood.jpg'],
                ['Rau sạch', 'category-vegetables.jpg'],
                ['Các loại thịt', 'category-meat.jpg'],
                ['Sản phẩm mới', 'product-chicken.jpg'],
            ];
            $categories = [];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('dn351-'.$name),
                    'description' => 'Thực phẩm tươi ngon được Meatlers tuyển chọn mỗi ngày.',
                    'image_url' => '/theme-demo/dn351/'.$image,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['DN351-CHICKEN-01', 3, 'Ức gà phi lê', 60000, 70000, 'product-chicken.jpg'],
                ['DN351-SCALLOP-01', 0, 'Cồi sò điệp Nhật Bản', 1090000, null, 'product-scallops.jpg'],
                ['DN351-CRAB-01', 0, 'Ghẹ xanh loại 2', 550000, 650000, 'product-crab.jpg'],
                ['DN351-CRAB-02', 0, 'Cua lông Hong Kong', 846000, 950000, 'product-crab.jpg'],
                ['DN351-KINGCRAB-01', 0, 'Cua King Crab đỏ sống', 1650000, 2150000, 'category-seafood.jpg'],
                ['DN351-OCTOPUS-01', 0, 'Bạch tuộc Nhật', 500000, 750000, 'blog-squid-grilled.jpg'],
                ['DN351-BEEF-01', 2, 'Bò Wagyu cắt lát', 1290000, null, 'category-meat.jpg'],
                ['DN351-VEG-01', 1, 'Rau củ hữu cơ', 99000, null, 'category-vegetables.jpg'],
            ];

            foreach ($products as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('dn351-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 50 + $index,
                    'short_description' => 'Tươi ngon, nguồn gốc minh bạch và được bảo quản theo tiêu chuẩn Meatlers.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn kỹ, đóng gói an toàn và giao tươi đến khách hàng.</p>',
                    'image_url' => '/theme-demo/dn351/'.$image,
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([
                ['Nhà cung cấp thực phẩm tươi tốt nhất thị trường', 'Nguồn thực phẩm minh bạch, tuyển chọn mỗi ngày.'],
                ['Thịt tươi chuẩn nhà hàng', 'Cắt mới mỗi ngày, đóng gói an toàn và giao tận nơi.'],
            ] as $index => [$title, $subtitle]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'dn351-hero-slider',
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'image_url' => '/theme-demo/dn351/hero-market.jpg',
                    'link_url' => '#danh-muc',
                    'badge' => 'MEATLERS',
                    'metadata' => ['button_label' => 'Khám phá ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang ẩm thực DN351',
                'slug' => 'dn351-cam-nang-am-thuc',
                'description' => 'Công thức và kiến thức lựa chọn thực phẩm tươi ngon.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Đậm vị với mực nướng chanh tỏi ớt', 'Công thức nhanh gọn giúp mực giữ được độ ngọt và hương thơm đặc trưng.', 'blog-squid-grilled.jpg'],
                ['Canh cá saba chua ngọt', 'Món canh thanh mát, giàu dinh dưỡng cho bữa cơm gia đình.', 'blog-fish-soup.jpg'],
                ['Mực lá tốt cho sức khỏe tim mạch', 'Gợi ý cách lựa chọn và chế biến mực tươi ngon, an toàn.', 'blog-stuffed-calamari.jpg'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $filePath = 'theme-demo/dn351/'.$image;
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => $filePath,
                    'file_url' => '/storage/'.$filePath,
                    'mime_type' => 'image/jpeg',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('dn351-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Meatlers chia sẻ bí quyết chọn nguyên liệu và chế biến món ăn ngon cho gia đình.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'DN351 Primary Navigation',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home', [], false)],
                    ['label' => 'Giới thiệu', 'url' => route('site.home', [], false).'#gioi-thieu'],
                    ['label' => 'Sản phẩm', 'url' => route('site.home', [], false).'#san-pham'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index', [], false)],
                    ['label' => 'Thư viện', 'url' => route('site.home', [], false).'#thu-vien'],
                    ['label' => 'Liên hệ', 'url' => route('site.contact', [], false)],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ Meatlers',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn nguồn hàng và giao thực phẩm tươi mỗi ngày.',
                    'body' => '<p>Hãy liên hệ Meatlers để được tư vấn sản phẩm, số lượng và lịch giao phù hợp.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew(['website_key' => $websiteKey]);
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'Meatlers',
                'company_description' => 'Thực phẩm tươi tuyển chọn, nguồn gốc minh bạch và giao hàng tận nơi.',
                'support_hotline' => '1900 9477',
                'support_email' => 'hello@meatlers.vn',
                'support_location' => '344 Huỳnh Tấn Phát, Quận 7, TP. Hồ Chí Minh',
            ];
            $profile->forceFill([
                'site_name' => 'Meatlers Market',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => $branding,
            ])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
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
