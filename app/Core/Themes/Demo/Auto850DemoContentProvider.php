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
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Auto850DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'AUTO850';
    private const PRESET_KEY = 'auto850-euro-care';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }

    public function preset(): array
    {
        return ['key' => self::PRESET_KEY, 'label' => 'AUTO850 Euro Auto Care', 'description' => 'Trung tâm chăm sóc, bảo dưỡng và nâng cấp ô tô tông đỏ đen.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho AUTO850.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $categories = [];
            foreach (['Nâng cấp ánh sáng', 'Phim cách nhiệt', 'Camera hành trình', 'Âm thanh ô tô', 'Mâm và lốp xe', 'Màn hình Android'] as $index => $name) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('auto850-'.$name),
                    'description' => 'Giải pháp được tuyển chọn, tư vấn đúng nhu cầu và lắp đặt chuyên nghiệp.',
                    'image_url' => $this->asset('accessory-'.($index + 1)), 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category); $categories[] = $category;
            }

            $productDefinitions = [
                ['A850-LED-01', 0, 'Đèn LED tăng sáng RoadBeam', 2690000, 2990000, 1],
                ['A850-CAM-01', 2, 'Camera hành trình Vision Pro', 3450000, 3890000, 2],
                ['A850-AUDIO-01', 3, 'Loa sub gầm ghế BassCore', 5900000, 6500000, 3],
                ['A850-FILM-01', 1, 'Phim bảo vệ sơn ClearGuard', 8500000, 9200000, 4],
                ['A850-WHEEL-01', 4, 'Mâm hợp kim thể thao Aero', 7800000, 8600000, 5],
                ['A850-SCREEN-01', 5, 'Màn hình ô tô DriveLink 10 inch', 6990000, 7490000, 6],
                ['A850-LED-02', 0, 'Bi LED projector NightDrive', 4500000, 5100000, 1],
                ['A850-CAM-02', 2, 'Camera trước sau RoadView', 4290000, 4890000, 2],
                ['A850-AUDIO-02', 3, 'Bộ loa cánh cửa Signature', 6250000, 6900000, 3],
                ['A850-FILM-02', 1, 'Phim cách nhiệt Ceramic Shield', 12000000, 13000000, 4],
            ];
            foreach ($productDefinitions as $index => [$sku, $categoryIndex, $name, $price, $originalPrice, $image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name,
                    'slug' => Str::slug('auto850-'.$name), 'sku' => $sku, 'price' => $price,
                    'original_price' => $originalPrice, 'stock' => 10 + $index,
                    'short_description' => 'Phụ kiện chính hãng, bảo hành rõ ràng và thi công thẩm mỹ.',
                    'detail_content' => '<p>Sản phẩm AUTO850 được tư vấn theo dòng xe, nhu cầu sử dụng và ngân sách thực tế.</p>',
                    'image_url' => $this->asset('accessory-'.$image), 'is_featured' => true,
                    'is_highlight' => $index < 6, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            $serviceCategory = CmsServiceCategory::query()->create([
                'name' => 'Chăm sóc và bảo dưỡng ô tô', 'slug' => 'auto850-cham-soc-bao-duong',
                'description' => 'Các dịch vụ kỹ thuật tiêu chuẩn tại AUTO850.', 'image_url' => $this->asset('service-strip'),
                'sort_order' => 0, 'is_active' => true,
            ]);
            $this->record($serviceCategory);
            $serviceDefinitions = [
                ['Chẩn đoán tổng quát', 'Kiểm tra toàn diện bằng thiết bị hiện đại và tư vấn rõ ràng.'],
                ['Kiểm tra hệ thống phanh', 'Đo kiểm má phanh, đĩa phanh và dầu phanh theo tiêu chuẩn.'],
                ['Bảo dưỡng động cơ', 'Chăm sóc định kỳ giúp động cơ vận hành ổn định và bền bỉ.'],
                ['Thay lốp và cân bằng động', 'Lắp đặt chính xác, cân chỉnh an toàn trên mọi hành trình.'],
            ];
            foreach ($serviceDefinitions as $index => [$title, $summary]) {
                $service = CmsService::query()->create([
                    'cms_service_category_id' => $serviceCategory->id, 'title' => $title,
                    'slug' => Str::slug('auto850-'.$title), 'status' => 'published', 'summary' => $summary,
                    'content' => '<p>'.$summary.'</p><p>Quy trình minh bạch, báo giá trước khi thực hiện và có chính sách bảo hành.</p>',
                    'button_label' => 'Đặt lịch', 'link_url' => '#dat-lich', 'publish_at' => now(),
                    'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index,
                ]);
                $this->record($service);
                $image = CmsServiceImage::query()->create([
                    'cms_service_id' => $service->id, 'image_url' => $this->asset('service-'.($index + 1)),
                    'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0,
                ]);
                $this->record($image);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Kinh nghiệm chăm xe', 'slug' => 'auto850-kinh-nghiem-cham-xe', 'description' => 'Tin tức và kiến thức sử dụng ô tô.']);
            $this->record($postCategory);
            $postDefinitions = [
                ['5 dấu hiệu lốp xe cần được thay mới', 'Nhận biết sớm giúp hành trình an toàn và tiết kiệm hơn.', 4],
                ['Vì sao nên kiểm tra phanh định kỳ?', 'Hệ thống phanh cần được đo kiểm theo quãng đường sử dụng.', 2],
                ['Kinh nghiệm chăm sóc động cơ mùa nóng', 'Những hạng mục nên ưu tiên khi nhiệt độ tăng cao.', 3],
                ['Nâng cấp ánh sáng sao cho đúng nhu cầu', 'Chọn cấu hình phù hợp để tăng tầm nhìn mà vẫn an toàn.', 1],
            ];
            foreach ($postDefinitions as $index => [$title, $excerpt, $imageIndex]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => $this->asset('service-'.$imageIndex), 'mime_type' => 'image/png', 'size' => 0, 'alt_text' => $title]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('auto850-'.$title),
                    'status' => 'published', 'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>AUTO850 chia sẻ những lưu ý thực tế để xe vận hành an toàn, ổn định và bền bỉ hơn.</p>',
                    'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true,
                ]);
                $this->record($post);
            }

            foreach ([
                ['Nguyễn Minh Hoàng', 'Tài xế', 'Tư vấn rất chi tiết, thi công cẩn thận và đúng hẹn. Ánh sáng cải thiện rõ rệt.'],
                ['Trần Quốc Bảo', 'Nhân viên văn phòng', 'Quy trình chuyên nghiệp, báo giá minh bạch và không phát sinh ngoài dự kiến.'],
                ['Lê Thanh Tùng', 'Chủ doanh nghiệp', 'Lắp camera gọn gàng, hướng dẫn tận tình và chế độ bảo hành khiến tôi rất yên tâm.'],
            ] as $index => [$name, $role, $quote]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'company' => 'Khách hàng AUTO850', 'quote' => $quote, 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
                $this->record($testimonial);
            }
            foreach (['HONRIN', 'MITSUBA', 'MERCURA', 'KIO', 'BAVEN', 'AUDRIA'] as $index => $name) {
                $partner = CmsPartner::query()->create(['title' => $name, 'slug' => Str::slug('auto850-'.$name), 'description' => 'Đối tác đồng hành cùng AUTO850.', 'image_url' => null, 'image_alt' => $name, 'link_url' => '#top', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'AUTO850 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'url' => $home], ['label' => 'Giới thiệu', 'url' => $home.'#gioi-thieu'],
                ['label' => 'Sản phẩm', 'url' => $home.'#san-pham'], ['label' => 'Dịch vụ', 'url' => $home.'#dich-vu'],
                ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'], ['label' => 'Câu hỏi thường gặp', 'url' => $home.'#faq'],
                ['label' => 'Liên hệ', 'url' => route('site.contact')],
            ]]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ AUTO850', 'status' => 'published', 'excerpt' => 'Đặt lịch chăm sóc, bảo dưỡng và nâng cấp ô tô.', 'body' => '<p>Gửi thông tin xe và nhu cầu để AUTO850 tư vấn lịch phù hợp.</p>', 'publish_at' => now()]);
            if ($contact->wasRecentlyCreated) { $this->record($contact); }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'AUTO850 Euro Auto Care', 'company_description' => 'Trung tâm chăm sóc, bảo dưỡng và nâng cấp ô tô chuyên nghiệp.',
                'slogan' => 'Đồng hành cùng mọi hành trình', 'support_hotline' => '1900 6750',
                'support_email' => 'support@htvietnam.vn', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
            ];
            $profile->forceFill(['site_name' => 'AUTO850 Euro Auto Care', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => $branding])->save();

            $websiteKey = $this->siteContext->websiteKey();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) { $this->record($landing); }

            return ['preset' => $this->preset(), 'counts' => [
                'categories' => 6, 'products' => 10, 'service_categories' => 1, 'services' => 4, 'service_images' => 4,
                'post_categories' => 1, 'posts' => 4, 'media' => 4, 'testimonials' => 3, 'partners' => 6,
                'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0,
            ], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = array_fill_keys(['categories', 'products', 'service_categories', 'services', 'service_images', 'post_categories', 'posts', 'media', 'testimonials', 'partners', 'pages', 'menus', 'landing_pages'], 0);
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        foreach ([
            [CmsServiceImage::class, 'service_images'], [CmsService::class, 'services'], [CmsServiceCategory::class, 'service_categories'],
            [CmsTestimonial::class, 'testimonials'], [CmsPartner::class, 'partners'], [CmsPost::class, 'posts'], [CmsMedia::class, 'media'],
            [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'],
            [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) { $counts[$key] = $model::query()->whereKey($modelIds)->delete(); }
        }
        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete();
        return $counts;
    }

    private function asset(string $name): string { return '/themes/AUTO850/images/'.$name.'.png'; }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
