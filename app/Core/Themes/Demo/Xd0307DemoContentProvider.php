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
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Xd0307DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0307';

    private const PRESET_KEY = 'xd0307-cleaning-services';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder) {}

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
        return ['key' => self::PRESET_KEY, 'label' => 'Klean Services', 'description' => 'Dữ liệu mẫu dịch vụ vệ sinh nhà ở và doanh nghiệp, đội ngũ, đánh giá và tư vấn.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0307.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Vệ sinh nhà ở định kỳ', 'Lịch làm sạch linh hoạt, quy trình rõ ràng và hóa chất an toàn cho gia đình.', 'photo-1581578731548-c64695cc6952'],
                ['Vệ sinh văn phòng', 'Giữ không gian làm việc sạch thoáng mà không ảnh hưởng hoạt động doanh nghiệp.', 'photo-1527515637462-cff94eecc1ac'],
                ['Vệ sinh sau xây dựng', 'Làm sạch bụi mịn, sơn và vật liệu còn lại trước khi bàn giao công trình.', 'photo-1528740561666-dc2479dc08ab'],
                ['Giặt sofa và thảm', 'Thiết bị chuyên dụng giúp làm sạch sâu, khử mùi và bảo vệ bề mặt nội thất.', 'photo-1558618666-fcd25c85cd64'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0307-cleaning-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem dịch vụ', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Green Office', 'Không gian làm việc xanh', 'photo-1497366754035-f200968a6e72'],
                ['Happy Home', 'Căn hộ và nhà ở', 'photo-1505693416388-ac5ce068fe85'],
                ['City Mall', 'Trung tâm thương mại', 'photo-1441986300917-64674bd600d8'],
                ['Care Clinic', 'Phòng khám', 'photo-1519494026892-80bbd2d6fd0d'],
                ['Little Star', 'Trường học', 'photo-1509062522246-3755977927d7'],
                ['North Hotel', 'Khách sạn', 'photo-1566073771259-6a8506099945'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0307-partner-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Không gian sạch, cuộc sống nhẹ nhàng hơn', 'Đội ngũ được đào tạo, đúng giờ và tận tâm cho từng góc nhỏ trong ngôi nhà.', 'photo-1581578731548-c64695cc6952'],
                ['Giải pháp làm sạch đáng tin cậy cho doanh nghiệp', 'Quy trình kiểm soát chất lượng giúp văn phòng luôn sạch thoáng và chuyên nghiệp.', 'photo-1527515637462-cff94eecc1ac'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0307-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'Klean Services', 'metadata' => ['kicker' => 'Klean Services', 'summary' => $summary, 'button_label' => 'Đặt lịch làm sạch'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0307 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Về Klean', 'url' => '#gioi-thieu'], ['label' => 'Lợi ích', 'url' => '#loi-ich'], ['label' => 'Đội ngũ', 'url' => '#doi-ngu'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Klean Services', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Klean Services', 'company_description' => 'Dịch vụ làm sạch tận tâm, an toàn và linh hoạt cho nhà ở, văn phòng.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@klean.vn', 'support_location' => 'Hà Nội và TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(SiteContext::class)->websiteKey(), self::THEME_KEY, true);
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

        if ($serviceIds = $ids(CmsService::class)) {
            CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($partnerIds = $ids(CmsPartner::class)) {
            $counts['partners'] = CmsPartner::query()->whereKey($partnerIds)->delete();
        }
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        if ($menuIds = $ids(CmsMenu::class)) {
            $counts['menus'] = CmsMenu::query()->whereKey($menuIds)->delete();
        }
        if ($bannerIds = $ids(SiteBanner::class)) {
            $counts['banners'] = SiteBanner::query()->whereKey($bannerIds)->delete();
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
