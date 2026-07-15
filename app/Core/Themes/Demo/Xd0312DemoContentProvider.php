<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
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

class Xd0312DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0312';
    private const PRESET_KEY = 'xd0312-logistics-bizgrow';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'Bizgrow logistics',
            'description' => 'Default warehouse, transport, team, partner and news content for XD0312.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for XD0312.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Kho bai va luu tru', 'He thong kho bai linh hoat, quan ly ton kho ro rang va bao quan hang hoa an toan.', 'photo-1586528116493-da8c7e6d8e14'],
                ['Van chuyen hang khong quoc te', 'Ket noi hang khong quoc te voi lich trinh chu dong, theo doi don hang minh bach.', 'photo-1436491865332-7a61a109cc05'],
                ['Dich vu Amazon FBA', 'Ho tro giao nhan, dan nhan va xu ly hang hoa cho kenh thuong mai dien tu.', 'photo-1586528116311-ad8dd3c8310d'],
                ['Van chuyen hang le LCL', 'Giai phap gom hang le toi uu chi phi cho tung lo hang cua doanh nghiep.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('xd0312-'.$title),
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
                    'slug' => Str::slug('xd0312-'.$title),
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
                ['Dich vu kho bai va luu tru', 'He thong kho bai an toan, quan ly hang hoa chinh xac va linh hoat theo nhu cau.', 'photo-1586528116493-da8c7e6d8e14'],
                ['Van chuyen ket noi thi truong toan cau', 'Tu van tuyen van chuyen, chung tu va phuong an toi uu chi phi cho tung lo hang.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'xd0312-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image($photo, 1920),
                    'link_url' => '#lien-he',
                    'badge' => 'Bizgrow logistics',
                    'metadata' => ['kicker' => 'Bizgrow logistics', 'summary' => $summary, 'button_label' => 'Kham pha ngay'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            foreach ([
                ['Giai phap kho bai giup doanh nghiep toi uu chi phi', 'Cach to chuc kho bai khoa hoc giup doanh nghiep kiem soat hang hoa va rut ngan thoi gian xu ly don.', 'logistics-cost'],
                ['Xuat sieu can thi truong dich vu logistics', 'Xu huong chuoi cung ung va cac yeu to can chuan bi cho doanh nghiep xuat khau.', 'logistics-export'],
                ['Quan ly don hang minh bach tu kho den diem giao', 'Du lieu van hanh dong bo giup theo doi tien do don hang de dang hon.', 'logistics-order'],
            ] as $index => [$title, $excerpt, $slug]) {
                $post = CmsPost::query()->create([
                    'title' => $title, 'slug' => 'xd0312-'.$slug, 'status' => 'published',
                    'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => $now->copy()->subDays($index), 'is_highlight' => true,
                ]);
                $this->record($post);
            }

            foreach ([
                ['James Cooper', 'Dieu phoi kho van', 'photo-1560250097-0b93528c311a'],
                ['Minh Tran', 'Quan ly van hanh', 'photo-1560250097-0b93528c311a'],
                ['Linh Nguyen', 'Chuyen vien dich vu khach hang', 'photo-1573496359142-b8d87734a5a2'],
            ] as $index => [$name, $role, $photo]) {
                $member = CmsTeamMember::query()->create([
                    'name' => $name, 'slug' => Str::slug('xd0312-'.$name.'-'.$index), 'role' => $role,
                    'summary' => 'Dong hanh cung khach hang trong tung chang duong logistics.', 'status' => 'published',
                    'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index,
                ]);
                CmsTeamMemberImage::query()->create([
                    'cms_team_member_id' => $member->id, 'image_url' => $image($photo, 720), 'alt_text' => $name,
                    'is_featured' => true, 'sort_order' => 0,
                ]);
                $this->record($member);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'XD0312 Main Menu',
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
                'site_name' => 'Bizgrow Logistics',
                'website_type' => 'service',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'Bizgrow',
                    'company_description' => 'Giai phap kho bai, van chuyen va hau can cho doanh nghiep hien dai.',
                    'support_hotline' => '1900 9477',
                    'support_email' => 'hello@bizgrow.local',
                    'support_location' => '344 Huynh Tan Phat, Quan 7, TP.HCM',
                ]),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 4, 'posts' => 3, 'team_members' => 3, 'projects' => 0, 'partners' => 5, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'services' => 0, 'posts' => 0, 'team_members' => 0, 'partners' => 0, 'landing_pages' => 0];

        if ($serviceIds = $ids(CmsService::class)) {
            CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($partnerIds = $ids(CmsPartner::class)) {
            $counts['partners'] = CmsPartner::query()->whereKey($partnerIds)->delete();
        }
        if ($postIds = $ids(CmsPost::class)) {
            $counts['posts'] = CmsPost::query()->whereKey($postIds)->delete();
        }
        if ($teamMemberIds = $ids(CmsTeamMember::class)) {
            CmsTeamMemberImage::query()->whereIn('cms_team_member_id', $teamMemberIds)->delete();
            $counts['team_members'] = CmsTeamMember::query()->whereKey($teamMemberIds)->delete();
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
