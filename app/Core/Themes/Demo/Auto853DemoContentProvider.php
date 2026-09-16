<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
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

class Auto853DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'AUTO853';

    private const PRESET_KEY = 'auto853-summit-cycle';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}

    public function themeKey(): string { return self::THEME_KEY; }

    public function defaultPreset(): string { return self::PRESET_KEY; }

    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'AUTO853 Summit Cycle',
            'description' => 'Cửa hàng xe đạp và phụ kiện phong cách thể thao, hiện đại với tông vàng đen.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho AUTO853.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $categoryNames = ['Xe đạp địa hình', 'Xe đạp đường trường', 'Xe đạp điện', 'Xe đạp đô thị', 'Xe đạp đi làm', 'Xe đạp trẻ em'];
            $categories = [];

            foreach ($categoryNames as $index => $name) {
                $category = CatalogCategory::query()->create([
                    'name' => $name,
                    'slug' => Str::slug('auto853-'.$name),
                    'description' => $index === 3 ? '3 sản phẩm' : '19 sản phẩm',
                    'image_url' => $this->asset('bike-'.($index + 1)),
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($category);
                $categories[] = $category;
            }

            $products = [
                ['Summit Trail X9', 0, 32590000, 44990000],
                ['Apex Road R5 Disc', 1, 32590000, 44990000],
                ['Metro Step City', 3, 17838000, 24580000],
                ['Aero Carbon Pro', 1, 38900000, 0],
                ['Gravel Venture 7', 4, 18990000, 22990000],
                ['Terra E-MTB Pro', 2, 26190000, 29990000],
                ['Urban Flow 3', 3, 12990000, 0],
                ['Commuter Daily 2', 4, 15990000, 18990000],
                ['Junior Explorer', 5, 6490000, 0],
                ['Summit Cross 2.0', 2, 25190000, 28990000],
                ['RaceLine SL 6', 1, 42900000, 49900000],
                ['Trail Scout 27.5', 0, 21990000, 25990000],
            ];

            foreach ($products as $index => [$name, $categoryIndex, $price, $originalPrice]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id,
                    'name' => $name,
                    'slug' => Str::slug('auto853-'.$name),
                    'sku' => 'A853-BIKE-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'stock' => 8 + $index,
                    'short_description' => 'Khung nhẹ, vận hành linh hoạt và sẵn sàng cho mọi cung đường.',
                    'detail_content' => '<p>Mẫu xe được tuyển chọn cho hiệu suất ổn định, tư thế thoải mái và trải nghiệm lái đầy cảm hứng.</p>',
                    'image_url' => $this->asset('bike-'.($index + 1)),
                    'is_featured' => true,
                    'is_highlight' => $index < 8,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($product);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang xe đạp',
                'slug' => 'auto853-cam-nang-xe-dap',
                'description' => 'Kiến thức chọn xe, bảo dưỡng và chuẩn bị hành trình.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Top 5 xe đạp thể thao chính hãng không thể bỏ qua', 'Gợi ý các dòng xe nổi bật cho người mới bắt đầu và người đạp lâu năm.', 4],
                ['Hướng dẫn chọn xe đạp đường trường cho người mới', 'Các tiêu chí quan trọng về kích thước khung, tư thế và bộ truyền động.', 5],
                ['Bí quyết chuẩn bị hành trình bikepacking', 'Danh sách trang bị giúp chuyến đi dài ngày nhẹ nhàng và an toàn hơn.', 2],
                ['Bảo dưỡng xe đạp sau chuyến đi dài', 'Những bước vệ sinh và kiểm tra giúp chiếc xe luôn vận hành trơn tru.', 6],
            ];

            foreach ($posts as $index => [$title, $excerpt, $imageIndex]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => '',
                    'file_url' => $this->asset('story-'.$imageIndex),
                    'mime_type' => 'image/png',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);

                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('auto853-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Summit Cycle chia sẻ hướng dẫn thực tế để mỗi chuyến đi luôn tự tin, an toàn và nhiều cảm hứng.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => true,
                ]);
                $this->record($post);
            }

            $home = route('site.home');
            $menu = CmsMenu::query()->create([
                'name' => 'AUTO853 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => $home],
                    ['label' => 'Xe đạp', 'url' => $home.'#danh-muc'],
                    ['label' => 'Phụ tùng xe đạp', 'url' => $home.'#bikepacking'],
                    ['label' => 'Flash Sale', 'url' => $home.'#flash-sale'],
                    ['label' => 'Bộ sưu tập', 'url' => $home.'#san-pham'],
                    ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
            ]);
            $this->record($menu);

            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], [
                'title' => 'Liên hệ Summit Cycle',
                'status' => 'published',
                'excerpt' => 'Tư vấn chọn xe, phụ kiện và lịch bảo dưỡng.',
                'body' => '<p>Gửi nhu cầu của bạn để đội ngũ Summit Cycle tư vấn mẫu xe và trang bị phù hợp.</p>',
                'publish_at' => now(),
            ]);
            if ($contact->wasRecentlyCreated) {
                $this->record($contact);
            }

            $profile = SiteProfile::query()->firstOrNew();
            $branding = (array) $profile->branding;
            $branding += [
                'company_name' => 'SUMMIT CYCLE',
                'company_description' => 'Cửa hàng xe đạp hiệu suất cao, phụ kiện hành trình và dịch vụ đồng hành đáng tin cậy.',
                'slogan' => 'Chinh phục mọi nẻo đường',
                'support_hotline' => '1900 6750',
                'support_email' => 'support@htvietnam.vn',
                'support_location' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM',
            ];
            $profile->forceFill([
                'site_name' => 'Summit Cycle',
                'website_type' => 'ecommerce',
                'active_theme_key' => self::THEME_KEY,
                'branding' => $branding,
            ])->save();

            $websiteKey = $this->siteContext->websiteKey();
            $existing = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($landing && ! $existing) {
                $this->record($landing);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'categories' => 6,
                    'products' => 12,
                    'post_categories' => 1,
                    'posts' => 4,
                    'media' => 4,
                    'pages' => $contact->wasRecentlyCreated ? 1 : 0,
                    'menus' => 1,
                    'landing_pages' => ! $existing && $landing ? 1 : 0,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = array_fill_keys(['categories', 'products', 'post_categories', 'posts', 'media', 'pages', 'menus', 'landing_pages'], 0);

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }

        foreach ([[CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'], [CmsPage::class, 'pages'], [CatalogProduct::class, 'products'], [CatalogCategory::class, 'categories'], [CmsMenu::class, 'menus']] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
        }

        ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->delete();

        return $counts;
    }

    private function asset(string $name): string { return '/themes/AUTO853/images/'.$name.'.png'; }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create([
            'theme_key' => self::THEME_KEY,
            'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ]);
    }
}
