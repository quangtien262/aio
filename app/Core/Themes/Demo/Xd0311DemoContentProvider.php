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

class Xd0311DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0311';
    private const PRESET_KEY = 'xd0311-accounting-advisory';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'InVess accounting advisory',
            'description' => 'Default banner, accounting services, customer testimonials, news and fixed footer for XD0311.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for XD0311.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Ke toan doanh nghiep', 'To chuc so sach, bao cao tai chinh va quy trinh ke toan phu hop voi quy mo doanh nghiep.', 'photo-1454165804606-c3d57bc86b40'],
                ['Tu van thue', 'Cap nhat quy dinh va xay dung phuong an ke khai thue minh bach, dung han.', 'photo-1554224155-6726b3ff858f'],
                ['Tu van tai chinh', 'Phan tich dong tien, chi phi va chi so tai chinh de ho tro quyet dinh kinh doanh.', 'photo-1556761175-b413da4baf72'],
                ['Kiem toan noi bo', 'Ra soat quy trinh, kiem soat rui ro va nang cao do tin cay cua du lieu tai chinh.', 'photo-1551836022-d5d88e9218df'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('xd0311-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'button_label' => 'Tim hieu them',
                    'link_url' => '#lien-he',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => $now,
                ]);
                CmsServiceImage::query()->create([
                    'cms_service_id' => $service->id,
                    'image_url' => $image($photo, 900),
                    'alt_text' => $title,
                    'is_featured' => true,
                    'sort_order' => 0,
                ]);
                $this->record($service);
            }

            foreach ([
                ['TrustLedger', 'Nền tảng báo cáo minh bạch', 'photo-1450101499163-c8848c66ca85'],
                ['Finwise', 'Giải pháp quản trị tài chính', 'photo-1554224154-26032ffc0d07'],
                ['Audit Pro', 'Đồng hành cùng doanh nghiệp', 'photo-1454165804606-c3d57bc86b40'],
                ['Taxlink', 'Tư vấn thuế chuyên sâu', 'photo-1551836022-d5d88e9218df'],
                ['Smart Ledger', 'Sổ sách thông minh', 'photo-1556761175-b413da4baf72'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('xd0311-'.$title),
                    'description' => $description,
                    'image_url' => $image($photo, 420),
                    'image_alt' => $title,
                    'link_url' => '#top',
                    'status' => 'published',
                    'publish_at' => $now,
                    'is_featured' => true,
                    'sort_order' => $index,
                ]);
                $this->record($partner);
            }

            foreach ([
                ['Dich vu ke toan - thue cho doanh nghiep', 'Dong hanh cung doanh nghiep tu ke toan, ke khai thue den quan tri tai chinh minh bach.', 'photo-1454165804606-c3d57bc86b40'],
                ['Quan tri tai chinh tu goc nhin chuyen gia', 'Giai phap tu van thuc te de doanh nghiep chu dong truoc moi quyet dinh.', 'photo-1554224155-6726b3ff858f'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'xd0311-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image($photo, 1920),
                    'link_url' => '#lien-he',
                    'badge' => 'InVess advisory',
                    'metadata' => ['kicker' => 'InVess advisory', 'summary' => $summary, 'button_label' => 'Tim hieu them'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'XD0311 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chu', 'url' => '#top'],
                    ['label' => 'Gioi thieu', 'url' => '#gioi-thieu'],
                    ['label' => 'Dich vu', 'url' => '#dich-vu'],
                    ['label' => 'Quy trinh', 'url' => '#quy-trinh'],
                    ['label' => 'Tin tuc', 'url' => '#tin-tuc'],
                    ['label' => 'Lien he', 'url' => '#lien-he'],
                ],
            ]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => 'InVess Advisory',
                'website_type' => 'service',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'InVess',
                    'company_description' => 'Dich vu ke toan, thue va tu van tai chinh minh bach cho doanh nghiep.',
                    'support_hotline' => '1900 9477',
                    'support_email' => 'hello@invess.local',
                    'support_location' => '196 Nguyen Dinh Chieu, Quan 3, TP.HCM',
                ]),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 4, 'projects' => 0, 'partners' => 5, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
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
