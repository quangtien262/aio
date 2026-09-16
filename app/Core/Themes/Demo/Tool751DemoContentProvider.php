<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Tool751DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'TOOL751';

    private const PRESET_KEY = 'tool751-bee-store';

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
            'label' => 'TOOL751 Bee Tools Store',
            'description' => 'Cửa hàng dụng cụ cơ khí vàng đen với hero khuyến mãi, danh mục và Catalog hoàn chỉnh.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho TOOL751.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categories = [];
            $categoryDefinitions = [
                ['Dụng cụ cầm tay', 'product-wrench'],
                ['Dụng cụ dùng pin', 'product-drill'],
                ['Máy nổ', 'product-blower'],
                ['Máy cắt', 'product-saw'],
                ['Dụng cụ cơ khí', 'product-grinder'],
                ['Dụng cụ xây dựng', 'product-measure'],
                ['Dụng cụ gia đình', 'product-drill'],
            ];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('tool751-'.$name),
                    'description' => 'Thiết bị bền bỉ, thông số minh bạch và phù hợp cho công việc thực tế.',
                    'image_url' => $this->asset($image),
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['T751-DRILL-01', 1, 'Máy khoan pin đa năng 20V', 3290000, 3790000, 'product-drill'],
                ['T751-GRIND-01', 4, 'Máy mài góc công suất lớn', 2250000, 2500000, 'product-grinder'],
                ['T751-WRENCH-01', 0, 'Máy siết bu lông lực cao', 4100000, 4550000, 'product-wrench'],
                ['T751-SAW-01', 3, 'Máy cưa đĩa bàn cắt 185mm', 3650000, 4190000, 'product-saw'],
                ['T751-MEASURE-01', 5, 'Bộ thước đo công trường Pro', 1450000, 1690000, 'product-measure'],
                ['T751-BLOWER-01', 2, 'Máy thổi bụi pin gọn nhẹ', 1890000, 2190000, 'product-blower'],
                ['T751-DRILL-02', 1, 'Bộ khoan động lực hai pin', 4990000, 5590000, 'product-drill'],
                ['T751-GRIND-02', 4, 'Máy mài pin không chổi than', 3450000, 3890000, 'product-grinder'],
                ['T751-WRENCH-02', 0, 'Thân máy siết vít va đập', 2750000, 3090000, 'product-wrench'],
                ['T751-SAW-02', 3, 'Máy cắt đa năng chuyên dụng', 2890000, 3290000, 'product-saw'],
            ];

            foreach ($productDefinitions as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('tool751-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 15 + $index * 2,
                    'short_description' => 'Thiết kế chắc chắn, thao tác thuận tiện và hiệu suất ổn định trong nhiều giờ.',
                    'detail_content' => '<p>Sản phẩm TOOL751 được tuyển chọn cho nhu cầu gia đình, xưởng máy và công trường chuyên nghiệp.</p>',
                    'image_url' => $this->asset($image),
                    'is_featured' => $index < 8,
                    'is_highlight' => $index < 8,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Góc kỹ thuật TOOL751',
                'slug' => 'tool751-goc-ky-thuat',
                'description' => 'Tin tức, hướng dẫn sử dụng và bảo dưỡng dụng cụ.',
            ]);
            $this->record($postCategory);

            $postDefinitions = [
                ['Ra mắt dòng máy khoan pin thế hệ mới', 'Thiết kế nhỏ gọn, mạnh mẽ và phù hợp nhiều bề mặt thi công.', 'hero-sale'],
                ['Cách nhận biết dụng cụ chính hãng', 'Những dấu hiệu quan trọng khi kiểm tra máy và phụ kiện.', 'product-drill'],
                ['Chọn máy khoan cho công việc gia đình', 'Công suất, đầu kẹp và nguồn pin là ba yếu tố nên cân nhắc.', 'workshop-service'],
            ];

            foreach ($postDefinitions as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => $this->asset($image),
                    'mime_type' => 'image/png',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('tool751-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>TOOL751 chia sẻ kinh nghiệm thực tế để thiết bị hoạt động bền bỉ, hiệu quả và an toàn hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => true,
                ]);
                $this->record($post);
            }

            foreach (['WORKFORGE', 'BEEPRO', 'IRONLAB', 'TOOLMATE', 'BUILDFORCE', 'MECHLAB'] as $index => $name) {
                $partner = CmsPartner::query()->create([
                    'title' => $name,
                    'slug' => Str::slug('tool751-'.$name),
                    'description' => 'Đối tác thiết bị đồng hành cùng TOOL751.',
                    'image_url' => null,
                    'image_alt' => $name,
                    'link_url' => '#top',
                    'status' => 'published',
                    'publish_at' => now(),
                    'is_featured' => true,
                    'sort_order' => $index,
                ]);
                $this->record($partner);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'TOOL751 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Giới thiệu', 'url' => $home.'#gioi-thieu'],
                    ['label' => 'Sản phẩm', 'url' => $home.'#san-pham'],
                    ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(
                ['slug' => 'contact'],
                [
                    'title' => 'Liên hệ TOOL751',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn chọn dụng cụ, máy móc và phụ kiện phù hợp.',
                    'body' => '<p>Gửi nhu cầu công việc để đội ngũ TOOL751 đề xuất thiết bị và phương án sử dụng phù hợp.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'BeeTools Store',
                'company_description' => 'Kênh dụng cụ cơ khí đáng tin cậy cho gia đình, xưởng máy và công trình.',
                'slogan' => 'Dụng cụ cơ khí cho mọi nhà',
                'support_hotline' => '1900 6750',
                'support_email' => 'support@htvietnam.vn',
                'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội',
            ];
            $profile->forceFill([
                'site_name' => 'BeeTools Store',
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
                    'products' => count($productDefinitions),
                    'post_categories' => 1,
                    'posts' => count($postDefinitions),
                    'media' => count($postDefinitions),
                    'partners' => 6,
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
        $counts = ['categories' => 0, 'products' => 0, 'post_categories' => 0, 'posts' => 0, 'media' => 0, 'partners' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }

        foreach ([
            [CmsPartner::class, 'partners'],
            [CmsPost::class, 'posts'],
            [CmsMedia::class, 'media'],
            [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'],
            [CatalogProduct::class, 'products'],
            [CatalogCategory::class, 'categories'],
            [CmsMenu::class, 'menus'],
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

    private function asset(string $name): string
    {
        return '/themes/TOOL751/images/'.$name.'.png';
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
