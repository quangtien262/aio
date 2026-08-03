<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Xd321DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD321';
    private const PRESET_KEY = 'xd321-cargo-logistics';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'XD321 Cargo Logistics', 'description' => 'Cargo, warehouse, packaging and logistics content designed specifically for XD321.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for XD321.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Vận tải đường biển', 'Lịch trình linh hoạt, kết nối cảng biển và tối ưu chi phí cho hàng FCL, LCL.', 'photo-1494412651409-8963ce7935a7'],
                ['Vận tải hàng không', 'Rút ngắn thời gian giao nhận với dịch vụ hàng không quốc tế và theo dõi đơn hàng.', 'photo-1436491865332-7a61a109cc05'],
                ['Vận tải đường bộ', 'Điều phối xe tải và giao nhận nội địa theo lịch hẹn, an toàn và đúng tiến độ.', 'photo-1566576912321-d58ddd7a6088'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiết', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Giải pháp kho mỹ phẩm cho doanh nghiệp', 'Hệ thống lưu kho chuyên biệt, kiểm soát điều kiện bảo quản, nhập xuất và tồn kho theo thời gian thực.', 'photo-1586528116493-da8b8d5a6c44'],
                ['4PL và điều phối chuỗi cung ứng', 'Kết nối nhà cung cấp, kho bãi và đơn vị vận chuyển để tối ưu toàn bộ hành trình đơn hàng.', 'photo-1586528116311-ad8dd3c8310d'],
                ['Hàng dự án và giao nhận chuyên dụng', 'Phương án vận chuyển linh hoạt cho máy móc, thiết bị và các lô hàng có yêu cầu đặc thù.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiết', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            $category = CatalogCategory::query()->create(['name' => 'Vật tư đóng gói XD321', 'slug' => 'xd321-vat-tu-dong-goi', 'description' => 'Thùng carton, seal niêm phong và vật tư hỗ trợ giao nhận.', 'image_url' => $image('photo-1601584115197-04ecc0da31d7', 700), 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);
            foreach ([
                ['Thùng carton 5 lớp 25x20x13cm', 13000, 'photo-1601584115197-04ecc0da31d7'],
                ['Thùng carton 5 lớp 28x22x31cm', 18000, 'photo-1504274066651-8d31a536b11a'],
                ['Thùng carton đóng hàng 70x60x52cm', 26000, 'photo-1586864387967-d02ef85d93e8'],
                ['Thùng carton vách ngăn chai 64x54x46cm', 21000, 'photo-1586864387789-628af9feed72'],
            ] as $index => [$name, $price, $photo]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('xd321-'.$name), 'sku' => 'X321-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $index === 2 ? 28000 : null, 'stock' => 100, 'short_description' => 'Vật tư đóng gói demo cho XD321 Cargo.', 'detail_content' => '<p>Sản phẩm đóng gói phù hợp cho lưu kho và vận chuyển.</p>', 'image_url' => $image($photo, 800), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Những trường hợp nên chọn vận chuyển hàng hóa quốc tế bằng đường bộ', 'Những lưu ý về tuyến đường, chứng từ và cách tối ưu chi phí khi vận chuyển xuyên biên giới.', 'photo-1566576912321-d58ddd7a6088'],
                ['Các loại kho trong logistics và cách tối ưu chuỗi cung ứng', 'Kho hàng, kho phân phối và kho bảo quản cần được tổ chức theo nhu cầu vận hành thực tế.', 'photo-1586528116493-da8b8d5a6c44'],
                ['Cross docking giúp doanh nghiệp rút ngắn thời gian xử lý đơn', 'Mô hình trung chuyển phù hợp cho các doanh nghiệp cần tăng tốc độ giao nhận và giảm tồn kho.', 'photo-1586528116311-ad8dd3c8310d'],
                ['Tầm quan trọng của bảo hiểm hàng hóa trong vận chuyển quốc tế', 'Chuẩn bị đúng giúp doanh nghiệp chủ động hơn trước các rủi ro trên từng chặng đường.', 'photo-1436491865332-7a61a109cc05'],
            ] as $index => [$title, $excerpt, $photo]) {
                $post = CmsPost::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'meta_title' => $title, 'meta_description' => $excerpt, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach ([
                ['ABC Manufacturing', 'Logistics Manager', 'XD321 Cargo là đối tác logistics đáng tin cậy trong vận hành hằng ngày. Đội ngũ phản hồi nhanh và luôn đảm bảo lô hàng được xử lý đúng tiến độ.', 'photo-1494790108377-be9c29b29330'],
                ['Minh Phát Trading', 'Supply Chain Director', 'Báo giá rõ ràng, quy trình minh bạch và khả năng điều phối linh hoạt giúp chúng tôi chủ động hơn trong từng đợt giao hàng.', 'photo-1500648767791-00dcc994a43e'],
                ['Northstar Retail', 'Operations Lead', 'Hệ thống theo dõi đơn hàng và chăm sóc khách hàng của XD321 giúp đội ngũ vận hành tiết kiệm rất nhiều thời gian.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'quote' => $quote, 'image_url' => $image($photo, 500), 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            foreach (['CATHAY', 'LUFTHANSA', 'CMA CGM', 'IATA', 'GLOBAL CARGO'] as $index => $title) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'description' => 'Đối tác logistics quốc tế.', 'image_url' => "https://placehold.co/260x120/ffffff/153d78?text=".urlencode($title), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Nhanh chóng - An toàn cùng XD321 Cargo', 'Tối ưu mọi hành trình vận chuyển từ nội địa đến quốc tế. An toàn và tiết kiệm.', 'photo-1436491865332-7a61a109cc05'],
                ['Kết nối chuỗi cung ứng không giới hạn', 'Dịch vụ vận tải linh hoạt, minh bạch và đúng tiến độ cho doanh nghiệp.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd321-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#dich-vu', 'badge' => 'XD321 Cargo', 'metadata' => ['summary' => $summary, 'button_label' => 'Xem thêm'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD321 Cargo Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Giải pháp', 'url' => '#giai-phap'], ['label' => 'Sản phẩm', 'url' => '#san-pham'], ['label' => 'Tin tức', 'url' => '#tin-tuc'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'XD321 Cargo', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'XD321 Cargo', 'company_description' => 'Đối tác logistics tin cậy, kết nối hàng hóa toàn cầu.', 'support_hotline' => '0399162342', 'support_email' => 'support@xd321cargo.local', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(\App\Support\SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(\App\Support\SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 3, 'projects' => 3, 'products' => 4, 'posts' => 4, 'testimonials' => 3, 'partners' => 5, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'posts' => 0, 'projects' => 0, 'services' => 0, 'testimonials' => 0, 'products' => 0, 'partners' => 0, 'categories' => 0, 'landing_pages' => 0];
        if ($projectIds = $ids(CmsProject::class)) { CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete(); $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete(); }
        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsTestimonial::class, 'testimonials'], [CatalogProduct::class, 'products'], [CmsPartner::class, 'partners'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) { if ($modelIds = $ids($model)) $counts[$key] += $model::query()->whereKey($modelIds)->delete(); }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
