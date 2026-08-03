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

class Xd0311DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0311';

    private const PRESET_KEY = 'xd0311-accounting-advisory';

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
            'label' => 'InVess accounting advisory',
            'description' => 'Default banner, accounting services, customer testimonials, news and fixed footer for XD0311.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0311.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Kế toán doanh nghiệp', 'Tổ chức sổ sách, báo cáo tài chính và quy trình kế toán phù hợp với quy mô doanh nghiệp.', 'photo-1454165804606-c3d57bc86b40'],
                ['Tư vấn thuế', 'Cập nhật quy định và xây dựng phương án kê khai thuế minh bạch, đúng hạn.', 'photo-1554224155-6726b3ff858f'],
                ['Tư vấn tài chính', 'Phân tích dòng tiền, chi phí và chỉ số tài chính để hỗ trợ quyết định kinh doanh.', 'photo-1556761175-b413da4baf72'],
                ['Kiểm toán nội bộ', 'Rà soát quy trình, kiểm soát rủi ro và nâng cao độ tin cậy của dữ liệu tài chính.', 'photo-1551836022-d5d88e9218df'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('xd0311-'.$title),
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
                ['Kế toán và thuế vững vàng cho doanh nghiệp', 'Đồng hành từ tổ chức kế toán, kê khai thuế đến quản trị tài chính minh bạch.', 'photo-1454165804606-c3d57bc86b40'],
                ['Quản trị tài chính từ góc nhìn chuyên gia', 'Giải pháp tư vấn thực tế để doanh nghiệp chủ động trước mỗi quyết định.', 'photo-1554224155-6726b3ff858f'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'xd0311-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image($photo, 1920),
                    'link_url' => '#lien-he',
                    'badge' => 'InVess advisory',
                    'metadata' => ['kicker' => 'Tư vấn tài chính chuyên nghiệp', 'summary' => $summary, 'button_label' => 'Nhận tư vấn'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'XD0311 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => '#top'],
                    ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'],
                    ['label' => 'Dịch vụ', 'url' => '#dich-vu'],
                    ['label' => 'Quy trình', 'url' => '#quy-trinh'],
                    ['label' => 'Tin tức', 'url' => '#tin-tuc'],
                    ['label' => 'Liên hệ', 'url' => '#lien-he'],
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
                    'company_description' => 'Dịch vụ kế toán, thuế và tư vấn tài chính minh bạch cho doanh nghiệp.',
                    'support_hotline' => '1900 9477',
                    'support_email' => 'hello@invess.vn',
                    'support_location' => '196 Nguyễn Đình Chiểu, Quận 3, TP. Hồ Chí Minh',
                ]),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', app(SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(SiteContext::class)->websiteKey(), self::THEME_KEY, true);
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
