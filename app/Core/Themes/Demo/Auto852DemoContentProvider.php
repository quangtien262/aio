<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
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

class Auto852DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'AUTO852';
    private const PRESET_KEY = 'auto852-onyx-detailing';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'AUTO852 Onyx Detailing', 'description' => 'Trung tâm detailing và sản phẩm chăm sóc xe cao cấp tông đen cam.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) { throw new InvalidArgumentException('Preset demo không hợp lệ cho AUTO852.'); }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $categoryNames = ['Chăm sóc ngoại thất', 'Chăm sóc nội thất', 'Chăm sóc khoang máy', 'Phụ kiện và dụng cụ', 'Chăm sóc lốp và mâm', 'Chăm sóc kính xe'];
            $categories = [];
            foreach ($categoryNames as $index => $name) {
                $category = CatalogCategory::query()->create(['name' => $name, 'slug' => Str::slug('auto852-'.$name), 'description' => 'Sản phẩm chuyên dụng được tuyển chọn cho quy trình detailing.', 'image_url' => $this->asset('product-'.(($index % 10) + 1)), 'sort_order' => $index, 'is_active' => true]);
                $this->record($category); $categories[] = $category;
            }

            $products = [
                ['Dung dịch vệ sinh kính Crystal Clear', 0, 108000, 140000], ['Dung dịch vệ sinh mâm xe chuyên sâu', 4, 285000, 385000],
                ['Xịt phủ bóng nhanh Quick Detailer', 0, 247000, 350000], ['Khử mùi và diệt khuẩn cabin', 1, 247000, 350000],
                ['Chai vệ sinh mạch điện chuyên dụng', 2, 195000, 0], ['Nước rửa kính lái đậm đặc', 5, 175000, 0],
                ['Nước rửa xe bóng sơn Premium Wash', 0, 160000, 200000], ['Dung dịch đánh bóng hoàn thiện', 0, 660000, 800000],
                ['Dung dịch bảo dưỡng lốp xe', 4, 170000, 0], ['Bộ phủ chống bám nước kính xe', 5, 320000, 0],
            ];
            foreach ($products as $index => [$name, $categoryIndex, $price, $originalPrice]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('auto852-'.$name), 'sku' => 'A852-PROD-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $originalPrice, 'stock' => 20 + $index, 'short_description' => 'Công thức chuyên dụng, dễ sử dụng và an toàn cho bề mặt xe.', 'detail_content' => '<p>Sản phẩm AUTO852 được tuyển chọn cho hiệu quả làm sạch và bảo vệ bền lâu.</p>', 'image_url' => $this->asset('product-'.($index + 1)), 'is_featured' => true, 'is_highlight' => $index < 5, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            $serviceCategory = CmsServiceCategory::query()->create(['name' => 'Detailing chuyên nghiệp', 'slug' => 'auto852-detailing-chuyen-nghiep', 'description' => 'Quy trình chăm sóc xe toàn diện tại AUTO852.', 'image_url' => $this->asset('service-1'), 'sort_order' => 0, 'is_active' => true]);
            $this->record($serviceCategory);
            $services = [
                ['Phủ ceramic cao cấp', 'Bảo vệ sơn, chống bám bẩn và duy trì độ bóng sâu.'],
                ['Hiệu chỉnh bề mặt sơn', 'Loại bỏ xước nhẹ, phục hồi độ bóng và chiều sâu màu sơn.'],
                ['Dán phim bảo vệ PPF', 'Bảo vệ bề mặt sơn trước va quệt và tác động môi trường.'],
                ['Vệ sinh nội thất', 'Làm sạch sâu, khử khuẩn và phục hồi bề mặt da, nỉ.'],
                ['Chăm sóc khoang máy', 'Làm sạch an toàn và bảo vệ các chi tiết quan trọng.'],
                ['Rửa xe detailing', 'Quy trình rửa không chạm, an toàn cho mọi bề mặt.'],
            ];
            foreach ($services as $index => [$title, $summary]) {
                $service = CmsService::query()->create(['cms_service_category_id' => $serviceCategory->id, 'title' => $title, 'slug' => Str::slug('auto852-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p><p>Quy trình minh bạch, báo giá rõ ràng và kiểm tra chất lượng trước bàn giao.</p>', 'button_label' => 'Đặt lịch', 'link_url' => '#bang-gia', 'publish_at' => now(), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index]);
                $this->record($service);
                $image = CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $this->asset('service-'.($index + 1)), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($image);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Kiến thức detailing', 'slug' => 'auto852-kien-thuc-detailing', 'description' => 'Kinh nghiệm chăm sóc và bảo vệ ô tô.']); $this->record($postCategory);
            $posts = [
                ['Bảo dưỡng ô tô thế nào sau hành trình dài?', 'Các bước kiểm tra giúp xe sẵn sàng cho hành trình tiếp theo.', 5],
                ['Sơn xe cần được bảo vệ thế nào trong mùa mưa?', 'Giải pháp hạn chế bám bẩn, vệt nước và tác động môi trường.', 1],
                ['Khi nào nên vệ sinh khoang động cơ?', 'Những dấu hiệu cho thấy khoang máy cần được chăm sóc đúng cách.', 5],
                ['Năm vị trí dễ bỏ quên khi chăm sóc nội thất', 'Kinh nghiệm làm sạch giúp khoang xe luôn thoáng và dễ chịu.', 4],
            ];
            foreach ($posts as $index => [$title, $excerpt, $imageIndex]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => $this->asset('service-'.$imageIndex), 'mime_type' => 'image/png', 'size' => 0, 'alt_text' => $title]); $this->record($media);
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('auto852-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p><p>AUTO852 chia sẻ kiến thức thực tế để xe luôn sạch đẹp và được bảo vệ đúng cách.</p>', 'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]); $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'AUTO852 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => $home], ['label' => 'Dịch vụ', 'url' => $home.'#dich-vu'], ['label' => 'Quy trình', 'url' => $home.'#quy-trinh'], ['label' => 'Bảng giá', 'url' => $home.'#bang-gia'], ['label' => 'Sản phẩm', 'url' => $home.'#san-pham'], ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'], ['label' => 'Liên hệ', 'url' => route('site.contact')]]]); $this->record($menu);
            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ AUTO852', 'status' => 'published', 'excerpt' => 'Đặt lịch detailing và tư vấn sản phẩm chăm sóc xe.', 'body' => '<p>Gửi thông tin xe và nhu cầu để AUTO852 tư vấn giải pháp phù hợp.</p>', 'publish_at' => now()]); if ($contact->wasRecentlyCreated) { $this->record($contact); }

            $profile = SiteProfile::query()->firstOrNew(); $branding = (array) $profile->branding;
            $branding += ['company_name' => 'AUTO852 Onyx Detailing', 'company_description' => 'Trung tâm chăm sóc xe chuyên nghiệp với dịch vụ detailing cao cấp và sản phẩm bảo vệ xe được tuyển chọn.', 'slogan' => 'Hoàn hảo từng chi tiết', 'support_hotline' => '1900 6750', 'support_email' => 'support@htvietnam.vn', 'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM'];
            $profile->forceFill(['site_name' => 'AUTO852 Onyx Detailing', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => $branding])->save();

            $websiteKey = $this->siteContext->websiteKey(); $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true); if ($landing && ! $existing) { $this->record($landing); }

            return ['preset' => $this->preset(), 'counts' => ['categories' => 6, 'products' => 10, 'service_categories' => 1, 'services' => 6, 'service_images' => 6, 'post_categories' => 1, 'posts' => 4, 'media' => 4, 'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get(); $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = array_fill_keys(['categories', 'products', 'service_categories', 'services', 'service_images', 'post_categories', 'posts', 'media', 'pages', 'menus', 'landing_pages'], 0);
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsServiceImage::class, 'service_images'], [CmsService::class, 'services'], [CmsServiceCategory::class, 'service_categories'], [CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus']] as [$model, $key]) { if ($modelIds = $ids($model)) { $counts[$key] = $model::query()->whereKey($modelIds)->delete(); } }
        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete(); return $counts;
    }

    private function asset(string $name): string { return '/themes/AUTO852/images/'.$name.'.png'; }
    private function record(Model $model): void { ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]); }
}
