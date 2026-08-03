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
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Xd0312DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0312';

    private const PRESET_KEY = 'xd0312-logistics-bizgrow';

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
        return [
            'key' => self::PRESET_KEY,
            'label' => 'Bizgrow logistics',
            'description' => 'Default warehouse, transport, team, partner and news content for XD0312.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0312.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Kho bãi và lưu trữ', 'Hệ thống kho linh hoạt, quản lý tồn kho rõ ràng và bảo quản hàng hóa an toàn.', 'photo-1586528116493-da8c7e6d8e14'],
                ['Vận chuyển hàng không quốc tế', 'Kết nối các tuyến hàng không với lịch trình chủ động và trạng thái đơn hàng minh bạch.', 'photo-1436491865332-7a61a109cc05'],
                ['Dịch vụ Amazon FBA', 'Hỗ trợ giao nhận, dán nhãn và xử lý hàng hóa cho kênh thương mại điện tử.', 'photo-1586528116311-ad8dd3c8310d'],
                ['Vận chuyển hàng lẻ LCL', 'Giải pháp gom hàng lẻ tối ưu chi phí cho từng lô hàng của doanh nghiệp.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('xd0312-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'button_label' => 'Tìm hiểu thêm',
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
                ['GlobalPort', 'Kết nối cảng quốc tế', 'photo-1494412651409-8963ce7935a7'],
                ['AirBridge', 'Mạng lưới vận tải hàng không', 'photo-1436491865332-7a61a109cc05'],
                ['NorthStar Cargo', 'Điều phối vận tải đa phương thức', 'photo-1586191582151-f73872dfd183'],
                ['Smart Warehouse', 'Kho bãi và xử lý đơn hàng', 'photo-1586528116493-da8c7e6d8e14'],
                ['RouteOne', 'Giao nhận nội địa', 'photo-1586528116311-ad8dd3c8310d'],
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
                ['Logistics thông minh cho chuỗi cung ứng hiện đại', 'Kết nối kho bãi, vận chuyển và giao nhận bằng một quy trình minh bạch, linh hoạt.', 'photo-1586528116493-da8c7e6d8e14'],
                ['Vận chuyển kết nối thị trường toàn cầu', 'Tư vấn tuyến vận chuyển, chứng từ và phương án tối ưu chi phí cho từng lô hàng.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'xd0312-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image($photo, 1920),
                    'link_url' => '#lien-he',
                    'badge' => 'Bizgrow logistics',
                    'metadata' => ['kicker' => 'Bizgrow Logistics', 'summary' => $summary, 'button_label' => 'Khám phá dịch vụ'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            foreach ([
                ['Giải pháp kho bãi giúp doanh nghiệp tối ưu chi phí', 'Cách tổ chức kho khoa học giúp doanh nghiệp kiểm soát hàng hóa và rút ngắn thời gian xử lý đơn.', 'logistics-cost'],
                ['Xu hướng mới của thị trường dịch vụ logistics', 'Những thay đổi trong chuỗi cung ứng và các yếu tố doanh nghiệp xuất khẩu cần chuẩn bị.', 'logistics-export'],
                ['Quản lý đơn hàng minh bạch từ kho đến điểm giao', 'Dữ liệu vận hành đồng bộ giúp theo dõi tiến độ đơn hàng dễ dàng hơn.', 'logistics-order'],
            ] as $index => [$title, $excerpt, $slug]) {
                $post = CmsPost::query()->create([
                    'title' => $title, 'slug' => 'xd0312-'.$slug, 'status' => 'published',
                    'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => $now->copy()->subDays($index), 'is_highlight' => true,
                ]);
                $this->record($post);
            }

            foreach ([
                ['James Cooper', 'Điều phối kho vận', 'photo-1560250097-0b93528c311a'],
                ['Trần Hoàng Minh', 'Quản lý vận hành', 'photo-1500648767791-00dcc994a43e'],
                ['Nguyễn Khánh Linh', 'Chuyên viên dịch vụ khách hàng', 'photo-1573496359142-b8d87734a5a2'],
            ] as $index => [$name, $role, $photo]) {
                $member = CmsTeamMember::query()->create([
                    'name' => $name, 'slug' => Str::slug('xd0312-'.$name.'-'.$index), 'role' => $role,
                    'summary' => 'Đồng hành cùng khách hàng trong từng chặng đường logistics.', 'status' => 'published',
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
                    ['label' => 'Trang chủ', 'url' => '#top'],
                    ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'],
                    ['label' => 'Dịch vụ', 'url' => '#dich-vu'],
                    ['label' => 'Quy trình', 'url' => '#quy-trinh'],
                    ['label' => 'Tin tức', 'url' => '#tin-tuc'],
                    ['label' => 'Liên hệ', 'url' => '#footer'],
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
                    'company_description' => 'Giải pháp kho bãi, vận chuyển và hậu cần cho doanh nghiệp hiện đại.',
                    'support_hotline' => '1900 9477',
                    'support_email' => 'hello@bizgrow.vn',
                    'support_location' => '344 Huỳnh Tấn Phát, Quận 7, TP. Hồ Chí Minh',
                ]),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', app(SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(SiteContext::class)->websiteKey(), self::THEME_KEY, true);
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
