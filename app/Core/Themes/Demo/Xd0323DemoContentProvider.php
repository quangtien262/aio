<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Xd0323DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0323';
    private const PRESET_KEY = 'xd0323-euro-farm';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }

    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'XD0323 Euro Farm', 'description' => 'Data test Euro Farm: nông sản, dịch vụ nông nghiệp, dự án, đội ngũ, đánh giá, tin tức, banner và landing blocks.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for XD0323.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            $productCategory = CatalogCategory::query()->create([
                'name' => 'Nông sản hữu cơ',
                'slug' => 'xd0323-nong-san-huu-co',
                'description' => 'Rau củ, trái cây và thực phẩm sạch tuyển chọn từ trang trại Euro Farm.',
                'image_url' => $image('photo-1542838132-92c53300491e', 700),
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($productCategory);

            foreach ([
                ['Bắp ngọt hữu cơ 500gr', 46000, 'photo-1551754655-cd27e38d2076'],
                ['Bí đao hữu cơ - 500g', 85000, 'photo-1566385101042-1a0aa0c1268c'],
                ['Cà chua bee ngọt hữu cơ - 300g', 59400, 'photo-1592924357228-91a4daadcfea'],
                ['Cà rốt baby hữu cơ - 250g', 74750, 'photo-1445282768818-728615cc910a'],
                ['Rau kale hữu cơ', 52000, 'photo-1515543904379-3d757afe72e4'],
                ['Nước ép trái cây organic', 39000, 'photo-1621506289937-a8e4df240d0b'],
            ] as $index => [$name, $price, $photo]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $productCategory->id,
                    'name' => $name,
                    'slug' => Str::slug('xd0323-'.$name),
                    'sku' => 'EF-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'stock' => 80,
                    'short_description' => 'Nông sản hữu cơ tươi sạch, đóng gói trong ngày.',
                    'detail_content' => '<p>Sản phẩm được tuyển chọn từ vùng trồng an toàn, bảo quản lạnh và giao nhanh.</p>',
                    'image_url' => $image($photo, 800),
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $serviceCategory = CmsServiceCategory::query()->create([
                'name' => 'Giải pháp nông nghiệp',
                'slug' => 'xd0323-giai-phap-nong-nghiep',
                'description' => 'Giải pháp trồng trọt, thu hoạch, dinh dưỡng và phân phối nông sản.',
                'image_url' => $image('photo-1523741543316-beb7fc7023d8', 900),
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($serviceCategory);

            foreach ([
                ['Giải pháp trồng rau củ sạch', 'Thiết kế vùng trồng, quy trình chăm sóc và kiểm soát chất lượng rau củ.', 'fa-solid fa-basket-shopping', 'photo-1542838132-92c53300491e'],
                ['Giải pháp thu hoạch', 'Tối ưu thời điểm thu hoạch, sơ chế và vận chuyển nông sản.', 'fa-solid fa-tractor', 'photo-1523741543316-beb7fc7023d8'],
                ['Giải pháp dinh dưỡng', 'Tư vấn dinh dưỡng cây trồng và danh mục thực phẩm xanh.', 'fa-solid fa-apple-whole', 'photo-1592841200221-a6898f307baa'],
                ['Cung ứng nông sản', 'Kết nối nguồn hàng hữu cơ ổn định cho cửa hàng và doanh nghiệp.', 'fa-solid fa-store', 'photo-1500382017468-9049fed747ef'],
            ] as $index => [$title, $summary, $icon, $photo]) {
                $service = CmsService::query()->create([
                    'cms_service_category_id' => $serviceCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('xd0323-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'icon' => $icon,
                    'button_label' => 'Xem chi tiết',
                    'link_url' => '#lien-he',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => $now,
                ]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Trang trại rau hữu cơ Đà Lạt', 'Khu trồng rau sạch theo quy trình kiểm soát đất, nước và thu hoạch.', 'photo-1500382017468-9049fed747ef'],
                ['Nhà kính cà chua công nghệ cao', 'Ứng dụng nhà kính và kiểm soát dinh dưỡng cho cà chua hữu cơ.', 'photo-1592841200221-a6898f307baa'],
                ['Chuỗi cung ứng trái cây tươi', 'Quy trình đóng gói và giao hàng lạnh cho trái cây trong ngày.', 'photo-1490818387583-1baba5e638af'],
            ] as $index => [$title, $summary, $photo]) {
                $project = CmsProject::query()->create([
                    'title' => $title,
                    'slug' => Str::slug('xd0323-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p>',
                    'button_label' => 'Xem dự án',
                    'link_url' => '#lien-he',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => $now,
                ]);
                CmsProjectImage::query()->create(['cms_project_id' => $project->id, 'image_url' => $image($photo, 1000), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($project);
            }

            foreach ([
                ['Nguyễn Văn Huy', 'Khách hàng thân thiết', 'Tôi rất hài lòng với sản phẩm của farm. Rau củ luôn tươi, sạch và có mùi vị tự nhiên.', 'photo-1506794778202-cad84cf45f1d'],
                ['Trần Quốc Anh', 'Nhân viên văn phòng', 'Mình đặt hàng vài lần và lần nào cũng ưng ý. Rau quả tươi lâu, đóng gói gọn gàng.', 'photo-1527980965255-d3b416303d12'],
            ] as $index => [$name, $role, $quote, $photo]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'company' => $role, 'quote' => $quote, 'image_url' => $image($photo, 500), 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            foreach ([
                ['Mike Brown', 'Founder & owner', 'photo-1530268729831-4b0b9e170218'],
                ['Alees Hardson', 'Winery Master', 'photo-1494790108377-be9c29b29330'],
                ['Hailey Simpson', 'Agricultural Development Specialist', 'photo-1527980965255-d3b416303d12'],
                ['Jassica Andrew', 'Agricultural Systems Technician', 'photo-1534528741775-53994a69daeb'],
            ] as $index => [$name, $role, $photo]) {
                $member = CmsTeamMember::query()->create(['name' => $name, 'slug' => Str::slug('xd0323-'.$name), 'role' => $role, 'summary' => $role, 'bio' => '<p>'.$role.'</p>', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                CmsTeamMemberImage::query()->create(['cms_team_member_id' => $member->id, 'image_url' => $image($photo, 700), 'alt_text' => $name, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($member);
            }

            $newsCategory = CmsCategory::query()->create(['name' => 'Tin nông nghiệp sạch', 'slug' => 'xd0323-tin-nong-nghiep-sach', 'description' => 'Tin tức và kiến thức về thực phẩm hữu cơ.']);
            $this->record($newsCategory);
            $postImages = $this->ensurePostImages();
            foreach ([
                ['15 thực đơn bữa sáng healthy thơm ngon, nhiều dinh dưỡng', 'Gợi ý bữa sáng với trái cây, ngũ cốc và rau củ tốt cho sức khỏe.', 'photo-1490818387583-1baba5e638af'],
                ['Top 8 các loại sinh tố tốt cho da, bí quyết khỏe đẹp từ bên trong', 'Các loại sinh tố xanh giúp bổ sung vitamin và khoáng chất tự nhiên.', 'photo-1621506289937-a8e4df240d0b'],
                ['Cách bày đĩa hoa quả đẹp: bí quyết trang trí tinh tế & ấn tượng', 'Mẹo chọn và trình bày trái cây tươi cho bàn tiệc gia đình.', 'photo-1494390248081-4e521a5940db'],
            ] as $index => [$title, $excerpt, $photo]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => $postImages[$index % count($postImages)], 'file_url' => $image($photo, 1000), 'mime_type' => 'image/png', 'size' => 0, 'alt_text' => $title]);
                $post = CmsPost::query()->create(['category_id' => $newsCategory->id, 'title' => $title, 'slug' => Str::slug('xd0323-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'featured_media_id' => $media->id, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($media);
                $this->record($post);
            }

            foreach ([
                ['Thực phẩm hữu cơ tươi chất lượng cao', 'Sản phẩm nông nghiệp tự nhiên', 'photo-1500382017468-9049fed747ef'],
                ['Nông sản sạch cho bữa ăn xanh', 'Từ trang trại đến bàn ăn', 'photo-1492496913980-501348b61469'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0323-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 2200), 'link_url' => '#san-pham', 'badge' => 'Euro Farm', 'metadata' => ['summary' => $summary, 'eyebrow' => 'Euro Farm', 'button_label' => 'Xem ngay'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0323 Euro Farm Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Sản phẩm', 'url' => '#san-pham'], ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'], ['label' => 'Tin tức', 'url' => '#tin-tuc'], ['label' => 'Dự án', 'url' => '#du-an'], ['label' => 'Đội ngũ', 'url' => '#doi-ngu'], ['label' => 'Câu hỏi thường gặp', 'url' => '#hoi-dap'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Euro Farm', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Euro Farm', 'company_description' => 'Euro Farm là doanh nghiệp nông nghiệp tiên phong chuyên sản xuất và cung cấp thực phẩm hữu cơ, an toàn và tốt cho sức khỏe.', 'support_hotline' => '0399162342', 'support_email' => 'support@htvietnam.vn', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(\App\Support\SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(\App\Support\SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['product_categories' => 1, 'products' => 6, 'service_categories' => 1, 'services' => 4, 'projects' => 3, 'testimonials' => 2, 'team_members' => 4, 'post_categories' => 1, 'posts' => 3, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'posts' => 0, 'post_media' => 0, 'post_categories' => 0, 'projects' => 0, 'services' => 0, 'service_categories' => 0, 'products' => 0, 'product_categories' => 0, 'landing_pages' => 0, 'testimonials' => 0, 'team_members' => 0];

        if ($projectIds = $ids(CmsProject::class)) { CmsProjectImage::query()->whereIn('cms_project_id', $projectIds)->delete(); $counts['projects'] = CmsProject::query()->whereKey($projectIds)->delete(); }
        if ($serviceIds = $ids(CmsService::class)) { CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete(); $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete(); }
        if ($teamIds = $ids(CmsTeamMember::class)) { CmsTeamMemberImage::query()->whereIn('cms_team_member_id', $teamIds)->delete(); $counts['team_members'] = CmsTeamMember::query()->whereKey($teamIds)->delete(); }
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }

        foreach ([[CmsPost::class, 'posts'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'product_categories'], [CmsCategory::class, 'post_categories'], [CmsServiceCategory::class, 'service_categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners'], [CmsMedia::class, 'post_media'], [CmsTestimonial::class, 'testimonials']] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] += $model::query()->whereKey($modelIds)->delete();
            }
        }

        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }

    /** @return array<int, string> */
    private function ensurePostImages(): array
    {
        $source = public_path('theme-previews/XD0323/preview-xd0323.png');
        $relativeDir = 'theme-demo/xd0323';
        $absoluteDir = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir));

        if (! File::isDirectory($absoluteDir)) {
            File::makeDirectory($absoluteDir, 0755, true);
        }

        return collect(range(1, 3))->map(function (int $index) use ($source, $relativeDir, $absoluteDir): string {
            $fileName = 'farm-news-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.png';
            $absoluteTarget = $absoluteDir.DIRECTORY_SEPARATOR.$fileName;

            if (File::exists($source) && ! File::exists($absoluteTarget)) {
                File::copy($source, $absoluteTarget);
            }

            return $relativeDir.'/'.$fileName;
        })->all();
    }
}
