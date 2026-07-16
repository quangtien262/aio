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
                ['Thiet ke noi that', 'Tu van bo cuc, vat lieu va giai phap noi that dong bo cho khong gian song.', 'fa-solid fa-couch', 'photo-1618221195710-dd6b41faaea6'],
                ['Thi cong nha pho', 'Thi cong tron goi nha pho theo quy trinh ro rang, kiem soat chat luong tung giai doan.', 'fa-solid fa-compass-drafting', 'photo-1487958449943-2429e8be8625'],
                ['Thi cong nha hang va cafe', 'Phat trien khong gian kinh doanh co ban sac, toi uu van hanh va trai nghiem khach hang.', 'fa-solid fa-store', 'photo-1514933651103-005eec06c04b'],
                ['Thi cong can ho', 'Hoan thien can ho hien dai, dong bo tu y tuong den noi that va ban giao.', 'fa-solid fa-building', 'photo-1600585154340-be6161a56a0c'],
                ['Thi cong biet thu', 'Giai phap thiet ke va thi cong biet thu chu trong ca tinh va chat luong ben vung.', 'fa-solid fa-house', 'photo-1600607687939-ce8a6c25118c'],
                ['Thi cong van phong', 'Khong gian lam viec linh hoat, nang cao hieu suat va nhan dien thuong hieu.', 'fa-solid fa-briefcase', 'photo-1497366754035-f200968a6e72'],
            ] as $index => [$title, $summary, $icon, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'icon' => $icon, 'button_label' => 'Xem chi tiet', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Can ho cao cap phong cach contemporary', 'Can ho 3 phong ngu voi noi that go am, anh sang tu nhien va giai phap luu tru thong minh.', 'photo-1600210492486-724fe5c67fb0'],
                ['Biet thu vuon hien dai', 'Khong gian mo ket noi phong khach, bep va khu vuon cho gia dinh nhieu the he.', 'photo-1600585152915-d208bec867a1'],
                ['Nha pho 4 tang tai Thu Duc', 'Nha pho toi uu thong gio va anh sang, hoan thien dong bo mat tien va noi that.', 'photo-1600566753086-00f18fb6b3ea'],
                ['Van phong sang tao cho doanh nghiep cong nghe', 'Van phong hybrid voi khu hop, khu tap trung va cac diem ket noi cong dong.', 'photo-1497366216548-37526070297c'],
                ['Showroom noi that thuong hieu Viet', 'Khong gian trung bay co nhieu lop trai nghiem de ton vinh vat lieu va san pham.', 'photo-1494438639946-1ebd1d20bf85'],
                ['Nha hang urban contemporary', 'Nha hang voi vat lieu moc, he den am va luong di chuyen toi uu cho gio cao diem.', 'photo-1517248135467-4c7edcad34c4'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem du an', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            $category = CatalogCategory::query()->create(['name' => 'Noi that va vat lieu XD0322', 'slug' => 'xd0322-noi-that-va-vat-lieu', 'description' => 'Noi that hoan thien cho nha o, van phong va khong gian thuong mai.', 'image_url' => $image('photo-1618220179428-22790b461013', 700), 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);
            foreach ([
                ['Ban lam viec go oc cho', 3250000, 'photo-1518455027359-f3f8164ba6bd'], ['Tu ke tivi go soi', 4800000, 'photo-1616486338812-3dadae4b4ace'], ['Tu giay canh lua', 3650000, 'photo-1618221195710-dd6b41faaea6'], ['Ke sach module', 2900000, 'photo-1594620302200-9a762244a156'], ['Ghe don phong khach', 1450000, 'photo-1555041469-a586c61ea9bc'], ['Tu dau giuong toi gian', 2100000, 'photo-1616486338812-3dadae4b4ace'], ['Ghe van phong ergonomic', 2750000, 'photo-1505843490701-5b4b83a4d523'], ['Sofa vai trung tinh', 8900000, 'photo-1555041469-a586c61ea9bc'],
            ] as $index => [$name, $price, $photo]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('xd0322-'.$name), 'sku' => 'X322-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $index === 7 ? 9800000 : null, 'stock' => 20, 'short_description' => 'San pham noi that demo phu hop cho XD0322 Construction.', 'detail_content' => '<p>San pham hoan thien cho khong gian hien dai.</p>', 'image_url' => $image($photo, 800), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Toi uu anh sang tu nhien trong can ho hien dai', 'Nhung nguyen tac don gian de khong gian song thoang, tiet kiem nang luong va ben vung.', 'photo-1600210492486-724fe5c67fb0'],
                ['Cach chon vat lieu hoan thien ben dep theo thoi gian', 'Can bang tham my, do ben va chi phi de moi quyet dinh thiet ke de dang hon.', 'photo-1618220179428-22790b461013'],
                ['Quy trinh thi cong tron goi giup kiem soat tien do', 'Tu khao sat den ban giao, cac moc cong viec can minh bach de giu dung cam ket.', 'photo-1503387762-592deb58ef4e'],
                ['Xu huong khong gian lam viec linh hoat nam nay', 'Van phong can ho tro tap trung, hop tac va phuc hoi nang luong cho doi ngu.', 'photo-1497366754035-f200968a6e72'],
            ] as $index => [$title, $excerpt, $photo]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_url' => $image($photo, 1000), 'mime_type' => 'image/jpeg', 'size' => 0, 'alt_text' => $title]);
                $post = CmsPost::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'meta_title' => $title, 'meta_description' => $excerpt, 'featured_media_id' => $media->id, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($media);
                $this->record($post);
            }

            foreach ([
                ['Nguyen Van Son', 'Tong giam doc', 'Dieu hanh', 'Kinh nghiem trong quan ly chat luong va dieu phoi cac du an xay dung.', 'photo-1500648767791-00dcc994a43e'],
                ['Nguyen Quang Anh', 'Giam sat thi cong', 'Thi cong', 'Theo sat cong truong, tien do va tieu chuan an toan cho tung hang muc.', 'photo-1506794778202-cad84cf45f1d'],
                ['Nguyen Thi Tham', 'Truong phong ke toan', 'Van hanh', 'Quan ly ngan sach minh bach va ho tro van hanh du an hieu qua.', 'photo-1494790108377-be9c29b29330'],
                ['Nguyen Thu Trang', 'Giam doc truyen thong', 'Sang tao', 'Ket noi y tuong thiet ke voi cau chuyen thuong hieu cua khach hang.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $department, $summary, $photo]) {
                $member = CmsTeamMember::query()->create(['name' => $name, 'slug' => Str::slug('xd0322-'.$name), 'role' => $role, 'department' => $department, 'summary' => $summary, 'bio' => $summary, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                CmsTeamMemberImage::query()->create(['cms_team_member_id' => $member->id, 'image_url' => $image($photo, 700), 'alt_text' => $name, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($member);
            }

            foreach ([
                ['Gia Bao Residence', 'Chu dau tu', 'ND Construction hoan thien cong trinh dung tien do, chi tiet noi that chi chu va doi ngu phoi hop rat chuyen nghiep.', 'photo-1507003211169-0a1dd7228f2d'],
                ['Minh Anh Studio', 'Founder', 'Tu van ro rang tu ngay dau, phuong an vat lieu hop ly va chat luong thi cong vuot ky vong cua chung toi.', 'photo-1544005313-94ddf0286df2'],
                ['Lumi Office', 'Operations Director', 'Du an van phong da van hanh on dinh sau ban giao. Khong gian dep va phu hop voi cach doi ngu lam viec.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'company' => 'Khach hang ND Construction', 'quote' => $quote, 'image_url' => $image($photo, 500), 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            foreach (['ARC Studio', 'UrbanBuild', 'NOVA Materials', 'Prime Lighting', 'Form Works', 'Green Habitat'] as $index => $title) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0322-'.$title), 'description' => 'Doi tac dong hanh cung ND Construction.', 'image_url' => 'https://placehold.co/260x120/ffffff/071638?text='.urlencode($title), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Cung cap giai phap xay dung tot nhat', 'Chat luong - An toan - Hieu qua - Chuyen nghiep. Dich vu thi cong tron goi voi chien luoc linh hoat.', 'photo-1486406146926-c627a92ad1ab'],
                ['Khong gian song duoc thiet ke cho ban', 'Tu y tuong den ban giao, chung toi dong hanh de tao nen gia tri ben vung.', 'photo-1600585154340-be6161a56a0c'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0322-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'ND Construction', 'metadata' => ['summary' => $summary, 'button_label' => 'Lien he bao gia'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0322 Construction Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chu', 'url' => '#top'], ['label' => 'Gioi thieu', 'url' => '#gioi-thieu'], ['label' => 'Dich vu', 'url' => '#dich-vu'], ['label' => 'San pham', 'url' => '#san-pham'], ['label' => 'Du an', 'url' => '#du-an'], ['label' => 'Doi ngu', 'url' => '#doi-ngu'], ['label' => 'Tin tuc', 'url' => '#tin-tuc'], ['label' => 'Lien he', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'ND Construction', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'ND Construction', 'company_description' => 'Thiet ke va thi cong tron goi cho nha o, van phong va khong gian thuong mai.', 'support_hotline' => '1900 6750', 'support_email' => 'support@ndconstruction.local', 'support_location' => '266 Doi Can, Ba Dinh, Ha Noi'])])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
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
