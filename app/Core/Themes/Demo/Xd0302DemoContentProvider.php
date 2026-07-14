<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
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

class Xd0302DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0302';
    private const PRESET_KEY = 'xd0302-solar-energy';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'Năng lượng mặt trời Soler Panel', 'description' => 'Dịch vụ, dự án, tin tức và landingpage năng lượng cho XD0302.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0302.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            $category = CmsCategory::query()->create(['name' => 'Năng lượng bền vững', 'slug' => 'xd0302-nang-luong-ben-vung', 'description' => 'Tin tức và kiến thức năng lượng mặt trời.']);
            $this->record($category);

            $serviceDefinitions = [
                ['Giải pháp điện mặt trời mái nhà', 'Khảo sát, thiết kế và triển khai hệ thống điện mặt trời cho nhà máy, văn phòng và công trình thương mại.', 'photo-1509391366360-2e959784a276'],
                ['Tư vấn hạ tầng năng lượng', 'Đánh giá nhu cầu sử dụng điện, xây dựng lộ trình đầu tư và phương án vận hành hiệu quả.', 'photo-1541888946425-d81bb19240f5'],
                ['Bảo trì và giám sát hệ thống', 'Theo dõi hiệu suất, kiểm tra định kỳ và tối ưu sản lượng trong suốt vòng đời dự án.', 'photo-1511818966892-d7d671e672a2'],
            ];
            foreach ($serviceDefinitions as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0302-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Đọc thêm', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            $projectDefinitions = [
                ['Nhà máy điện mặt trời Vĩnh Tân 2', 'Hệ thống điện mặt trời quy mô lớn giúp chủ động nguồn năng lượng và tối ưu chi phí vận hành.', 'photo-1509391366360-2e959784a276'],
                ['Dự án lọc hóa dầu Nghi Sơn', 'Hạ tầng năng lượng đồng bộ cho khu công nghiệp với yêu cầu vận hành liên tục.', 'photo-1581091226825-a6a2a5aee158'],
                ['Tổ hợp sản xuất ô tô', 'Giải pháp năng lượng sạch đồng hành cùng nhà máy sản xuất hiện đại.', 'photo-1531058020387-3be344556be6'],
                ['Giàn khoan tự nâng PV Drilling', 'Giải pháp hạ tầng và giám sát năng lượng cho hoạt động công nghiệp biển.', 'photo-1565514020179-026b92b2dcd9'],
                ['Nhà máy xử lý khí Cà Mau', 'Tư vấn, thiết kế và triển khai theo tiêu chuẩn an toàn nghiêm ngặt.', 'photo-1541888946425-d81bb19240f5'],
            ];
            foreach ($projectDefinitions as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd0302-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem dự án', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            foreach ([
                ['Xu hướng đầu tư điện mặt trời cho nhà máy trong năm nay', 'Các doanh nghiệp đang chuyển từ đầu tư ngắn hạn sang chiến lược tối ưu năng lượng dài hạn.', 'photo-1509391366360-2e959784a276'],
                ['Ba bước đánh giá hiệu quả hệ thống năng lượng mặt trời', 'Khảo sát phụ tải, phân tích mặt bằng và dự kiến hiệu quả tài chính là ba bước cần có.', 'photo-1551830820-330a71b99659'],
                ['Vận hành xanh giúp tăng sức cạnh tranh của doanh nghiệp', 'Một lộ trình năng lượng rõ ràng tạo nền tảng cho các cam kết phát triển bền vững.', 'photo-1486406146926-c627a92ad1ab'],
            ] as $index => [$title, $excerpt, $photo]) {
                $post = CmsPost::query()->create(['title' => $title, 'slug' => Str::slug('xd0302-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'meta_title' => $title, 'meta_description' => $excerpt, 'category_id' => $category->id, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach ([
                ['Thanh Hương', 'Giám đốc vận hành', 'Giải pháp được tư vấn rõ ràng, đội ngũ triển khai đúng tiến độ và chủ động cập nhật trong toàn bộ quá trình thực hiện.', 'photo-1494790108377-be9c29b29330'],
                ['Hoàng Tuấn', 'Quản lý nhà máy', 'Phương án phù hợp với nhu cầu vận hành thực tế. Chúng tôi đánh giá cao cách đội ngũ xử lý các yêu cầu kỹ thuật nhanh chóng.', 'photo-1500648767791-00dcc994a43e'],
                ['Nguyễn Thị Tuyết Vân', 'Khách hàng doanh nghiệp', 'Chất lượng bàn giao tốt, hỗ trợ sau triển khai rất kịp thời và giúp chúng tôi yên tâm hơn khi vận hành hệ thống.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'quote' => $quote, 'image_url' => $image($photo, 320), 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            $catalogCategory = CatalogCategory::query()->create(['name' => 'Thiết bị năng lượng mặt trời', 'slug' => 'xd0302-thiet-bi-nang-luong', 'description' => 'Thiết bị và phụ kiện cho hệ thống năng lượng.', 'image_url' => $image('photo-1509391366360-2e959784a276', 700), 'sort_order' => 0, 'is_active' => true]);
            $this->record($catalogCategory);
            foreach (['Tấm pin mặt trời hiệu suất cao', 'Bộ biến tần thông minh', 'Hệ thống giám sát năng lượng'] as $index => $name) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $catalogCategory->id, 'name' => $name, 'slug' => Str::slug('xd0302-'.$name), 'sku' => 'XD2-0'.($index + 1), 'price' => 15000000 + ($index * 5000000), 'stock' => 20, 'short_description' => 'Thiết bị mẫu cho cấu hình nguồn dữ liệu Sản phẩm của XD0302.', 'detail_content' => '<p>Thiết bị mẫu XD0302.</p>', 'image_url' => $image('photo-1509391366360-2e959784a276', 800), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Giải pháp tốt nhất dành cho doanh nghiệp', 'Tư vấn và triển khai giải pháp năng lượng phù hợp với từng quy mô vận hành.', 'photo-1509391366360-2e959784a276'],
                ['Năng lượng sạch cho tương lai bền vững', 'Chủ động chi phí, tối ưu nguồn lực và giảm phát thải cho doanh nghiệp.', 'photo-1466611653911-95081537e5b7'],
            ] as $index => [$title, $subtitle, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0302-hero-slider', 'title' => $title, 'subtitle' => $subtitle, 'image_url' => $image($photo, 1920), 'link_url' => '#du-an', 'badge' => 'Soler Panel', 'metadata' => ['eyebrow' => 'Soler Panel', 'summary' => $subtitle, 'button_label' => 'Xem dự án'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0302 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Dự án', 'url' => '#du-an'], ['label' => 'Tin tức', 'url' => '#tin-tuc'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Soler Panel', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Soler Panel', 'company_description' => 'Giải pháp năng lượng sạch cho doanh nghiệp.', 'support_hotline' => '1900 9477', 'support_email' => 'admin@solerpanel.vn', 'support_location' => '344 Huỳnh Tấn Phát, Quận 7, TP.HCM'])])->save();

            $existingPage = LandingPage::query()
                ->where('website_key', 'website-main')
                ->where('theme_key', self::THEME_KEY)
                ->where('is_home', true)
                ->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
                $about = $page->blocks()->where('block_type', 'about_experience')->first();
                if ($about) {
                    $about->update(['media' => ['image' => $image('photo-1486406146926-c627a92ad742', 900)]]);
                }
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 3, 'projects' => 5, 'posts' => 3, 'testimonials' => 3, 'products' => 3, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'posts' => 0, 'projects' => 0, 'services' => 0, 'testimonials' => 0, 'products' => 0, 'categories' => 0, 'landing_pages' => 0];

        if ($projectIds = $ids(CmsProject::class)) { CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete(); $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete(); }
        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        if ($postIds = $ids(CmsPost::class)) $counts['posts'] = CmsPost::query()->whereKey($postIds)->delete();
        if ($testimonialIds = $ids(CmsTestimonial::class)) $counts['testimonials'] = CmsTestimonial::query()->whereKey($testimonialIds)->delete();
        if ($productIds = $ids(CatalogProduct::class)) $counts['products'] = CatalogProduct::query()->whereKey($productIds)->delete();
        if ($categoryIds = $ids(CmsCategory::class)) $counts['categories'] += CmsCategory::query()->whereKey($categoryIds)->delete();
        if ($categoryIds = $ids(CatalogCategory::class)) $counts['categories'] += CatalogCategory::query()->whereKey($categoryIds)->delete();
        if ($menuIds = $ids(CmsMenu::class)) $counts['menus'] = CmsMenu::query()->whereKey($menuIds)->delete();
        if ($bannerIds = $ids(SiteBanner::class)) $counts['banners'] = SiteBanner::query()->whereKey($bannerIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
