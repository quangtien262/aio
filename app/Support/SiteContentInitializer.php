<?php

namespace App\Support;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
use App\Models\CmsTestimonial;
use App\Models\Site;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteContentInitializer
{
    public const MODE_BLANK = 'blank';
    public const MODE_SAMPLE = 'sample';
    public const MODE_COPY_MAIN = 'copy_main';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly ThemeDemoContentGenerator $demoContentGenerator,
        private readonly SiteContentCopier $contentCopier,
        private readonly SiteContext $siteContext,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function initialize(Site $site, string $mode, ?string $presetKey = null): array
    {
        $mode = $this->normalizeMode($mode);

        if ($mode === self::MODE_BLANK) {
            return ['mode' => $mode, 'counts' => []];
        }

        return $this->runForSite($site, function () use ($site, $mode, $presetKey): array {
            if ($mode === self::MODE_COPY_MAIN) {
                $counts = $this->contentCopier->copy(SiteContext::DEFAULT_WEBSITE_KEY, $site->website_key);
                $this->landingPageBuilder->resolveHome($site->website_key, $site->theme_key, true);

                return ['mode' => $mode, 'counts' => $counts];
            }

            $preset = $presetKey ?: $this->demoContentGenerator->defaultPresetForTheme($site->theme_key);

            if ($preset !== null) {
                $result = $this->demoContentGenerator->generate($site->theme_key, $preset);
                $this->landingPageBuilder->resolveHome($site->website_key, $site->theme_key, true);

                return ['mode' => $mode, 'preset' => $preset, 'counts' => (array) ($result['counts'] ?? [])];
            }

            return ['mode' => $mode, 'counts' => $this->seedFallbackContent($site)];
        });
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, [self::MODE_BLANK, self::MODE_SAMPLE, self::MODE_COPY_MAIN], true)
            ? $mode
            : self::MODE_BLANK;
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    private function runForSite(Site $site, callable $callback): mixed
    {
        $previousSite = $this->siteContext->site();
        $previousWebsiteKey = $this->siteContext->websiteKey();

        $this->siteContext->set($site, $site->website_key);

        try {
            return $callback();
        } finally {
            $this->siteContext->set($previousSite, $previousWebsiteKey);
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedFallbackContent(Site $site): array
    {
        return DB::transaction(function () use ($site): array {
            $now = now();
            $themeKey = strtoupper($site->theme_key);
            $companyName = $site->name ?: ('Demo '.$themeKey);
            $image = fn (string $seed, int $width = 1200, int $height = 720): string => "https://picsum.photos/seed/{$site->website_key}-{$seed}/{$width}/{$height}";

            $profile = SiteProfile::query()->firstOrNew(['website_key' => $site->website_key]);
            $branding = array_merge((array) $profile->branding, [
                'website_key' => $site->website_key,
                'company_name' => $companyName,
                'company_description' => 'Bo du lieu mau de kiem thu giao dien '.$themeKey.'.',
                'support_hotline' => '1900 9477',
                'support_email' => 'demo@example.test',
                'support_location' => '196 Nguyen Dinh Chieu, Quan 3, TP.HCM',
            ]);

            $profile->forceFill([
                'website_key' => $site->website_key,
                'site_name' => $companyName,
                'website_type' => $profile->website_type ?: 'service',
                'active_theme_key' => $themeKey,
                'branding' => $branding,
            ])->save();

            $newsCategory = CmsCategory::query()->updateOrCreate([
                'slug' => 'tin-tuc',
            ], [
                'name' => 'Tin tuc',
                'description' => 'Cac bai viet mau cho website demo.',
                'sort_order' => 0,
            ]);

            $serviceCategory = CmsServiceCategory::query()->updateOrCreate([
                'slug' => 'dich-vu-noi-bat',
            ], [
                'name' => 'Dich vu noi bat',
                'description' => 'Nhom dich vu mau cho website demo.',
                'sort_order' => 0,
            ]);

            $projectCategory = CmsProjectCategory::query()->updateOrCreate([
                'slug' => 'du-an-tieu-bieu',
            ], [
                'name' => 'Du an tieu bieu',
                'description' => 'Nhom du an mau cho website demo.',
                'sort_order' => 0,
            ]);

            foreach ([
                ['Gioi thieu', 'gioi-thieu', 'Cau chuyen thuong hieu va nang luc trien khai cua chung toi.'],
                ['Lien he', 'lien-he', 'Thong tin lien he va form yeu cau tu van.'],
            ] as [$title, $slug, $excerpt]) {
                CmsPage::query()->updateOrCreate([
                    'slug' => $slug,
                ], [
                    'title' => $title,
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p>',
                    'meta_title' => $title.' | '.$companyName,
                    'meta_description' => $excerpt,
                    'template' => 'default',
                    'publish_at' => $now,
                ]);
            }

            foreach ([
                ['Giai phap tu van nhanh cho khach hang moi', 'Kich ban mau giup kiem thu card tin tuc va khu vuc blog.'],
                ['Cach chuan bi thong tin truoc khi gui yeu cau', 'Noi dung mau cho bai viet huong dan va CTA.'],
                ['Nhung diem can luu y khi chon don vi dich vu', 'Bai viet mau de kiem thu danh sach tin lien quan.'],
            ] as $index => [$title, $excerpt]) {
                CmsPost::query()->updateOrCreate([
                    'slug' => Str::slug($title),
                ], [
                    'category_id' => $newsCategory->id,
                    'title' => $title,
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p>',
                    'publish_at' => $now->copy()->subDays($index),
                    'is_highlight' => $index === 0,
                ]);
            }

            foreach ([
                ['Tu van tron goi', 'Dong hanh tu khau tiep nhan yeu cau den khi hoan tat.'],
                ['Xu ly ho so nhanh', 'Toi uu quy trinh de tiet kiem thoi gian cho khach hang.'],
                ['Cham soc sau dich vu', 'Theo doi phan hoi va ho tro cac nhu cau phat sinh.'],
            ] as $index => [$title, $summary]) {
                $service = CmsService::query()->updateOrCreate([
                    'slug' => Str::slug($title),
                ], [
                    'cms_service_category_id' => $serviceCategory->id,
                    'title' => $title,
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'button_label' => 'Xem them',
                    'link_url' => '#lien-he',
                    'publish_at' => $now,
                    'is_featured' => true,
                    'is_highlight' => $index === 0,
                    'sort_order' => $index,
                ]);

                CmsServiceImage::query()->updateOrCreate([
                    'cms_service_id' => $service->id,
                    'sort_order' => 0,
                ], [
                    'image_url' => $image('service-'.$index, 900, 600),
                    'alt_text' => $title,
                    'is_featured' => true,
                ]);
            }

            foreach ([
                ['Du an demo so 1', 'Mau noi dung du an de kiem thu block gallery.'],
                ['Du an demo so 2', 'Mau noi dung du an de kiem thu danh sach project.'],
            ] as $index => [$title, $summary]) {
                $project = CmsProject::query()->updateOrCreate([
                    'slug' => Str::slug($title),
                ], [
                    'cms_project_category_id' => $projectCategory->id,
                    'title' => $title,
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'button_label' => 'Xem chi tiet',
                    'link_url' => '#',
                    'publish_at' => $now,
                    'is_featured' => true,
                    'is_highlight' => $index === 0,
                    'sort_order' => $index,
                ]);

                CmsProjectImage::query()->updateOrCreate([
                    'cms_project_id' => $project->id,
                    'sort_order' => 0,
                ], [
                    'image_url' => $image('project-'.$index, 900, 600),
                    'alt_text' => $title,
                    'is_featured' => true,
                ]);
            }

            foreach ([
                ['Nguyen Minh', 'Giam doc du an'],
                ['Tran Linh', 'Chuyen vien tu van'],
                ['Le Hoang', 'Quan ly van hanh'],
            ] as $index => [$name, $role]) {
                $member = CmsTeamMember::query()->updateOrCreate([
                    'slug' => Str::slug($name.'-'.$index),
                ], [
                    'name' => $name,
                    'role' => $role,
                    'summary' => 'Thanh vien mau trong doi ngu '.$companyName.'.',
                    'status' => 'published',
                    'publish_at' => $now,
                    'is_featured' => true,
                    'sort_order' => $index,
                ]);

                CmsTeamMemberImage::query()->updateOrCreate([
                    'cms_team_member_id' => $member->id,
                    'sort_order' => 0,
                ], [
                    'image_url' => $image('team-'.$index, 640, 760),
                    'alt_text' => $name,
                    'is_featured' => true,
                ]);
            }

            foreach ([
                ['Pham Anh', 'CEO cong ty ABC', 'Dich vu nhanh, ro rang va rat de lam viec.'],
                ['Minh Quan', 'Quan ly marketing', 'Bo giao dien va du lieu demo giup chung toi review nhanh hon.'],
            ] as $index => [$name, $role, $quote]) {
                CmsTestimonial::query()->updateOrCreate([
                    'name' => $name,
                    'role' => $role,
                ], [
                    'company' => $companyName,
                    'quote' => $quote,
                    'image_url' => $image('testimonial-'.$index, 400, 400),
                    'status' => 'published',
                    'publish_at' => $now,
                    'is_featured' => true,
                    'sort_order' => $index,
                ]);
            }

            CmsMenu::query()->updateOrCreate([
                'location' => 'primary-navigation',
                'name' => $themeKey.' Main Menu',
            ], [
                'items' => [
                    ['label' => 'Trang chu', 'url' => '#top'],
                    ['label' => 'Gioi thieu', 'url' => '#gioi-thieu'],
                    ['label' => 'Dich vu', 'url' => '#dich-vu'],
                    ['label' => 'Tin tuc', 'url' => '#tin-tuc'],
                    ['label' => 'Lien he', 'url' => '#lien-he'],
                ],
            ]);

            SiteBanner::query()->updateOrCreate([
                'theme_key' => $themeKey,
                'placement' => strtolower($themeKey).'-hero',
                'sort_order' => 0,
            ], [
                'title' => $companyName,
                'subtitle' => 'Du lieu mau duoc tao rieng cho '.$site->website_key.'.',
                'image_url' => $image('hero', 1920, 900),
                'link_url' => '#lien-he',
                'badge' => 'Demo '.$themeKey,
                'metadata' => ['button_label' => 'Lien he ngay'],
                'is_active' => true,
            ]);

            $landingPage = $this->landingPageBuilder->resolveHome($site->website_key, $themeKey, true);

            return [
                'pages' => 2,
                'post_categories' => 1,
                'posts' => 3,
                'service_categories' => 1,
                'services' => 3,
                'project_categories' => 1,
                'projects' => 2,
                'team_members' => 3,
                'testimonials' => 2,
                'menus' => 1,
                'banners' => 1,
                'landing_pages' => $landingPage ? 1 : 0,
            ];
        });
    }
}
