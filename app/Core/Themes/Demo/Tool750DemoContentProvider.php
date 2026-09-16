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
use App\Models\CmsTestimonial;
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

class Tool750DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'TOOL750';

    private const PRESET_KEY = 'tool750-industrial';

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
            'label' => 'TOOL750 Industrial Store',
            'description' => 'Cửa hàng dụng cụ cơ khí vàng đen với Catalog, CMS và dữ liệu demo hoàn chỉnh.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho TOOL750.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categories = [];
            $categoryDefinitions = [
                ['Dụng cụ điện', 'product-drill'],
                ['Dụng cụ cầm tay', 'product-wrench'],
                ['Phụ kiện cơ khí', 'product-measure'],
                ['Thiết bị ngoài trời', 'product-blower'],
                ['Máy móc nhà xưởng', 'product-saw'],
                ['Đồ bảo hộ', 'tool-collection'],
                ['Cơ khí neo', 'product-grinder'],
            ];

            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('tool750-'.$name),
                    'description' => 'Thiết bị được tuyển chọn theo độ bền, hiệu suất và an toàn sử dụng.',
                    'image_url' => $this->asset($image),
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $productDefinitions = [
                ['T750-DRILL-01', 0, 'Máy khoan búa pin Brushless 20V', 4890000, 5690000, 'product-drill'],
                ['T750-GRIND-01', 0, 'Máy mài góc công nghiệp 125mm', 2190000, 2590000, 'product-grinder'],
                ['T750-WRENCH-01', 1, 'Máy siết bu lông lực cao 20V', 3750000, 4290000, 'product-wrench'],
                ['T750-SAW-01', 0, 'Máy cưa đĩa chính xác 185mm', 3290000, 3890000, 'product-saw'],
                ['T750-MEASURE-01', 2, 'Bộ đo laser và thước cuộn Pro', 1490000, 1790000, 'product-measure'],
                ['T750-BLOWER-01', 3, 'Máy thổi bụi công trường Compact', 1890000, 2290000, 'product-blower'],
                ['T750-DRILL-02', 0, 'Bộ máy khoan và sạc nhanh 2 pin', 5250000, 6150000, 'product-drill'],
                ['T750-GRIND-02', 4, 'Máy mài pin không chổi than', 3490000, 4090000, 'product-grinder'],
                ['T750-WRENCH-02', 1, 'Máy siết vít va đập thân máy', 2450000, 2950000, 'product-wrench'],
                ['T750-SAW-02', 4, 'Máy cắt đa năng đế hợp kim', 2790000, 3290000, 'product-saw'],
                ['T750-MEASURE-02', 2, 'Thiết bị đo khoảng cách 60m', 1190000, 1490000, 'product-measure'],
                ['T750-BLOWER-02', 3, 'Máy thổi khí pin hiệu suất cao', 2290000, 2690000, 'product-blower'],
            ];

            foreach ($productDefinitions as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('tool750-'.$name),
                    'sku' => $sku,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 12 + $index * 3,
                    'short_description' => 'Động cơ hiệu suất cao, thân máy chắc chắn và thiết kế tối ưu cho thao tác dài giờ.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn cho nhu cầu thi công chuyên nghiệp, đi kèm thông số rõ ràng và hỗ trợ kỹ thuật tận tâm.</p>',
                    'image_url' => $this->asset($image),
                    'is_featured' => $index < 9,
                    'is_highlight' => $index < 9,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang kỹ thuật TOOL750',
                'slug' => 'tool750-cam-nang-ky-thuat',
                'description' => 'Kiến thức lựa chọn, vận hành và bảo dưỡng dụng cụ cơ khí.',
            ]);
            $this->record($postCategory);
            $postDefinitions = [
                ['Cách chọn máy khoan phù hợp từng vật liệu', 'Các thông số cần lưu ý khi làm việc với gỗ, kim loại và bê tông.', 'hero-tools'],
                ['Bảo dưỡng dụng cụ điện đúng cách', 'Lịch kiểm tra đơn giản giúp thiết bị vận hành ổn định hơn.', 'workshop-service'],
                ['Năm nguyên tắc an toàn trong xưởng cơ khí', 'Tạo thói quen làm việc an toàn trước khi khởi động bất kỳ thiết bị nào.', 'tool-collection'],
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
                    'slug' => Str::slug('tool750-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>TOOL750 tổng hợp hướng dẫn thực tế để người thợ vận hành thiết bị hiệu quả, bền bỉ và an toàn hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index < 2,
                ]);
                $this->record($post);
            }

            foreach ([
                ['Anh Dũng', 'Quản lý xưởng', 'Danh mục rõ ràng, sản phẩm chắc chắn và đội ngũ tư vấn đúng nhu cầu thực tế.'],
                ['Minh Quân', 'Kỹ sư công trình', 'Thiết bị vận hành ổn định, giao hàng đúng hẹn và hỗ trợ sau bán hàng nhanh.'],
                ['Thu Hà', 'Chủ cửa hàng vật tư', 'Thông số minh bạch, mức giá hợp lý và chính sách đổi trả dễ hiểu.'],
            ] as $index => [$name, $role, $quote]) {
                $testimonial = CmsTestimonial::query()->create([
                    'name' => $name,
                    'role' => $role,
                    'company' => 'Khách hàng TOOL750',
                    'quote' => $quote,
                    'image_url' => null,
                    'image_alt' => $name,
                    'status' => 'published',
                    'publish_at' => now(),
                    'is_featured' => true,
                    'sort_order' => $index,
                ]);
                $this->record($testimonial);
            }

            foreach (['IRONWORKS', 'PROGEAR', 'MAKERLAB', 'FORTIS', 'MECHANO', 'BUILDMAX'] as $index => $name) {
                $partner = CmsPartner::query()->create([
                    'title' => $name,
                    'slug' => Str::slug('tool750-'.$name),
                    'description' => 'Thương hiệu thiết bị đồng hành cùng TOOL750.',
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
                'name' => 'TOOL750 Main Menu',
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
                    'title' => 'Liên hệ TOOL750',
                    'status' => 'published',
                    'excerpt' => 'Tư vấn lựa chọn máy móc, dụng cụ và phụ kiện phù hợp.',
                    'body' => '<p>Hãy gửi nhu cầu, vật liệu cần gia công và tần suất sử dụng để đội ngũ kỹ thuật đề xuất thiết bị phù hợp.</p>',
                    'publish_at' => now(),
                ],
            );
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'TOOL750 Cơ Khí Việt',
                'company_description' => 'Thiết bị cơ khí chính hãng cho xưởng máy, công trình và người thợ hiện đại.',
                'slogan' => 'Sức mạnh cho mọi công trình',
                'support_hotline' => '1900 6750',
                'support_email' => 'support@htvietnam.vn',
                'support_location' => 'Trung tâm thiết bị cơ khí Hà Nội',
            ];
            $profile->forceFill([
                'site_name' => 'TOOL750 Cơ Khí Việt',
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
                    'testimonials' => 3,
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
        $counts = ['categories' => 0, 'products' => 0, 'post_categories' => 0, 'posts' => 0, 'media' => 0, 'testimonials' => 0, 'partners' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }

        foreach ([
            [CmsTestimonial::class, 'testimonials'],
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
        return '/themes/TOOL750/images/'.$name.'.png';
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
