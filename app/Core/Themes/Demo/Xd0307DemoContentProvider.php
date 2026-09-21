<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
use App\Models\CmsPost;
use App\Models\CmsCategory;
use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
use App\Models\CmsTestimonial;
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
            $home = route('site.home', [], false);
            $image = fn (string $id, int $width = 1200): string => '/theme-demo/xd0307/'.match ($id) {
                'photo-1581578731548-c64695cc6952' => $width >= 1900 ? 'hero-pressure-washing.webp' : 'service-hourly.webp',
                'photo-1527515637462-cff94eecc1ac' => $width >= 1900 ? 'clean-home.webp' : 'service-deep-clean.webp',
                'photo-1528740561666-dc2479dc08ab' => 'gallery-kitchen-work.webp',
                'photo-1558618666-fcd25c85cd64' => 'service-upholstery.webp',
                'photo-1497366754035-f200968a6e72' => 'service-deep-clean.webp',
                'photo-1505693416388-ac5ce068fe85' => 'clean-home.webp',
                'photo-1441986300917-64674bd600d8' => 'gallery-kitchen.webp',
                'photo-1519494026892-80bbd2d6fd0d' => 'service-housekeeper.webp',
                'photo-1509062522246-3755977927d7' => 'gallery-team.webp',
                default => 'mission-deck.webp',
            };

            foreach ([
                ['Vệ sinh nhà ở định kỳ', 'Lịch làm sạch linh hoạt, quy trình rõ ràng và hóa chất an toàn cho gia đình.', 'photo-1581578731548-c64695cc6952'],
                ['Vệ sinh văn phòng', 'Giữ không gian làm việc sạch thoáng mà không ảnh hưởng hoạt động doanh nghiệp.', 'photo-1527515637462-cff94eecc1ac'],
                ['Vệ sinh sau xây dựng', 'Làm sạch bụi mịn, sơn và vật liệu còn lại trước khi bàn giao công trình.', 'photo-1528740561666-dc2479dc08ab'],
                ['Giặt sofa và thảm', 'Thiết bị chuyên dụng giúp làm sạch sâu, khử mùi và bảo vệ bề mặt nội thất.', 'photo-1558618666-fcd25c85cd64'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0307-cleaning-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<h2>Phạm vi dịch vụ</h2><p>'.$summary.'</p><h2>Quy trình thực hiện</h2><p>Tiếp nhận nhu cầu, khảo sát hiện trạng và thống nhất phạm vi công việc trước khi thực hiện. Sau khi hoàn tất, đội ngũ kiểm tra và bàn giao cùng khách hàng.</p><h2>Chuẩn bị trước buổi vệ sinh</h2><p>Thông báo diện tích, loại bề mặt, thời gian mong muốn và các đồ vật cần lưu ý để được tư vấn phù hợp.</p>', 'button_label' => 'Xem dịch vụ', 'link_url' => route('site.services.show', ['slug' => Str::slug('xd0307-cleaning-'.$title)], false), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
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

            $postCategory = CmsCategory::query()->create(['name' => 'Cẩm nang vệ sinh', 'slug' => 'xd0307-cam-nang-ve-sinh']);
            $this->record($postCategory);
            foreach ([
                ['Lên lịch vệ sinh nhà ở theo từng khu vực', 'Phân chia công việc hằng ngày và định kỳ giúp duy trì không gian sạch sẽ.', 'Ưu tiên bếp, phòng tắm và các bề mặt thường xuyên tiếp xúc. Kiểm tra hướng dẫn của nhà sản xuất trước khi chọn sản phẩm làm sạch.'],
                ['Chuẩn bị văn phòng trước buổi tổng vệ sinh', 'Sắp xếp tài liệu và thiết bị để buổi vệ sinh diễn ra thuận tiện.', 'Cất tài liệu quan trọng, đánh dấu khu vực cần lưu ý và thống nhất lịch làm việc để hạn chế ảnh hưởng hoạt động văn phòng.'],
                ['Những lưu ý khi vệ sinh sofa và thảm', 'Chọn cách làm sạch phù hợp với chất liệu và hướng dẫn chăm sóc.', 'Kiểm tra nhãn chăm sóc, thử trên vùng nhỏ khuất tầm nhìn và để bề mặt khô hoàn toàn trước khi sử dụng.'],
            ] as $index => [$title, $excerpt, $body]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('xd0307-'.$title), 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p><p>'.$body.'</p>', 'status' => 'published', 'publish_at' => $now->copy()->subDays($index), 'is_highlight' => true]);
                $this->record($post);
            }
            foreach ([['Nguyễn Minh Anh', 'Điều phối dịch vụ'], ['Trần Hải Nam', 'Giám sát chất lượng'], ['Lê Thu Hà', 'Tư vấn khách hàng']] as $index => [$name, $role]) {
                $member = CmsTeamMember::query()->create(['name' => $name, 'slug' => Str::slug('xd0307-'.$name), 'role' => $role, 'summary' => 'Hồ sơ minh họa cho đội ngũ Klean Services.', 'bio' => '<p>Phối hợp khảo sát, sắp xếp lịch và hỗ trợ khách hàng trong quá trình thực hiện dịch vụ.</p>', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($member);
                $memberImage = CmsTeamMemberImage::query()->create(['cms_team_member_id' => $member->id, 'image_url' => '/theme-demo/xd0307/'.['gallery-team.webp', 'reasons-team.webp', 'gallery-cleaner.webp'][$index], 'alt_text' => 'Ảnh minh họa đội ngũ vệ sinh', 'is_featured' => true, 'sort_order' => 0]);
                $this->record($memberImage);
                $testimonial = CmsTestimonial::query()->create(['name' => ['Gia đình Minh', 'Văn phòng An Bình', 'Chị Lan'][$index], 'role' => 'Đánh giá minh họa', 'quote' => ['Lịch hẹn rõ ràng, các khu vực cần làm sạch được trao đổi trước khi bắt đầu.', 'Đội ngũ phối hợp lịch làm việc phù hợp với hoạt động văn phòng.', 'Nhân viên hướng dẫn cách chăm sóc bề mặt sau khi vệ sinh.'][$index], 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }
            $menu = CmsMenu::query()->create(['name' => 'XD0307 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'link_type' => 'home', 'url' => $home], ['label' => 'Dịch vụ', 'url' => $home.'#dich-vu'], ['label' => 'Về Klean', 'url' => $home.'#gioi-thieu'], ['label' => 'Lợi ích', 'url' => $home.'#loi-ich'], ['label' => 'Đội ngũ', 'url' => $home.'#doi-ngu'], ['label' => 'Tin tức', 'link_type' => 'post-category', 'link_value' => (string) $postCategory->id, 'url' => route('site.blog.category', ['slug' => $postCategory->slug], false)], ['label' => 'Liên hệ', 'url' => $home.'#lien-he']]]);
            $this->record($menu);

            $extraCounts = app(Xd0307DemoCatalog::class)->seed($postCategory);
            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Klean Services', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Klean Services', 'company_description' => 'Dịch vụ làm sạch tận tâm, an toàn và linh hoạt cho nhà ở, văn phòng.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@klean.vn', 'support_location' => 'Hà Nội và TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => array_merge($extraCounts, ['services' => 4, 'partners' => 6, 'banners' => 2, 'menus' => 1, 'posts' => 3, 'post_categories' => 1, 'team_members' => 3, 'team_images' => 3, 'testimonials' => 3, 'landing_pages' => $existingPage === null && $page ? 1 : 0]), 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $extraDeleted = app(Xd0307DemoCatalog::class)->delete();
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'services' => 0, 'partners' => 0, 'landing_pages' => 0];
        foreach ([[CmsTeamMemberImage::class, 'team_images'], [CmsTeamMember::class, 'team_members'], [CmsTestimonial::class, 'testimonials'], [CmsPost::class, 'posts'], [CmsCategory::class, 'post_categories']] as [$model, $key]) {
            $counts[$key] = ($modelIds = $ids($model)) ? $model::query()->whereKey($modelIds)->delete() : 0;
        }

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

        return array_merge($counts, $extraDeleted);
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
