<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
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
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Spa111DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SPA111';
    private const PRESET_KEY = 'spa111-bean-spa';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'SPA111 Bean Spa', 'description' => 'Dữ liệu mẫu Bean Spa gồm dịch vụ, sản phẩm, chuyên viên, đánh giá, đối tác và bài viết.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho SPA111.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $websiteKey = $this->siteContext->websiteKey();

            $category = CatalogCategory::query()->create([
                'name' => 'Chăm sóc spa',
                'slug' => 'spa111-cham-soc-spa',
                'description' => 'Sản phẩm chăm sóc tóc, da và cơ thể được Bean Spa tuyển chọn.',
                'image_url' => '/theme-demo/spa111/product-shampoo.png',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($category);

            $products = [
                ['Dầu gội ngăn ngừa gàu thảo dược', 99000, 170000, 'product-shampoo.png'],
                ['Tinh chất phục hồi tóc Sachi Inca', 148000, 165000, 'product-serum.png'],
                ['Kem ủ tóc Keratin Smooth Salon', 175000, 178000, 'product-mask.png'],
                ['Dầu xả tinh chất bưởi phục hồi tóc', 175000, 195000, 'product-shampoo.png'],
                ['Kem xả phục hồi tóc chuyên sâu', 99000, 108000, 'product-mask.png'],
                ['Dầu gội Professional Defense', 490000, 620000, 'product-shampoo.png'],
                ['Dầu xả thảo dược dưỡng sinh', 490000, 670000, 'product-serum.png'],
                ['Dầu gội dược liệu làm sạch da đầu', 120000, 147000, 'product-mask.png'],
            ];
            foreach ($products as $index => [$name, $price, $original, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug('spa111-'.$name),
                    'sku' => 'SPA111-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $original,
                    'stock' => 40,
                    'short_description' => 'Sản phẩm được Bean Spa tuyển chọn cho liệu trình chăm sóc chuyên sâu.',
                    'detail_content' => '<p>Công thức dịu nhẹ, nguồn gốc rõ ràng và phù hợp quy trình chăm sóc tại spa hoặc tại nhà.</p>',
                    'image_url' => '/theme-demo/spa111/'.$image,
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $serviceDefinitions = [
                ['Massage Thư Giãn Toàn Thân', 'Liệu trình massage chuyên sâu giúp giải tỏa căng thẳng, giảm đau mỏi cơ và cải thiện tuần hoàn máu.', 'fa-solid fa-spa', 'hero.png'],
                ['Chăm Sóc Da Mặt Chuyên Sâu', 'Làm sạch, phục hồi và nuôi dưỡng làn da với sản phẩm cao cấp phù hợp từng loại da.', 'fa-solid fa-face-smile-beam', 'facial.png'],
                ['Massage Đá Nóng Trị Liệu', 'Đá nóng tự nhiên giúp làm giãn cơ sâu, giảm đau nhức và phục hồi năng lượng.', 'fa-solid fa-fire-flame-simple', 'hot-stone.png'],
            ];
            foreach ($serviceDefinitions as $index => [$title, $summary, $icon, $image]) {
                $service = CmsService::query()->create([
                    'title' => $title, 'slug' => Str::slug('spa111-'.$title), 'status' => 'published',
                    'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'icon' => $icon,
                    'button_label' => 'Xem chi tiết', 'link_url' => '#lien-he', 'is_featured' => true,
                    'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now,
                ]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => '/theme-demo/spa111/'.$image, 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Minh Anh', 'Chăm sóc Da & Facial', 'staff-minh-anh.png'],
                ['Ngọc Hân', 'Massage Body & Thư giãn', 'staff-ngoc-han.png'],
                ['Thanh Vy', 'Điều trị da công nghệ cao', 'staff-thanh-vy.png'],
                ['Kim Ngân', 'Trị liệu chuyên sâu', 'staff-kim-ngan.png'],
            ] as $index => [$name, $role, $image]) {
                $member = CmsTeamMember::query()->create(['name' => $name, 'slug' => Str::slug('spa111-'.$name), 'role' => $role, 'summary' => $role, 'bio' => '<p>'.$role.'</p>', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                CmsTeamMemberImage::query()->create(['cms_team_member_id' => $member->id, 'image_url' => '/theme-demo/spa111/'.$image, 'alt_text' => $name, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($member);
            }

            foreach ([
                ['Nguyễn Thảo – Hà Nội', 'Nhân viên văn phòng', 'Mình rất hài lòng với dịch vụ chăm sóc da tại Bean Spa. Không gian thư giãn, nhân viên nhẹ nhàng và tư vấn rất kỹ.', 'staff-minh-anh.png'],
                ['Minh Tuấn – TP.HCM', 'Kỹ sư', 'Kỹ thuật massage tốt, không gian sạch sẽ, thư giãn đúng nghĩa sau giờ làm việc.', 'staff-ngoc-han.png'],
                ['Thu Hà – Đà Nẵng', 'Kinh doanh', 'Điều mình thích nhất ở Bean Spa là sự tận tâm. Dịch vụ chất lượng nhưng giá rất hợp lý.', 'staff-kim-ngan.png'],
            ] as $index => [$name, $role, $quote, $image]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'quote' => $quote, 'image_url' => '/theme-demo/spa111/'.$image, 'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }

            foreach (['Jasmine Spa', 'Calming Spa', 'Christine', 'Scarlet Academy', 'Beauty Spa', 'Elegance Beauty', 'Spa & Beauty', 'Viet Nails', 'Beauty Salon'] as $index => $name) {
                $partner = CmsPartner::query()->create(['title' => $name, 'slug' => Str::slug('spa111-'.$name), 'description' => 'Đối tác đồng hành cùng Bean Spa.', 'image_url' => null, 'image_alt' => $name, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Xu hướng làm đẹp', 'slug' => 'spa111-xu-huong-lam-dep', 'description' => 'Kiến thức chăm sóc sức khỏe và sắc đẹp.']);
            $this->record($postCategory);
            $postDefinitions = [
                ['Tầm quan trọng của việc thiết lập dịch vụ mới cho spa chuyên nghiệp', 'Thị trường chăm sóc sắc đẹp phát triển nhanh đòi hỏi liệu trình được cập nhật và cá nhân hóa.', 'hero.png'],
                ['Chăm sóc da mùa xuân bằng thảo dược hiệu quả tự nhiên 2026', 'Làn da trong thời điểm chuyển mùa cần được làm sạch, cấp ẩm và phục hồi đúng cách.', 'facial.png'],
                ['Chia sẻ 5 bí quyết làm đẹp dịp cuối năm hiệu quả nhất', 'Những thói quen nhỏ giúp duy trì làn da sáng khỏe trong mùa bận rộn.', 'why-choose.png'],
                ['Hướng dẫn skincare cho da khô mùa đông hiệu quả nhất 2026', 'Quy trình chăm sóc giúp giảm khô căng và củng cố hàng rào bảo vệ da.', 'hot-stone.png'],
            ];
            foreach ($postDefinitions as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => '/theme-demo/spa111/'.$image, 'mime_type' => 'image/png', 'size' => 0, 'alt_text' => $title]);
                $this->record($media);
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('spa111-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'featured_media_id' => $media->id, 'publish_at' => $now->copy()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach ([
                ['Nâng Niu Vẻ Đẹp Của Bạn', 'Chăm Sóc Sắc Đẹp Toàn Diện'],
                ['Thư Giãn Trọn Vẹn Mỗi Ngày', 'Liệu Trình Tinh Chỉnh Cho Riêng Bạn'],
            ] as $index => [$title, $subtitle]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'spa111-hero-slider', 'title' => $title, 'subtitle' => $subtitle, 'image_url' => '/theme-demo/spa111/hero.png', 'link_url' => '#dich-vu', 'badge' => 'BEAN SPA', 'metadata' => ['button_label' => 'Xem thêm'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'SPA111 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'url' => route('site.home')], ['label' => 'Về chúng tôi', 'url' => '#gioi-thieu'],
                ['label' => 'Sản phẩm', 'url' => route('site.catalog.search')], ['label' => 'Dịch vụ', 'url' => '#dich-vu'],
                ['label' => 'Đội ngũ', 'url' => '#doi-ngu'], ['label' => 'Tin tức', 'url' => '#tin-tuc'],
                ['label' => 'Câu hỏi thường gặp', 'url' => '#faq'], ['label' => 'Liên hệ', 'url' => '#lien-he'],
            ]]);
            $this->record($menu);

            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ Bean Spa', 'status' => 'published', 'excerpt' => 'Tư vấn liệu trình và đặt lịch tại Bean Spa.', 'body' => '<p>Đội ngũ Bean Spa sẵn sàng tư vấn liệu trình phù hợp với nhu cầu chăm sóc của bạn.</p>', 'publish_at' => $now]);
            if ($page->wasRecentlyCreated) $this->record($page);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Bean Spa', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, [
                'company_name' => 'Bean Spa', 'company_description' => 'Chăm sóc sức khỏe và sắc đẹp chuyên sâu.',
                'support_hotline' => '0399162342', 'support_email' => 'support@htvietnam.vn',
                'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP. Hồ Chí Minh',
            ])])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 1, 'products' => count($products), 'services' => count($serviceDefinitions), 'team_members' => 4, 'testimonials' => 3, 'partners' => 9, 'posts' => count($postDefinitions), 'banners' => 2, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = [];
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        if ($memberIds = $ids(CmsTeamMember::class)) CmsTeamMemberImage::query()->whereIn('cms_team_member_id', $memberIds)->delete();
        if ($serviceIds = $ids(CmsService::class)) CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
        foreach ([CmsPost::class, CmsMedia::class, CmsCategory::class, CmsPage::class, CmsPartner::class, CmsTestimonial::class, CmsTeamMember::class, CmsService::class, CatalogProduct::class, CatalogCategory::class, CmsMenu::class, SiteBanner::class] as $model) {
            if ($modelIds = $ids($model)) $counts[class_basename($model)] = $model::query()->whereKey($modelIds)->delete();
        }
        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
