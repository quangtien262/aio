<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
use App\Models\CmsTestimonial;
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

class Xd0322DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0322';
    private const PRESET_KEY = 'xd0322-construction';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }

    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'XD0322 Construction', 'description' => 'Construction, interior, project, product and customer-story content designed for XD0322.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for XD0322.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Thiết kế nội thất', 'Tư vấn bố cục, vật liệu và giải pháp nội thất đồng bộ cho không gian sống.', 'fa-solid fa-couch', 'photo-1618221195710-dd6b41faaea6'],
                ['Thi công nhà phố', 'Thi công trọn gói nhà phố theo quy trình rõ ràng, kiểm soát chất lượng từng giai đoạn.', 'fa-solid fa-compass-drafting', 'photo-1487958449943-2429e8be8625'],
                ['Thi công nhà hàng và café', 'Phát triển không gian kinh doanh có bản sắc, tối ưu vận hành và trải nghiệm khách hàng.', 'fa-solid fa-store', 'photo-1514933651103-005eec06c04b'],
                ['Thi công căn hộ', 'Hoàn thiện căn hộ hiện đại, đồng bộ từ ý tưởng đến nội thất và bàn giao.', 'fa-solid fa-building', 'photo-1600585154340-be6161a56a0c'],
                ['Thi công biệt thự', 'Giải pháp thiết kế và thi công biệt thự chú trọng cá tính và chất lượng bền vững.', 'fa-solid fa-house', 'photo-1600607687939-ce8a6c25118c'],
                ['Thi công văn phòng', 'Không gian làm việc linh hoạt, nâng cao hiệu suất và nhận diện thương hiệu.', 'fa-solid fa-briefcase', 'photo-1497366754035-f200968a6e72'],
            ] as $index => [$title, $summary, $icon, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'icon' => $icon, 'button_label' => 'Xem chi tiết', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Căn hộ cao cấp phong cách contemporary', 'Căn hộ 3 phòng ngủ với nội thất gỗ ấm, ánh sáng tự nhiên và giải pháp lưu trữ thông minh.', 'photo-1600210492486-724fe5c67fb0'],
                ['Biệt thự vườn hiện đại', 'Không gian mở kết nối phòng khách, bếp và khu vườn cho gia đình nhiều thế hệ.', 'photo-1600585152915-d208bec867a1'],
                ['Nhà phố 4 tầng tại Thủ Đức', 'Nhà phố tối ưu thông gió và ánh sáng, hoàn thiện đồng bộ mặt tiền và nội thất.', 'photo-1600566753086-00f18fb6b3ea'],
                ['Văn phòng sáng tạo cho doanh nghiệp công nghệ', 'Văn phòng hybrid với khu họp, khu tập trung và các điểm kết nối cộng đồng.', 'photo-1497366216548-37526070297c'],
                ['Showroom nội thất thương hiệu Việt', 'Không gian trưng bày có nhiều lớp trải nghiệm để tôn vinh vật liệu và sản phẩm.', 'photo-1494438639946-1ebd1d20bf85'],
                ['Nhà hàng urban contemporary', 'Nhà hàng với vật liệu mộc, hệ đèn ấm và luồng di chuyển tối ưu cho giờ cao điểm.', 'photo-1517248135467-4c7edcad34c4'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem dự án', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            $category = CatalogCategory::query()->create(['name' => 'Nội thất và vật liệu XD0322', 'slug' => 'xd0322-noi-that-va-vat-lieu', 'description' => 'Nội thất hoàn thiện cho nhà ở, văn phòng và không gian thương mại.', 'image_url' => $image('photo-1618220179428-22790b461013', 700), 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);
            foreach ([
                ['Bàn làm việc gỗ óc chó', 3250000, 'photo-1518455027359-f3f8164ba6bd'], ['Tủ kệ tivi gỗ sồi', 4800000, 'photo-1616486338812-3dadae4b4ace'], ['Tủ giày cánh lùa', 3650000, 'photo-1618221195710-dd6b41faaea6'], ['Kệ sách module', 2900000, 'photo-1594620302200-9a762244a156'], ['Ghế đơn phòng khách', 1450000, 'photo-1555041469-a586c61ea9bc'], ['Tủ đầu giường tối giản', 2100000, 'photo-1616486338812-3dadae4b4ace'], ['Ghế văn phòng ergonomic', 2750000, 'photo-1505843490701-5b4b83a4d523'], ['Sofa vải trung tính', 8900000, 'photo-1555041469-a586c61ea9bc'],
            ] as $index => [$name, $price, $photo]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('xd0322-'.$name), 'sku' => 'X322-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $index === 7 ? 9800000 : null, 'stock' => 20, 'short_description' => 'Sản phẩm nội thất demo phù hợp cho XD0322 Construction.', 'detail_content' => '<p>Sản phẩm hoàn thiện cho không gian hiện đại.</p>', 'image_url' => $image($photo, 800), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Tối ưu ánh sáng tự nhiên trong căn hộ hiện đại', 'Những nguyên tắc đơn giản để không gian sống thoáng, tiết kiệm năng lượng và bền vững.', 'photo-1600210492486-724fe5c67fb0'],
                ['Cách chọn vật liệu hoàn thiện bền đẹp theo thời gian', 'Cân bằng thẩm mỹ, độ bền và chi phí để mỗi quyết định thiết kế dễ dàng hơn.', 'photo-1618220179428-22790b461013'],
                ['Quy trình thi công trọn gói giúp kiểm soát tiến độ', 'Từ khảo sát đến bàn giao, các mốc công việc cần minh bạch để giữ đúng cam kết.', 'photo-1503387762-592deb58ef4e'],
                ['Xu hướng không gian làm việc linh hoạt năm nay', 'Văn phòng cần hỗ trợ tập trung, hợp tác và phục hồi năng lượng cho đội ngũ.', 'photo-1497366754035-f200968a6e72'],
            ] as $index => [$title, $excerpt, $photo]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_url' => $image($photo, 1000), 'mime_type' => 'image/jpeg', 'size' => 0, 'alt_text' => $title]);
                $post = CmsPost::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'meta_title' => $title, 'meta_description' => $excerpt, 'featured_media_id' => $media->id, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($media);
                $this->record($post);
            }

            foreach ([
                ['Nguyễn Văn Sơn', 'Tổng giám đốc', 'Điều hành', 'Kinh nghiệm trong quản lý chất lượng và điều phối các dự án xây dựng.', 'photo-1500648767791-00dcc994a43e'],
                ['Nguyễn Quang Anh', 'Giám sát thi công', 'Thi công', 'Theo sát công trường, tiến độ và tiêu chuẩn an toàn cho từng hạng mục.', 'photo-1506794778202-cad84cf45f1d'],
                ['Nguyễn Thị Thắm', 'Trưởng phòng kế toán', 'Vận hành', 'Quản lý ngân sách minh bạch và hỗ trợ vận hành dự án hiệu quả.', 'photo-1494790108377-be9c29b29330'],
                ['Nguyễn Thu Trang', 'Giám đốc truyền thông', 'Sáng tạo', 'Kết nối ý tưởng thiết kế với câu chuyện thương hiệu của khách hàng.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $department, $summary, $photo]) {
                $member = CmsTeamMember::query()->create(['name' => $name, 'slug' => Str::slug('xd0322-'.$name), 'role' => $role, 'department' => $department, 'summary' => $summary, 'bio' => $summary, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                CmsTeamMemberImage::query()->create(['cms_team_member_id' => $member->id, 'image_url' => $image($photo, 700), 'alt_text' => $name, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($member);
            }

            foreach ([
                ['Gia Bảo Residence', 'Chủ đầu tư', 'ND Construction hoàn thiện công trình đúng tiến độ, chi tiết nội thất chỉn chu và đội ngũ phối hợp rất chuyên nghiệp.', 'photo-1507003211169-0a1dd7228f2d'],
                ['Minh Anh Studio', 'Founder', 'Tư vấn rõ ràng từ ngày đầu, phương án vật liệu hợp lý và chất lượng thi công vượt kỳ vọng của chúng tôi.', 'photo-1544005313-94ddf0286df2'],
                ['Lumi Office', 'Operations Director', 'Dự án văn phòng đã vận hành ổn định sau bàn giao. Không gian đẹp và phù hợp với cách đội ngũ làm việc.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'company' => 'Khách hàng ND Construction', 'quote' => $quote, 'image_url' => $image($photo, 500), 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            foreach (['ARC Studio', 'UrbanBuild', 'NOVA Materials', 'Prime Lighting', 'Form Works', 'Green Habitat'] as $index => $title) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'description' => 'Đối tác đồng hành cùng ND Construction.', 'image_url' => 'https://placehold.co/260x120/ffffff/071638?text='.urlencode($title), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Cung cấp giải pháp xây dựng tốt nhất', 'Chất lượng - An toàn - Hiệu quả - Chuyên nghiệp. Dịch vụ thi công trọn gói với chiến lược linh hoạt.', 'photo-1486406146926-c627a92ad1ab'],
                ['Không gian sống được thiết kế cho bạn', 'Từ ý tưởng đến bàn giao, chúng tôi đồng hành để tạo nên giá trị bền vững.', 'photo-1600585154340-be6161a56a0c'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0322-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'ND Construction', 'metadata' => ['summary' => $summary, 'button_label' => 'Liên hệ báo giá'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0322 Construction Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Sản phẩm', 'url' => '#san-pham'], ['label' => 'Dự án', 'url' => '#du-an'], ['label' => 'Đội ngũ', 'url' => '#doi-ngu'], ['label' => 'Tin tức', 'url' => '#tin-tuc'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'ND Construction', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'ND Construction', 'company_description' => 'Thiết kế và thi công trọn gói cho nhà ở, văn phòng và không gian thương mại.', 'support_hotline' => '0399162342', 'support_email' => 'support@ndconstruction.local', 'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(\App\Support\SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(\App\Support\SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 6, 'projects' => 6, 'products' => 8, 'posts' => 4, 'team_members' => 4, 'testimonials' => 3, 'partners' => 6, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'posts' => 0, 'post_media' => 0, 'projects' => 0, 'services' => 0, 'team_members' => 0, 'testimonials' => 0, 'products' => 0, 'partners' => 0, 'categories' => 0, 'landing_pages' => 0];
        if ($projectIds = $ids(CmsProject::class)) { CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete(); $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete(); }
        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($memberIds = $ids(CmsTeamMember::class)) { CmsTeamMemberImage::query()->whereIn('cms_team_member_id', $memberIds)->delete(); $counts['team_members'] = CmsTeamMember::query()->whereKey($memberIds)->delete(); }
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsTestimonial::class, 'testimonials'], [CatalogProduct::class, 'products'], [CmsPartner::class, 'partners'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners'], [CmsMedia::class, 'post_media']] as [$model, $key]) { if ($modelIds = $ids($model)) $counts[$key] += $model::query()->whereKey($modelIds)->delete(); }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
