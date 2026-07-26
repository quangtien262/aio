<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
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

class Ca0050DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'CA0050';
    private const PRESET_KEY = 'ca0050-sudes-aquarium';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'CA0050 Sudes Aquarium', 'description' => 'Cửa hàng cá cảnh, hồ thủy sinh, phụ kiện và dịch vụ setup với homepage block động.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho CA0050.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $catalogImages = [
                '/theme-demo/ca0050/hero-goldfish.png',
                '/theme-demo/ca0050/aquascape.png',
                '/theme-demo/ca0050/hero-goldfish.png',
            ];
            $image = fn (string $id, int $width = 1000): string => str_contains($id, '152913')
                ? '/theme-demo/ca0050/hero-goldfish.png'
                : (str_contains($id, '148398') ? '/theme-demo/ca0050/aquascape.png' : $catalogImages[abs(crc32($id)) % count($catalogImages)]);
            $categories = [];

            foreach ([['Cá cảnh', 'Cá cảnh khỏe đẹp, đa dạng chủng loại.'], ['Hồ và phụ kiện', 'Hồ, đèn, lọc và phụ kiện thủy sinh.'], ['Cây thủy sinh', 'Cây thủy sinh khỏe mạnh cho mọi layout.']] as $index => [$name, $description]) {
                $category = CatalogCategory::query()->create([
                    'name' => $name, 'slug' => Str::slug('ca0050-'.$name), 'description' => $description,
                    'image_url' => $image(['1434389677669-e08b4cac3105', '1603252110481-7ba873bf42ab', '1622290291468-a28f7a7dc6a8'][$index]),
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($category); $categories[] = $category;
            }

            $products = [
                ['Cá Ba Đuôi Oranda Đuôi Lụa', 999000, 1200000, 0, '1529139574466-a303027c1d8b'], ['Cá Ba Đuôi Lưu Kim Calico', 50000, 69000, 0, '1483985988355-763728e1935b'],
                ['Cá Bảy Màu Koi Đỏ', 12000, 15000, 0, '1529139574466-a303027c1d8b'], ['Cá Bảy Màu Red Albino', 8000, 9000, 0, '1483985988355-763728e1935b'],
                ['Cá Hạc Đỉnh Hồng', 200000, 220000, 0, '1529139574466-a303027c1d8b'], ['Betta trống cá xiêm trống', 49000, 55000, 0, '1483985988355-763728e1935b'],
                ['Hồ cá mini để bàn', 109000, 129000, 1, '1483985988355-763728e1935b'], ['Hồ cá tròn trong suốt', 49000, 59000, 1, '1529139574466-a303027c1d8b'],
                ['Hồ cá để bàn lọc tràn', 269000, 299000, 1, '1483985988355-763728e1935b'], ['Hồ cá Betta decor 360 độ', 404000, 490000, 1, '1529139574466-a303027c1d8b'],
                ['Ráy Nana thủy sinh', 65000, 75000, 2, '1483985988355-763728e1935b'], ['Cỏ thìa thủy sinh', 45000, 55000, 2, '1529139574466-a303027c1d8b'],
            ];

            foreach ($products as $index => [$name, $price, $originalPrice, $categoryIndex, $photo]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('ca0050-'.$name),
                    'sku' => ($categoryIndex === 1 ? 'CA0050-HO-' : 'CA0050-').str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $originalPrice,
                    'stock' => 50, 'short_description' => 'SUDES AQUARIUM', 'detail_content' => '<p>Sản phẩm được tuyển chọn kỹ, hướng dẫn chăm sóc rõ ràng và hỗ trợ kỹ thuật tận tâm.</p>',
                    'image_url' => $image($photo), 'is_featured' => $index < 5, 'is_highlight' => $index < 8, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }

            foreach ([['Thế giới cá cảnh Sudes', 'Khám phá vẻ đẹp sống động của thế giới dưới nước.', '1529139574466-a303027c1d8b'], ['Aquascape xanh mát', 'Kiến tạo không gian thư giãn ngay trong ngôi nhà bạn.', '1483985988355-763728e1935b']] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY, 'placement' => 'ca0050-hero-slider', 'title' => $title, 'subtitle' => $summary,
                    'image_url' => $image($photo, 2200), 'link_url' => '#the-gioi-ca-canh', 'badge' => 'SUDES',
                    'metadata' => ['eyebrow' => 'SUDES AQUARIUM', 'summary' => $summary, 'button_label' => 'Khám phá'],
                    'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($banner);
            }

            $postCategory = CmsCategory::query()->create(['name' => 'Cẩm nang thủy sinh', 'slug' => 'ca0050-cam-nang-thuy-sinh', 'description' => 'Kinh nghiệm nuôi cá, chăm bể và thiết kế thủy sinh.']);
            $this->record($postCategory);
            foreach ([['Cá cảnh nước ngọt và cá cảnh nước mặn có gì khác nhau?', 'So sánh môi trường sống, yêu cầu chăm sóc và vẻ đẹp của từng nhóm cá.'], ['Cách chọn đèn bể thủy sinh cho người mới', 'Ánh sáng phù hợp giúp cây thủy sinh sống khỏe và lên màu đẹp.'], ['Cách diệt rêu tảo hại bể cá', 'Quy trình kiểm soát rêu tảo an toàn và giữ nước trong.'], ['Năm bước setup hồ cá mini tại nhà', 'Hướng dẫn đơn giản để bắt đầu một hồ cá nhỏ khỏe mạnh.']] as $index => [$title, $excerpt]) {
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('ca0050-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p>', 'publish_at' => now()->subDays($index + 1), 'is_highlight' => true]);
                $this->record($post);
            }

            foreach (['Café Rustique', 'Koi Bistro', 'Hikari Sushi', 'Sunny Bakery', 'Hoàng Phúc Coffee', 'Koilands Coffee'] as $index => $name) {
                $partner = CmsPartner::query()->create(['title' => $name, 'slug' => Str::slug('ca0050-'.$name), 'description' => 'Đối tác Sudes Aquarium.', 'image_url' => null, 'image_alt' => $name, 'link_url' => '#top', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'CA0050 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Trang chủ', 'url' => $home], ['label' => 'Giới thiệu', 'url' => $home.'#gioi-thieu'],
                ['label' => 'Sản phẩm', 'url' => $home.'#the-gioi-ca-canh'], ['label' => 'Bộ sưu tập', 'url' => $home.'#setup'],
                ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'], ['label' => 'FAQ', 'url' => $home.'#faq'], ['label' => 'Liên hệ', 'url' => route('site.contact')],
            ]]);
            $this->record($menu);
            $page = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ', 'status' => 'published', 'excerpt' => 'Liên hệ Sudes Aquarium.', 'body' => '<p>Đội ngũ Sudes Aquarium luôn sẵn sàng tư vấn cá cảnh và setup hồ thủy sinh.</p>', 'publish_at' => now()]);
            if ($page->wasRecentlyCreated) $this->record($page);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Sudes Aquarium', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Sudes Aquarium', 'company_description' => 'Cá cảnh, phụ kiện và dịch vụ setup bể thủy sinh trọn gói.', 'support_hotline' => '1900 6750', 'support_email' => 'support@sudes.vn', 'support_location' => '70 Lữ Gia, Phường Phú Thọ, TP.HCM'])])->save();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) $this->record($landing);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 3, 'products' => 12, 'banners' => 2, 'post_categories' => 1, 'posts' => 4, 'partners' => 6, 'pages' => $page->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => ! $existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'post_categories' => 0, 'posts' => 0, 'partners' => 0, 'pages' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        foreach ([[CmsPost::class, 'posts'], [CmsCategory::class, 'post_categories'], [CmsPartner::class, 'partners'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) if ($modelIds = $ids($model)) $counts[$key] = $model::query()->whereKey($modelIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
