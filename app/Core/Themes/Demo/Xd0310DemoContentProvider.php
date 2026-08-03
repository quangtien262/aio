<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
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

class Xd0310DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0310';

    private const PRESET_KEY = 'xd0310-garden-landscape';

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
        return ['key' => self::PRESET_KEY, 'label' => 'Garden landscape', 'description' => 'Default banner, landscape services, projects, consultation form, news and fixed footer for XD0310.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0310.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Thiết kế cảnh quan', 'Lên ý tưởng, phân khu công năng và lựa chọn cây trồng phù hợp với kiến trúc, ánh sáng và thói quen sử dụng.', 'photo-1585320806297-9794b3e4eeae'],
                ['Thi công sân vườn', 'Triển khai nền, hệ thống tưới, cây xanh và vật liệu cảnh quan theo hồ sơ thiết kế đã thống nhất.', 'photo-1416879595882-3373a0480b5b'],
                ['Chăm sóc cây xanh', 'Cắt tỉa, dinh dưỡng và kiểm soát sâu bệnh định kỳ để khu vườn luôn khỏe mạnh và thẩm mỹ.', 'photo-1558904541-efa843a96f01'],
                ['Hồ cá và tiểu cảnh', 'Thiết kế điểm nhấn mặt nước, đá và cây bản địa để tạo nên không gian thư giãn tự nhiên.', 'photo-1497250681960-ef046c08a56e'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0310-garden-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiết', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Vườn Nhật tĩnh tại', 'Lối đá, mặt nước và các lớp cây được tiết chế để tạo cảm giác bình yên.', 'photo-1497250681960-ef046c08a56e'],
                ['Cảnh quan biệt thự ven sông', 'Không gian xanh nhiều tầng, kết nối khu sinh hoạt ngoài trời với kiến trúc biệt thự.', 'photo-1416879595882-3373a0480b5b'],
                ['Hồ cá Koi và sân trong', 'Điểm nhấn mặt nước cân bằng với bóng râm và vật liệu tự nhiên.', 'photo-1585320806297-9794b3e4eeae'],
                ['Vườn đô thị đa lớp', 'Giải pháp cây xanh gọn gàng cho nhà phố và văn phòng có diện tích hạn chế.', 'photo-1558904541-efa843a96f01'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd0310-garden-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem dự án', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1200), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            foreach ([
                ['GreenSeed', 'Vườn ươm cây cảnh', 'photo-1416879595882-3373a0480b5b'],
                ['Terra Stone', 'Đá và vật liệu tự nhiên', 'photo-1497250681960-ef046c08a56e'],
                ['AquaFlow', 'Giải pháp tưới tự động', 'photo-1558904541-efa843a96f01'],
                ['EcoWood', 'Gỗ ngoài trời bền vững', 'photo-1585320806297-9794b3e4eeae'],
                ['Botanica', 'Cây nội và ngoại thất', 'photo-1501004318641-b39e6451bec6'],
                ['Living Earth', 'Đất trồng và dinh dưỡng', 'photo-1492496913980-501348b61469'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0310-garden-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Kiến tạo khu vườn xanh cho mọi không gian sống', 'Thiết kế, thi công và chăm sóc cảnh quan trọn gói với giải pháp phù hợp từng công trình.', 'photo-1585320806297-9794b3e4eeae'],
                ['Đưa thiên nhiên trở lại gần hơn với cuộc sống', 'Không gian xanh hài hòa, dễ chăm sóc và bền vững theo thời gian.', 'photo-1416879595882-3373a0480b5b'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0310-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'Garden Haven', 'metadata' => ['kicker' => 'Cảnh quan bền vững', 'summary' => $summary, 'button_label' => 'Nhận tư vấn miễn phí'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0310 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'], ['label' => 'Dự án', 'url' => '#du-an'], ['label' => 'Đội ngũ', 'url' => '#doi-ngu'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Garden Haven', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Garden Haven', 'company_description' => 'Thiết kế, thi công và chăm sóc cảnh quan xanh cho nhà ở, biệt thự và không gian thương mại.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@gardenhaven.vn', 'support_location' => '28 Đường Số 12, Thảo Điền, TP. Hồ Chí Minh'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 4, 'projects' => 4, 'partners' => 6, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'services' => 0, 'projects' => 0, 'partners' => 0, 'landing_pages' => 0];

        if ($serviceIds = $ids(CmsService::class)) {
            CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($projectIds = $ids(CmsProject::class)) {
            CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete();
            $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete();
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
