<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
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

class Dn202DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'DN202';

    private const PRESET_KEY = 'dn202-delta-arc-interior';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

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
            'label' => 'DN202 Delta Arc Interior',
            'description' => 'Dữ liệu mẫu nội thất gồm dịch vụ, sản phẩm, dự án, đối tác, banner, menu và landing page DN202.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for DN202.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $websiteKey = app(SiteContext::class)->websiteKey();

            $serviceCategory = CmsServiceCategory::query()->create([
                'name' => 'Giải pháp nội thất DN202',
                'slug' => 'dn202-giai-phap-noi-that',
                'description' => 'Thiết kế và thi công nội thất cho nhà ở và không gian thương mại.',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($serviceCategory);

            $services = [
                ['Nội thất biệt thự', 'Thiết kế không gian biệt thự đồng bộ từ kiến trúc đến đồ rời.', 'fa-solid fa-house-chimney-window'],
                ['Nội thất chung cư', 'Tối ưu diện tích căn hộ với giải pháp lưu trữ và vật liệu bền vững.', 'fa-solid fa-building'],
                ['Nội thất khách sạn', 'Thiết kế phòng nghỉ và khu tiện ích đạt tiêu chuẩn vận hành.', 'fa-solid fa-hotel'],
                ['Nội thất nhà phố', 'Cân bằng công năng, ánh sáng và cá tính cho nhà phố hiện đại.', 'fa-solid fa-house'],
                ['Nội thất showroom', 'Không gian trưng bày giúp sản phẩm nổi bật và dẫn dắt trải nghiệm.', 'fa-solid fa-shop'],
                ['Nội thất văn phòng', 'Môi trường làm việc linh hoạt, nhận diện rõ và dễ mở rộng.', 'fa-solid fa-chair'],
            ];
            foreach ($services as $index => [$title, $summary, $icon]) {
                $service = CmsService::query()->create([
                    'cms_service_category_id' => $serviceCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('dn202-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'icon' => $icon,
                    'button_label' => 'Xem chi tiết',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => $now,
                ]);
                CmsServiceImage::query()->create([
                    'cms_service_id' => $service->id,
                    'image_url' => '/theme-demo/dn202/interior-'.str_pad((string) (($index % 4) + 1), 2, '0', STR_PAD_LEFT).'.jpg',
                    'alt_text' => $title,
                    'is_featured' => true,
                    'sort_order' => 0,
                ]);
                $this->record($service);
            }

            $productCategory = CatalogCategory::query()->create([
                'name' => 'Sản phẩm nội thất DN202',
                'slug' => 'dn202-san-pham-noi-that',
                'description' => 'Nội thất tuyển chọn cho phòng khách, phòng ngủ và không gian sinh hoạt.',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($productCategory);

            $products = [
                ['Giường ngủ gỗ hiện đại', 3450000, 4200000],
                ['Ghế sofa phòng khách thanh lịch', 12450000, 14500000],
                ['Bàn trà gỗ tự nhiên', 3400000, 4200000],
                ['Kệ tivi gỗ sồi tối giản', 3700000, 4500000],
            ];
            foreach ($products as $index => [$name, $price, $originalPrice]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $productCategory->id,
                    'name' => $name,
                    'slug' => Str::slug('dn202-'.$name),
                    'sku' => 'DN202-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 20,
                    'short_description' => 'Sản phẩm nội thất DN202 hoàn thiện chỉn chu, dễ phối trong không gian hiện đại.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn theo tiêu chí thẩm mỹ, độ bền và khả năng sử dụng lâu dài.</p>',
                    'image_url' => '/theme-demo/dn202/interior-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.jpg',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $projects = [
                ['Biệt thự sân vườn An Nhiên', 'Thiết kế và thi công trọn gói không gian sống kết nối sân vườn.'],
                ['Nội thất nhà phố Minh Khai', 'Cải tạo nhà phố với vật liệu ấm, đường nét gọn và nhiều ánh sáng.'],
                ['Căn hộ Park View', 'Tối ưu lưu trữ cho căn hộ gia đình trẻ với bảng màu trung tính.'],
                ['Văn phòng sáng tạo Aurora', 'Không gian làm việc mở, linh hoạt và giàu nhận diện thương hiệu.'],
            ];
            foreach ($projects as $index => [$title, $summary]) {
                $project = CmsProject::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('dn202-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'button_label' => 'Xem dự án',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => $now->copy()->subDays($index * 7),
                ]);
                CmsProjectImage::query()->create([
                    'cms_project_id' => $project->id,
                    'image_url' => '/theme-demo/dn202/project-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.jpg',
                    'alt_text' => $title,
                    'is_featured' => true,
                    'sort_order' => 0,
                ]);
                $this->record($project);
            }

            foreach (['NOVA HOME', 'SEASIDE', 'URBAN HOUSE', 'HORIZON', 'LUMINA', 'ATELIER'] as $index => $name) {
                $partner = CmsPartner::query()->create([
                    'title' => $name,
                    'slug' => Str::slug('dn202-'.$name),
                    'description' => 'Đối tác thiết kế và thi công của DN202.',
                    'image_url' => '/theme-demo/dn202/partner-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.svg',
                    'image_alt' => $name,
                    'link_url' => '#top',
                    'status' => 'published',
                    'publish_at' => $now,
                    'is_featured' => true,
                    'sort_order' => $index,
                ]);
                $this->record($partner);
            }

            foreach ([
                ['Không gian làm việc truyền cảm hứng', 'Thiết kế văn phòng và khu tiếp khách hiện đại.', '/theme-demo/dn202/hero-studio.png'],
                ['Kiến tạo tổ ấm mang dấu ấn riêng', 'Thiết kế và thi công nội thất trọn gói.', '/theme-demo/dn202/hero-villa.jpg'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'dn202-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => '#dich-vu',
                    'metadata' => ['summary' => $summary],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'DN202 Primary Navigation',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => '#top'],
                    ['label' => 'Sản phẩm', 'url' => '#san-pham'],
                    ['label' => 'Dự án', 'url' => '#du-an'],
                    ['label' => 'Thiết kế biệt thự', 'url' => '#thiet-ke-biet-thu'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index', [], false)],
                    ['label' => 'Giới thiệu', 'url' => '#dich-vu'],
                    ['label' => 'Liên hệ', 'url' => route('site.contact', [], false)],
                ],
            ]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew(['website_key' => $websiteKey]);
            $existingBranding = (array) $profile->branding;
            $profile->forceFill([
                'site_name' => 'DN202 Arc',
                'website_type' => 'service',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge([
                    'company_name' => 'DN202 Arc',
                    'company_description' => 'Thiết kế và thi công nội thất trọn gói cho nhà ở, khách sạn, showroom và văn phòng.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'hello@dn202.vn',
                    'support_location' => 'An Thượng, Hà Nội',
                    'working_hours' => '08:00 - 17:00',
                ], $existingBranding),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return [
                'preset' => $this->preset(),
                'counts' => ['service_categories' => 1, 'services' => 6, 'product_categories' => 1, 'products' => 4, 'projects' => 4, 'partners' => 6, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'partners' => 0, 'projects' => 0, 'services' => 0, 'service_categories' => 0, 'products' => 0, 'product_categories' => 0, 'landing_pages' => 0];

        if ($projectIds = $ids(CmsProject::class)) {
            CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete();
            $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete();
        }
        if ($serviceIds = $ids(CmsService::class)) {
            CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        foreach ([[CmsPartner::class, 'partners'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'product_categories'], [CmsServiceCategory::class, 'service_categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] += $model::query()->whereKey($modelIds)->delete();
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
