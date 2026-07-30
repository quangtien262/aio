<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
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

class Th0050DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'TH0050';
    private const PRESET_KEY = 'th0050-premium-wellness';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'TH0050 Premium Wellness', 'description' => 'Cửa hàng wellness cao cấp với bộ quà tặng, sản phẩm, câu chuyện thương hiệu và tư vấn.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) throw new InvalidArgumentException('Preset demo không hợp lệ cho TH0050.');

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $category = CatalogCategory::query()->create(['name' => 'Wellness cao cấp', 'slug' => 'th0050-wellness-cao-cap', 'description' => 'Sản phẩm chăm sóc sức khỏe và quà tặng tuyển chọn.', 'image_url' => '/theme-previews/TH0050/quality-th0050.png', 'sort_order' => 0, 'is_active' => true]);
            $this->record($category);

            $definitions = [
                ['Tổ yến tinh chế cao cấp', 2900000, 'Sợi nguyên vẹn, màu sắc tự nhiên.', 'quality-th0050.png'],
                ['Hộp quà wellness thượng hạng', 3200000, 'Món quà trọn vẹn và tinh tế.', 'hero-th0050.png'],
                ['Yến chưng dinh dưỡng', 890000, 'Tiện lợi cho ngày bận rộn.', 'about-th0050.png'],
                ['Set quà doanh nghiệp', 4200000, 'Cá nhân hóa theo chương trình.', 'hero-th0050.png'],
                ['Tổ yến thô tuyển chọn', 2100000, 'Nguồn nguyên liệu được kiểm định.', 'quality-th0050.png'],
                ['Hộp quà tri ân', 1690000, 'Thiết kế thanh lịch cho dịp đặc biệt.', 'hero-th0050.png'],
                ['Tinh chất wellness', 690000, 'Bổ sung năng lượng mỗi ngày.', 'about-th0050.png'],
                ['Bộ quà An Khang', 2500000, 'Gửi trao lời chúc sức khỏe.', 'quality-th0050.png'],
            ];
            foreach ($definitions as $index => [$name, $price, $summary, $image]) {
                $product = CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('th0050-'.$name), 'sku' => 'TH50-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'price' => $price, 'stock' => 30, 'short_description' => $summary, 'detail_content' => '<p>'.$summary.'</p>', 'image_url' => '/theme-previews/TH0050/'.$image, 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true]);
                $this->record($product);
            }

            foreach ([
                ['Tinh hoa chăm sóc sức khỏe', 'Quà tặng cao cấp, nguồn gốc minh bạch và trải nghiệm chỉn chu.', 'hero-th0050.png', '#bo-suu-tap'],
                ['Trao gửi an nhiên trong từng món quà', 'Sự lựa chọn tinh tế cho gia đình, khách hàng và đối tác.', 'about-th0050.png', '#san-pham'],
            ] as $index => [$title, $summary, $image, $url]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'th0050-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => '/theme-previews/TH0050/'.$image, 'link_url' => $url, 'badge' => 'An Nhiên Wellness', 'metadata' => ['eyebrow' => 'An Nhiên Wellness', 'summary' => $summary, 'button_label' => $index === 0 ? 'Khám phá ngay' : 'Xem sản phẩm'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'TH0050 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Bộ sưu tập', 'url' => '#bo-suu-tap'], ['label' => 'Sản phẩm', 'url' => '#san-pham'], ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'], ['label' => 'Tin tức', 'url' => '#tin-tuc'], ['label' => 'Liên hệ', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $currentBranding = (array) $profile->branding;
            $profile->forceFill(['site_name' => $profile->site_name ?: 'An Nhiên Nest', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'An Nhiên Nest', 'company_description' => 'Tinh hoa wellness và quà tặng sức khỏe cao cấp.', 'logo_url' => '', 'support_hotline' => '0399162342', 'support_email' => 'hello@annhien.vn', 'support_location' => 'TP. Hồ Chí Minh'])])->save();

            $generatedBranding = (array) $profile->branding;
            foreach (['logo_url', 'support_hotline', 'support_email'] as $field) {
                if (array_key_exists($field, $currentBranding)) {
                    $generatedBranding[$field] = $currentBranding[$field];
                }
            }
            $profile->forceFill(['branding' => $generatedBranding])->save();

            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existing === null) $this->record($page);

            return ['preset' => $this->preset(), 'counts' => ['categories' => 1, 'products' => 8, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existing === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['categories' => 0, 'products' => 0, 'banners' => 0, 'menus' => 0, 'landing_pages' => 0];
        if ($pageIds = $ids(LandingPage::class)) { $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id'); LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete(); LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete(); LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete(); $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete(); }
        if ($productIds = $ids(CatalogProduct::class)) $counts['products'] = CatalogProduct::query()->whereKey($productIds)->delete();
        if ($categoryIds = $ids(CatalogCategory::class)) $counts['categories'] = CatalogCategory::query()->whereKey($categoryIds)->delete();
        if ($menuIds = $ids(CmsMenu::class)) $counts['menus'] = CmsMenu::query()->whereKey($menuIds)->delete();
        if ($bannerIds = $ids(SiteBanner::class)) $counts['banners'] = SiteBanner::query()->whereKey($bannerIds)->delete();
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();
        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
