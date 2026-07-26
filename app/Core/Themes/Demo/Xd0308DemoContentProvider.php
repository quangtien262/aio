<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
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

class Xd0308DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0308';
    private const PRESET_KEY = 'xd0308-study-abroad';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'Logistics và vận tải', 'description' => 'Banner, dịch vụ logistics, đối tác, menu và landingpage mẫu cho XD0308.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0308.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Vận tải nội địa', 'Điều phối phương tiện phù hợp, theo dõi lộ trình rõ ràng và đúng hẹn.', 'photo-1586191582151-f73872dfd183'],
                ['Vận chuyển hàng không', 'Kết nối lịch bay và thủ tục hàng hóa với quy trình theo dõi minh bạch.', 'photo-1436491865332-7a61a109cc05'],
                ['Giải pháp kho bãi', 'Lưu trữ, kiểm soát tồn kho và giao nhận linh hoạt theo kế hoạch vận hành.', 'photo-1586528116493-da8c7e6d8e14'],
                ['Vận tải đường biển', 'Tối ưu hành trình container và thời gian giao nhận xuyên biên giới.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0308-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiết', 'link_url' => '#hotline', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['CargoLink', 'Vận tải quốc tế', 'photo-1494412651409-8963ce7935a7'],
                ['NorthStar', 'Chuỗi cung ứng', 'photo-1586528116493-da8c7e6d8e14'],
                ['Global Freight', 'Giao nhận hàng hóa', 'photo-1586191582151-f73872dfd183'],
                ['BluePort', 'Khai thác cảng', 'photo-1566576912321-d58ddd7a6088'],
                ['AirWay', 'Vận tải hàng không', 'photo-1436491865332-7a61a109cc05'],
                ['RouteOne', 'Vận tải nội địa', 'photo-1586528116311-ad8dd3c8310d'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0308-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Dịch vụ vận tải hàng không đường biển', 'Tối ưu lịch trình, phương thức vận chuyển và chi phí cho từng lô hàng.', 'photo-1494412651409-8963ce7935a7'],
                ['Logistics chủ động cho doanh nghiệp', 'Kết nối kho bãi, vận tải và giao nhận bằng một quy trình rõ ràng.', 'photo-1586528116493-da8c7e6d8e14'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0308-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#footer', 'badge' => 'Logistics và vận tải', 'metadata' => ['kicker' => 'Logistics và vận tải', 'summary' => $summary, 'button_label' => 'Nhận báo giá'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0308 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Giải pháp', 'url' => '#giai-phap'], ['label' => 'Thư viện', 'url' => '#thu-vien'], ['label' => 'Đối tác', 'url' => '#doi-tac'], ['label' => 'Liên hệ', 'url' => '#footer']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Logistics Việt', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Logistics Việt', 'company_description' => 'Giải pháp vận tải và hậu cần linh hoạt, kết nối doanh nghiệp với mọi hành trình.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@logisticsviet.vn', 'support_location' => '344 Huỳnh Tấn Phát, Quận 7, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(\App\Support\SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(\App\Support\SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 4, 'partners' => 6, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'services' => 0, 'partners' => 0, 'landing_pages' => 0];

        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($partnerIds = $ids(CmsPartner::class)) $counts['partners'] = CmsPartner::query()->whereKey($partnerIds)->delete();
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
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
