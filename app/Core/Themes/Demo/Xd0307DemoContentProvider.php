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

class Xd0307DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0307';
    private const PRESET_KEY = 'xd0307-cleaning-services';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'TÆ° váº¥n doanh nghiá»‡p', 'description' => 'Banner, dá»‹ch vá»¥, Ä‘á»‘i tÃ¡c, menu vÃ  landingpage máº«u cho XD0307.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo khÃ´ng há»£p lá»‡ cho XD0307.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Váº­n táº£i ná»™i Ä‘á»‹a', 'Äiá»u phá»‘i phÆ°Æ¡ng tiá»‡n phÃ¹ há»£p, theo dÃµi lá»™ trÃ¬nh rÃµ rÃ ng vÃ  Ä‘Ãºng háº¹n.', 'photo-1586191582151-f73872dfd183'],
                ['Váº­n chuyá»ƒn hÃ ng khÃ´ng', 'Káº¿t ná»‘i lá»‹ch bay vÃ  thá»§ tá»¥c hÃ ng hÃ³a vá»›i quy trÃ¬nh theo dÃµi minh báº¡ch.', 'photo-1436491865332-7a61a109cc05'],
                ['Giáº£i phÃ¡p kho bÃ£i', 'LÆ°u trá»¯, kiá»ƒm soÃ¡t tá»“n kho vÃ  giao nháº­n linh hoáº¡t theo káº¿ hoáº¡ch váº­n hÃ nh.', 'photo-1586528116493-da8c7e6d8e14'],
                ['Váº­n táº£i Ä‘Æ°á»ng biá»ƒn', 'Tá»‘i Æ°u hÃ nh trÃ¬nh container vÃ  thá»i gian giao nháº­n xuyÃªn biÃªn giá»›i.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0307-logistics-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiáº¿t', 'link_url' => '#hotline', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['CargoLink', 'Váº­n táº£i quá»‘c táº¿', 'photo-1494412651409-8963ce7935a7'],
                ['NorthStar', 'Chuá»—i cung á»©ng', 'photo-1586528116493-da8c7e6d8e14'],
                ['Global Freight', 'Giao nháº­n hÃ ng hÃ³a', 'photo-1586191582151-f73872dfd183'],
                ['BluePort', 'Khai thÃ¡c cáº£ng', 'photo-1566576912321-d58ddd7a6088'],
                ['AirWay', 'Váº­n táº£i hÃ ng khÃ´ng', 'photo-1436491865332-7a61a109cc05'],
                ['RouteOne', 'Váº­n táº£i ná»™i Ä‘á»‹a', 'photo-1586528116311-ad8dd3c8310d'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0307-logistics-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Dá»‹ch vá»¥ váº­n táº£i hÃ ng khÃ´ng Ä‘Æ°á»ng biá»ƒn', 'Tá»‘i Æ°u lá»‹ch trÃ¬nh, phÆ°Æ¡ng thá»©c váº­n chuyá»ƒn vÃ  chi phÃ­ cho tá»«ng lÃ´ hÃ ng.', 'photo-1494412651409-8963ce7935a7'],
                ['Logistics chá»§ Ä‘á»™ng cho doanh nghiá»‡p', 'Káº¿t ná»‘i kho bÃ£i, váº­n táº£i vÃ  giao nháº­n báº±ng má»™t quy trÃ¬nh rÃµ rÃ ng.', 'photo-1586528116493-da8c7e6d8e14'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0307-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'TÆ° váº¥n doanh nghiá»‡p', 'metadata' => ['kicker' => 'TÆ° váº¥n doanh nghiá»‡p', 'summary' => $summary, 'button_label' => 'Nháº­n bÃ¡o giÃ¡'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0307 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chá»§', 'url' => '#top'], ['label' => 'Dá»‹ch vá»¥', 'url' => '#dich-vu'], ['label' => 'Giáº£i phÃ¡p', 'url' => '#giai-phap'], ['label' => 'ThÆ° viá»‡n', 'url' => '#thu-vien'], ['label' => 'Äá»‘i tÃ¡c', 'url' => '#doi-tac'], ['label' => 'LiÃªn há»‡', 'url' => '#footer']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Logistics Viá»‡t', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Logistics Viá»‡t', 'company_description' => 'Giáº£i phÃ¡p váº­n táº£i vÃ  háº­u cáº§n linh hoáº¡t, káº¿t ná»‘i doanh nghiá»‡p vá»›i má»i hÃ nh trÃ¬nh.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@logisticsviet.vn', 'support_location' => '344 Huá»³nh Táº¥n PhÃ¡t, Quáº­n 7, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
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
