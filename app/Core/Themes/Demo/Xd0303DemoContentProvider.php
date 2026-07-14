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

class Xd0303DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0303';
    private const PRESET_KEY = 'xd0303-service-operations';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'Dịch vụ vận hành', 'description' => 'Banner, dịch vụ, đối tác, menu và landingpage mẫu cho XD0303.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0303.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Chuyển nhà trọn gói', 'Khảo sát, đóng gói và vận chuyển an toàn với lịch làm việc linh hoạt.', 'photo-1600518464441-9154a4dea21b'],
                ['Vận chuyển hàng hóa', 'Điều phối phương tiện phù hợp, theo dõi lộ trình rõ ràng và đúng hẹn.', 'photo-1586864387967-d02ef85d93e8'],
                ['Vệ sinh sau thi công', 'Hoàn thiện không gian sạch sẽ trước khi bàn giao và đưa vào sử dụng.', 'photo-1581578731548-c64695cc6952'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0303-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiết', 'link_url' => '#hotline', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Fix Services', 'Dịch vụ vận hành và bảo trì', 'photo-1586864387967-d02ef85d93e8'],
                ['Home Expert', 'Giải pháp vận chuyển thông minh', 'photo-1600518464441-9154a4dea21b'],
                ['Home Movers', 'Đối tác vận hành', 'photo-1581578731548-c64695cc6952'],
                ['Fix Store', 'Bảo trì và sửa chữa', 'photo-1581092919535-7146ff1a5904'],
                ['Garage Doors', 'Giải pháp công trình', 'photo-1541888946425-d81bb19240f5'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0303-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Dịch vụ chuyên nghiệp, chất lượng nhanh gọn', 'Đội ngũ chuyên nghiệp, quy trình minh bạch và phương án phù hợp cho từng nhu cầu chuyển nhà, chuyển văn phòng hoặc vận tải hàng hóa.', 'photo-1600518464441-9154a4dea21b'],
                ['Vận hành nhanh chóng, an toàn và đúng hẹn', 'Hỗ trợ từ khảo sát đến bàn giao, giúp khách hàng theo dõi rõ ràng từng bước thực hiện.', 'photo-1586864387967-d02ef85d93e8'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0303-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#hotline', 'badge' => 'Dịch vụ vận hành', 'metadata' => ['kicker' => 'Dịch vụ vận hành', 'summary' => $summary, 'button_label' => 'Liên hệ ngay'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0303 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Quy trình', 'url' => '#quy-trinh'], ['label' => 'Đối tác', 'url' => '#doi-tac'], ['label' => 'Liên hệ', 'url' => '#footer']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Dịch vụ vận hành', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Dịch vụ vận hành', 'company_description' => 'Giải pháp dịch vụ linh hoạt, rõ ràng và đặt trải nghiệm khách hàng làm trọng tâm.', 'support_hotline' => '1900 9477', 'support_email' => 'admin@example.vn', 'support_location' => '344 Huỳnh Tấn Phát, Quận 7, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 3, 'partners' => 5, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
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
