<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
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

class Auto851DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'AUTO851';
    private const PRESET_KEY = 'auto851-ohcar-marketplace';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'AUTO851 OH!Car Marketplace', 'description' => 'Sàn mua bán xe và phụ kiện ô tô tông navy vàng.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) { throw new InvalidArgumentException('Preset demo không hợp lệ cho AUTO851.'); }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $vehicleCategory = CatalogCategory::query()->create(['name' => 'Xe ô tô AUTO851', 'slug' => 'auto851-xe-o-to', 'description' => 'Xe mới và xe đã qua sử dụng được kiểm định minh bạch.', 'image_url' => $this->asset('car-1'), 'sort_order' => 0, 'is_active' => true]);
            $accessoryCategory = CatalogCategory::query()->create(['name' => 'Phụ kiện ô tô AUTO851', 'slug' => 'auto851-phu-kien-o-to', 'description' => 'Sản phẩm chăm sóc và bảo dưỡng ô tô.', 'image_url' => $this->asset('accessory-1'), 'sort_order' => 1, 'is_active' => true]);
            $this->record($vehicleCategory); $this->record($accessoryCategory);

            $cars = [
                ['Everest Platinum', 'SUV', 1545000000], ['Nordic XC60 Ultra', 'SUV', 2279000000], ['Lynk One 2024', 'CUV', 999000000], ['Executive Camry 2.0Q', 'Sedan', 1220000000],
                ['GLC 300 Sport Coupe', 'Sport', 2399000000], ['Royal Dawn Cabriolet', 'Sport', 16000000000], ['GT-R Performance', 'Sport', 2600000000], ['CCGT Hypercar', 'Sport', 4000000000],
            ];
            foreach ($cars as $index => [$name, $body, $price]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $vehicleCategory->id, 'name' => $name, 'slug' => Str::slug('auto851-'.$name), 'sku' => 'A851-CAR-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'stock' => 1, 'short_description' => $body, 'detail_content' => '<p>Xe được kiểm định 160 bước, thông tin minh bạch và hỗ trợ đăng ký lái thử.</p>', 'image_url' => $this->asset('car-'.($index + 1)), 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }
            $accessories = [['Phục hồi và làm mới nhựa', 257000], ['Vệ sinh bóng nhanh nội thất', 224000], ['Súc béc xăng chuyên dụng', 205000], ['Nhớt hộp số tự động', 387000], ['Dầu nhớt ô tô cao cấp', 1380000]];
            foreach ($accessories as $index => [$name, $price]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $accessoryCategory->id, 'name' => $name, 'slug' => Str::slug('auto851-'.$name), 'sku' => 'A851-ACC-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'stock' => 20, 'short_description' => 'Cập nhật', 'detail_content' => '<p>Phụ kiện chăm sóc ô tô được tuyển chọn cho nhu cầu sử dụng thực tế.</p>', 'image_url' => $this->asset('accessory-'.($index + 1)), 'is_featured' => true, 'is_highlight' => false, 'sort_order' => 100 + $index, 'is_active' => true]);
                $this->record($product);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Blog và chia sẻ AUTO851', 'slug' => 'auto851-blog-chia-se', 'description' => 'Kiến thức mua bán và sử dụng ô tô.']); $this->record($postCategory);
            foreach ([['Kinh nghiệm vững lái xe an toàn', 'Những lưu ý giúp hành trình tự tin và an toàn hơn.'], ['15 mẹo lái xe dành cho người mới', 'Các thói quen hữu ích để làm chủ chiếc xe mỗi ngày.'], ['Kinh nghiệm bảo dưỡng ô tô định kỳ', 'Những mốc bảo dưỡng quan trọng giúp xe luôn ổn định.']] as $index => [$title, $excerpt]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => $this->asset('car-'.($index + 3)), 'mime_type' => 'image/png', 'size' => 0, 'alt_text' => $title]); $this->record($media);
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('auto851-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p><p>AUTO851 chia sẻ kinh nghiệm thực tế giúp khách hàng an tâm trên mọi hành trình.</p>', 'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]); $this->record($post);
            }
            foreach ([['Anh Trần Hà', 'Mua xe KIA Sonet', 'Quy trình rõ ràng và dễ sử dụng. Chất lượng dịch vụ mang lại cảm giác rất yên tâm.'], ['Bạn Hà Thu', 'Mua xe Mazda 3', 'Thông tin minh bạch, giao dịch xử lý nhanh chóng và chuyên nghiệp.'], ['Anh Đặng Hùng', 'Mua xe Hyundai Santafe', 'Dịch vụ vận hành ổn định và hỗ trợ tốt trong suốt quá trình giao dịch.']] as $index => [$name, $role, $quote]) {
                $testimonial = CmsTestimonial::query()->create(['name' => $name, 'role' => $role, 'company' => 'Khách hàng AUTO851', 'quote' => $quote, 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]); $this->record($testimonial);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'AUTO851 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => $home], ['label' => 'Giới thiệu', 'url' => $home.'#top'], ['label' => 'Mua xe', 'url' => $home.'#mua-xe'], ['label' => 'Bán xe', 'url' => $home.'#ban-xe'], ['label' => 'Phụ kiện ô tô', 'url' => $home.'#phu-kien'], ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'], ['label' => 'Liên hệ', 'url' => route('site.contact')]]]); $this->record($menu);
            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ AUTO851', 'status' => 'published', 'excerpt' => 'Tư vấn mua bán xe và phụ kiện.', 'body' => '<p>Liên hệ AUTO851 để được tư vấn minh bạch và nhanh chóng.</p>', 'publish_at' => now()]); if ($contact->wasRecentlyCreated) { $this->record($contact); }

            $profile = SiteProfile::query()->firstOrNew(); $branding = (array) $profile->branding;
            $branding += ['company_name' => 'AUTO851 OH!Car Marketplace', 'company_description' => 'Nền tảng mua bán ô tô minh bạch, nhanh chóng và đáng tin cậy.', 'slogan' => 'Chinh phục tầm cao mới', 'support_hotline' => '1800 6750', 'support_email' => 'support@htvietnam.vn', 'support_location' => '266 Đội Cấn, Ba Đình, Hà Nội'];
            $profile->forceFill(['site_name' => 'AUTO851 OH!Car Marketplace', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => $branding])->save();

            $websiteKey = $this->siteContext->websiteKey(); $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true); if ($landing && ! $existing) { $this->record($landing); }
            if ($landing) {
                LandingPageBlock::query()->where('landing_page_id', $landing->id)->get()->each(function (LandingPageBlock $block) use ($vehicleCategory, $accessoryCategory): void {
                    $categoryId = $block->block_type === 'auto851_accessories' ? $accessoryCategory->id : (in_array($block->block_type, ['auto851_model_rail', 'auto851_featured_cars'], true) ? $vehicleCategory->id : null);
                    if ($categoryId) { $settings = (array) $block->settings; $settings['category_id'] = $categoryId; $block->forceFill(['settings' => $settings])->save(); }
                });
            }

            return ['preset' => $this->preset(), 'counts' => ['categories' => 2, 'products' => 13, 'post_categories' => 1, 'posts' => 3, 'media' => 3, 'testimonials' => 3, 'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get(); $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = array_fill_keys(['categories', 'products', 'post_categories', 'posts', 'media', 'testimonials', 'pages', 'menus', 'landing_pages'], 0);
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsTestimonial::class, 'testimonials'], [CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus']] as [$model, $key]) { if ($modelIds = $ids($model)) { $counts[$key] = $model::query()->whereKey($modelIds)->delete(); } }
        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete(); return $counts;
    }

    private function asset(string $name): string { return '/themes/AUTO851/images/'.$name.'.png'; }
    private function record(Model $model): void { ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]); }
}
