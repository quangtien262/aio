<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
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
use InvalidArgumentException;

class Ser102DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SER102';

    private const PRESET_KEY = 'ser102-auto-detailing';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {
    }

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
            'label' => 'SER102 Auto Detailing',
            'description' => 'Trang chủ chăm sóc xe cao cấp với dịch vụ, quy trình, bảng giá, sản phẩm, bài viết và đặt lịch.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho SER102.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();

            foreach ([
                ['Chăm sóc xe chuẩn chuyên gia', 'Bảo vệ toàn diện, hoàn thiện từng chi tiết.', '/theme-previews/SER102/cover-ser102.png', '#dich-vu'],
                ['Tỉ mỉ trong từng chuyển động', 'Đội ngũ chuyên nghiệp và quy trình kiểm soát chất lượng nghiêm ngặt.', '/theme-previews/SER102/appointment.png', '#bang-gia'],
            ] as $index => [$title, $summary, $image, $link]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ser102-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => $link,
                    'badge' => 'SER102 Auto Detailing',
                    'metadata' => [
                        'eyebrow' => 'SER102 Auto Detailing',
                        'summary' => $summary,
                        'button_label' => $index === 0 ? 'Khám phá ngay' : 'Đặt lịch ngay',
                    ],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'SER102 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => '#trang-chu'],
                    ['label' => 'Dịch vụ', 'url' => '#dich-vu'],
                    ['label' => 'Quy trình', 'url' => '#quy-trinh'],
                    ['label' => 'Bảng giá', 'url' => '#bang-gia'],
                    ['label' => 'Sản phẩm', 'url' => '#san-pham'],
                    ['label' => 'Kiến thức', 'url' => '#tin-tuc'],
                    ['label' => 'Liên hệ', 'url' => '#lien-he'],
                ],
            ]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill([
                'site_name' => $profile->site_name ?: 'SER102 Auto Detailing',
                'website_type' => 'service',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'SER102 Auto Detailing',
                    'company_description' => 'Trung tâm chăm sóc xe chuyên nghiệp và tận tâm.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'hello@ser102.vn',
                    'support_location' => 'TP. Hồ Chí Minh',
                ]),
            ])->save();

            $existingPage = LandingPage::query()
                ->where('website_key', $websiteKey)
                ->where('theme_key', self::THEME_KEY)
                ->where('is_home', true)
                ->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page !== null && $existingPage === null) {
                $this->record($page);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'banners' => 2,
                    'menus' => 1,
                    'landing_pages' => $existingPage === null && $page !== null ? 1 : 0,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()
            ->where('theme_key', self::THEME_KEY)
            ->where('preset_key', self::PRESET_KEY)
            ->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'landing_pages' => 0];

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
        ThemeDemoRecord::query()->create([
            'theme_key' => self::THEME_KEY,
            'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ]);
    }
}
