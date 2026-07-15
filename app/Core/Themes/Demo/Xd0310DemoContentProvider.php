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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Xd0310DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0310';
    private const PRESET_KEY = 'xd0310-garden-landscape';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'Garden landscape', 'description' => 'Default banner, landscape services, projects, consultation form, news and fixed footer for XD0310.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo khÃƒÂ´ng hÃ¡Â»Â£p lÃ¡Â»â€¡ cho XD0310.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['VÃ¡ÂºÂ­n tÃ¡ÂºÂ£i nÃ¡Â»â„¢i Ã„â€˜Ã¡Â»â€¹a', 'Ã„ÂiÃ¡Â»Âu phÃ¡Â»â€˜i phÃ†Â°Ã†Â¡ng tiÃ¡Â»â€¡n phÃƒÂ¹ hÃ¡Â»Â£p, theo dÃƒÂµi lÃ¡Â»â„¢ trÃƒÂ¬nh rÃƒÂµ rÃƒÂ ng vÃƒÂ  Ã„â€˜ÃƒÂºng hÃ¡ÂºÂ¹n.', 'photo-1586191582151-f73872dfd183'],
                ['VÃ¡ÂºÂ­n chuyÃ¡Â»Æ’n hÃƒÂ ng khÃƒÂ´ng', 'KÃ¡ÂºÂ¿t nÃ¡Â»â€˜i lÃ¡Â»â€¹ch bay vÃƒÂ  thÃ¡Â»Â§ tÃ¡Â»Â¥c hÃƒÂ ng hÃƒÂ³a vÃ¡Â»â€ºi quy trÃƒÂ¬nh theo dÃƒÂµi minh bÃ¡ÂºÂ¡ch.', 'photo-1436491865332-7a61a109cc05'],
                ['GiÃ¡ÂºÂ£i phÃƒÂ¡p kho bÃƒÂ£i', 'LÃ†Â°u trÃ¡Â»Â¯, kiÃ¡Â»Æ’m soÃƒÂ¡t tÃ¡Â»â€œn kho vÃƒÂ  giao nhÃ¡ÂºÂ­n linh hoÃ¡ÂºÂ¡t theo kÃ¡ÂºÂ¿ hoÃ¡ÂºÂ¡ch vÃ¡ÂºÂ­n hÃƒÂ nh.', 'photo-1586528116493-da8c7e6d8e14'],
                ['VÃ¡ÂºÂ­n tÃ¡ÂºÂ£i Ã„â€˜Ã†Â°Ã¡Â»Âng biÃ¡Â»Æ’n', 'TÃ¡Â»â€˜i Ã†Â°u hÃƒÂ nh trÃƒÂ¬nh container vÃƒÂ  thÃ¡Â»Âi gian giao nhÃ¡ÂºÂ­n xuyÃƒÂªn biÃƒÂªn giÃ¡Â»â€ºi.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0310-logistics-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiÃ¡ÂºÂ¿t', 'link_url' => '#hotline', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Japanese garden', 'A calm garden with stone paths, water features and sculpted greenery.', 'photo-1497250681960-ef046c08a56e'],
                ['Private villa landscape', 'Landscape planning and planting for a comfortable green home.', 'photo-1416879595882-3373a0480b5b'],
                ['Koi pond project', 'A balanced outdoor space built around water, shade and natural materials.', 'photo-1585320806297-9794b3e4eeae'],
                ['Urban garden', 'Compact planting and garden care for city homes and offices.', 'photo-1558904541-efa843a96f01'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd0310-garden-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'View project', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1200), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            foreach ([
                ['CargoLink', 'VÃ¡ÂºÂ­n tÃ¡ÂºÂ£i quÃ¡Â»â€˜c tÃ¡ÂºÂ¿', 'photo-1494412651409-8963ce7935a7'],
                ['NorthStar', 'ChuÃ¡Â»â€”i cung Ã¡Â»Â©ng', 'photo-1586528116493-da8c7e6d8e14'],
                ['Global Freight', 'Giao nhÃ¡ÂºÂ­n hÃƒÂ ng hÃƒÂ³a', 'photo-1586191582151-f73872dfd183'],
                ['BluePort', 'Khai thÃƒÂ¡c cÃ¡ÂºÂ£ng', 'photo-1566576912321-d58ddd7a6088'],
                ['AirWay', 'VÃ¡ÂºÂ­n tÃ¡ÂºÂ£i hÃƒÂ ng khÃƒÂ´ng', 'photo-1436491865332-7a61a109cc05'],
                ['RouteOne', 'VÃ¡ÂºÂ­n tÃ¡ÂºÂ£i nÃ¡Â»â„¢i Ã„â€˜Ã¡Â»â€¹a', 'photo-1586528116311-ad8dd3c8310d'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0310-logistics-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['DÃ¡Â»â€¹ch vÃ¡Â»Â¥ vÃ¡ÂºÂ­n tÃ¡ÂºÂ£i hÃƒÂ ng khÃƒÂ´ng Ã„â€˜Ã†Â°Ã¡Â»Âng biÃ¡Â»Æ’n', 'TÃ¡Â»â€˜i Ã†Â°u lÃ¡Â»â€¹ch trÃƒÂ¬nh, phÃ†Â°Ã†Â¡ng thÃ¡Â»Â©c vÃ¡ÂºÂ­n chuyÃ¡Â»Æ’n vÃƒÂ  chi phÃƒÂ­ cho tÃ¡Â»Â«ng lÃƒÂ´ hÃƒÂ ng.', 'photo-1494412651409-8963ce7935a7'],
                ['Logistics chÃ¡Â»Â§ Ã„â€˜Ã¡Â»â„¢ng cho doanh nghiÃ¡Â»â€¡p', 'KÃ¡ÂºÂ¿t nÃ¡Â»â€˜i kho bÃƒÂ£i, vÃ¡ÂºÂ­n tÃ¡ÂºÂ£i vÃƒÂ  giao nhÃ¡ÂºÂ­n bÃ¡ÂºÂ±ng mÃ¡Â»â„¢t quy trÃƒÂ¬nh rÃƒÂµ rÃƒÂ ng.', 'photo-1586528116493-da8c7e6d8e14'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0310-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'TÃ†Â° vÃ¡ÂºÂ¥n doanh nghiÃ¡Â»â€¡p', 'metadata' => ['kicker' => 'TÃ†Â° vÃ¡ÂºÂ¥n doanh nghiÃ¡Â»â€¡p', 'summary' => $summary, 'button_label' => 'NhÃ¡ÂºÂ­n bÃƒÂ¡o giÃƒÂ¡'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0310 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chÃ¡Â»Â§', 'url' => '#top'], ['label' => 'DÃ¡Â»â€¹ch vÃ¡Â»Â¥', 'url' => '#dich-vu'], ['label' => 'GiÃ¡ÂºÂ£i phÃƒÂ¡p', 'url' => '#giai-phap'], ['label' => 'ThÃ†Â° viÃ¡Â»â€¡n', 'url' => '#thu-vien'], ['label' => 'Ã„ÂÃ¡Â»â€˜i tÃƒÂ¡c', 'url' => '#doi-tac'], ['label' => 'LiÃƒÂªn hÃ¡Â»â€¡', 'url' => '#footer']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Logistics ViÃ¡Â»â€¡t', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Logistics ViÃ¡Â»â€¡t', 'company_description' => 'GiÃ¡ÂºÂ£i phÃƒÂ¡p vÃ¡ÂºÂ­n tÃ¡ÂºÂ£i vÃƒÂ  hÃ¡ÂºÂ­u cÃ¡ÂºÂ§n linh hoÃ¡ÂºÂ¡t, kÃ¡ÂºÂ¿t nÃ¡Â»â€˜i doanh nghiÃ¡Â»â€¡p vÃ¡Â»â€ºi mÃ¡Â»Âi hÃƒÂ nh trÃƒÂ¬nh.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@logisticsviet.vn', 'support_location' => '344 HuÃ¡Â»Â³nh TÃ¡ÂºÂ¥n PhÃƒÂ¡t, QuÃ¡ÂºÂ­n 7, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
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

        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($projectIds = $ids(CmsProject::class)) { CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete(); $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete(); }
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
