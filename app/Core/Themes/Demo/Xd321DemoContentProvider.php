<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
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

class Xd321DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD321';
    private const PRESET_KEY = 'xd321-cargo-logistics';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'XD321 Cargo Logistics', 'description' => 'Cargo, warehouse, packaging and logistics content designed specifically for XD321.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for XD321.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Van tai duong bien', 'Lich trinh linh hoat, ket noi cang bien va toi uu chi phi cho hang FCL, LCL.', 'photo-1494412651409-8963ce7935a7'],
                ['Van tai hang khong', 'Rut ngan thoi gian giao nhan voi dich vu hang khong quoc te va theo doi don hang.', 'photo-1436491865332-7a61a109cc05'],
                ['Van tai duong bo', 'Dieu phoi xe tai va giao nhan noi dia theo lich hen, an toan va dung tien do.', 'photo-1566576912321-d58ddd7a6088'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiet', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Giai phap kho my pham cho doanh nghiep', 'He thong luu kho chuyen biet, kiem soat dieu kien bao quan, nhap xuat va ton kho theo thoi gian thuc.', 'photo-1586528116493-da8b8d5a6c44'],
                ['4PL va dieu phoi chuoi cung ung', 'Ket noi nha cung cap, kho bai va don vi van chuyen de toi uu toan bo hanh trinh don hang.', 'photo-1586528116311-ad8dd3c8310d'],
                ['Hang du an va giao nhan chuyen dung', 'Phuong an van chuyen linh hoat cho may moc, thiet bi va cac lo hang co yeu cau dac thu.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiet', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            $category = CatalogCategory::query()->create(['name' => 'Vat tu dong goi XD321', 'slug' => 'xd321-vat-tu-dong-goi', 'description' => 'Thung carton, seal niem phong va vat tu ho tro giao nhan.', 'image_url' => $image('photo-1601584115197-04ecc0da31d7', 700), 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);
            foreach ([
                ['Thung carton 5 lop 25x20x13cm', 13000, 'photo-1601584115197-04ecc0da31d7'],
                ['Thung carton 5 lop 28x22x31cm', 18000, 'photo-1504274066651-8d31a536b11a'],
                ['Thung carton dong hang 70x60x52cm', 26000, 'photo-1586864387967-d02ef85d93e8'],
                ['Thung carton vach ngan chai 64x54x46cm', 21000, 'photo-1586864387789-628af9feed72'],
            ] as $index => [$name, $price, $photo]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('xd321-'.$name), 'sku' => 'X321-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $index === 2 ? 28000 : null, 'stock' => 100, 'short_description' => 'Vat tu dong goi demo cho XD321 Cargo.', 'detail_content' => '<p>San pham dong goi phu hop cho luu kho va van chuyen.</p>', 'image_url' => $image($photo, 800), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Nhung truong hop nen chon van chuyen hang hoa quoc te bang duong bo', 'Nhung luu y ve tuyen duong, chung tu va cach toi uu chi phi khi van chuyen xuyen bien gioi.', 'photo-1566576912321-d58ddd7a6088'],
                ['Cac loai kho trong logistics va cach toi uu chuoi cung ung', 'Kho hang, kho phan phoi va kho bao quan can duoc to chuc theo nhu cau van hanh thuc te.', 'photo-1586528116493-da8b8d5a6c44'],
                ['Cross docking giup doanh nghiep rut ngan thoi gian xu ly don', 'Mo hinh trung chuyen phu hop cho cac doanh nghiep can tang toc do giao nhan va giam ton kho.', 'photo-1586528116311-ad8dd3c8310d'],
                ['Tam quan trong cua bao hiem hang hoa trong van chuyen quoc te', 'Chuan bi dung giup doanh nghiep chu dong hon truoc cac rui ro tren tung chang duong.', 'photo-1436491865332-7a61a109cc05'],
            ] as $index => [$title, $excerpt, $photo]) {
                $post = CmsPost::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'meta_title' => $title, 'meta_description' => $excerpt, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach ([
                ['ABC Manufacturing', 'Logistics Manager', 'XD321 Cargo la doi tac logistics dang tin cay trong van hanh hang ngay. Doi ngu phan hoi nhanh va luon dam bao lo hang duoc xu ly dung tien do.', 'photo-1494790108377-be9c29b29330'],
                ['Minh Phat Trading', 'Supply Chain Director', 'Bao gia ro rang, quy trinh minh bach va kha nang dieu phoi linh hoat giup chung toi chu dong hon trong tung dot giao hang.', 'photo-1500648767791-00dcc994a43e'],
                ['Northstar Retail', 'Operations Lead', 'He thong theo doi don hang va cham soc khach hang cua XD321 giup doi ngu van hanh tiet kiem rat nhieu thoi gian.', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'quote' => $quote, 'image_url' => $image($photo, 500), 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            foreach (['CATHAY', 'LUFTHANSA', 'CMA CGM', 'IATA', 'GLOBAL CARGO'] as $index => $title) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd321-'.$title), 'description' => 'Doi tac logistics quoc te.', 'image_url' => "https://placehold.co/260x120/ffffff/153d78?text=".urlencode($title), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Nhanh chong - An toan cung XD321 Cargo', 'Toi uu moi hanh trinh van chuyen tu noi dia den quoc te. An toan va tiet kiem.', 'photo-1436491865332-7a61a109cc05'],
                ['Ket noi chuoi cung ung khong gioi han', 'Dich vu van tai linh hoat, minh bach va dung tien do cho doanh nghiep.', 'photo-1494412651409-8963ce7935a7'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd321-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#dich-vu', 'badge' => 'XD321 Cargo', 'metadata' => ['summary' => $summary, 'button_label' => 'Xem them'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD321 Cargo Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chu', 'url' => '#top'], ['label' => 'Gioi thieu', 'url' => '#gioi-thieu'], ['label' => 'Dich vu', 'url' => '#dich-vu'], ['label' => 'Giai phap', 'url' => '#giai-phap'], ['label' => 'San pham', 'url' => '#san-pham'], ['label' => 'Tin tuc', 'url' => '#tin-tuc'], ['label' => 'Lien he', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'XD321 Cargo', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'XD321 Cargo', 'company_description' => 'Doi tac logistics tin cay, ket noi hang hoa toan cau.', 'support_hotline' => '1900 6750', 'support_email' => 'support@xd321cargo.local', 'support_location' => '70 Lu Gia, Phuong 15, Quan 11, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', 'website-main')->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome('website-main', self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 3, 'projects' => 3, 'products' => 4, 'posts' => 4, 'testimonials' => 3, 'partners' => 5, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'posts' => 0, 'projects' => 0, 'services' => 0, 'testimonials' => 0, 'products' => 0, 'partners' => 0, 'categories' => 0, 'landing_pages' => 0];
        if ($projectIds = $ids(CmsProject::class)) { CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete(); $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete(); }
        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsTestimonial::class, 'testimonials'], [CatalogProduct::class, 'products'], [CmsPartner::class, 'partners'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) { if ($modelIds = $ids($model)) $counts[$key] += $model::query()->whereKey($modelIds)->delete(); }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
