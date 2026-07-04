<?php

namespace App\Core\Themes;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ThemeDemoContentGenerator
{
    public function __construct(
        private readonly CmsMenuLocationRegistry $menuLocationRegistry,
    ) {
    }

    public function presets(): array
    {
        return array_map(
            fn (array $preset): array => Arr::only($preset, ['key', 'label', 'description']),
            $this->presetDefinitions(),
        );
    }

    public function servicePresets(): array
    {
        return array_values(array_map(
            fn (array $preset): array => Arr::only($preset, ['key', 'label', 'description', 'company_name']),
            array_filter(
                $this->presetDefinitions(),
                fn (array $preset): bool => ($preset['catalog_style'] ?? 'commerce') === 'service',
            ),
        ));
    }

    public function generate(string $themeKey, string $presetKey): array
    {
        $preset = collect($this->presetDefinitions())->firstWhere('key', $presetKey);

        if (! is_array($preset)) {
            throw new InvalidArgumentException('Preset demo content không hợp lệ.');
        }

        $isServicePreset = ($preset['catalog_style'] ?? 'commerce') === 'service';

        $siteProfile = SiteProfile::query()->first();

        if (! $siteProfile) {
            $siteProfile = new SiteProfile([
                'site_name' => 'AIO Website',
                'website_type' => 'ecommerce',
                'active_theme_key' => $themeKey,
                'is_setup_completed' => false,
                'completed_steps' => [],
                'branding' => [],
            ]);
            $siteProfile->save();
        }

        $timestamp = Carbon::now();

        return DB::transaction(function () use ($preset, $siteProfile, $themeKey, $timestamp, $isServicePreset): array {
            $this->replaceMenuLocations();
            $purged = $this->purgeDemoContent();

            $newsCategory = CmsCategory::query()->create($this->buildDemoNewsCategory($preset, $themeKey));
            $this->recordDemoModel($newsCategory, $themeKey, $preset['key']);

            $pageSlugs = [
                'about' => $this->demoSlug($themeKey, 'gioi-thieu'),
                'contact' => 'contact',
            ];

            $pageCount = $this->seedPages($preset, $timestamp, $themeKey, $pageSlugs);
            $postCount = $this->seedPosts($preset, $newsCategory->id, $timestamp, $themeKey);
            $categoryMap = $this->seedCatalog($preset, $timestamp, $themeKey);
            $menuCount = $this->seedMenus($preset, $categoryMap, $themeKey, $pageSlugs);
            $bannerCount = $this->seedBanners($preset, $themeKey, $timestamp);

            $branding = array_merge((array) $siteProfile->branding, $this->buildDemoBranding($preset, $isServicePreset));

            if ($isServicePreset) {
                $branding['demo_preset_key'] = $preset['key'];
                $branding['demo_preset_label'] = $preset['label'];
                $branding['demo_preset_description'] = $preset['description'];
                $branding['slogan'] = $preset['description'];
            }

            $siteProfile->forceFill([
                'site_name' => $preset['company_name'],
                'website_type' => $isServicePreset ? 'service' : 'ecommerce',
                'active_theme_key' => $themeKey,
                'branding' => $branding,
            ])->save();

            SiteProfile::query()->whereKey($siteProfile->getKey())->update([
                'site_name' => $preset['company_name'],
                'website_type' => $isServicePreset ? 'service' : 'ecommerce',
                'active_theme_key' => $themeKey,
                'branding' => $branding,
            ]);

            return [
                'preset' => Arr::only($preset, ['key', 'label', 'description']),
                'counts' => [
                    'pages' => $pageCount,
                    'posts' => $postCount,
                    'menus' => $menuCount,
                    'catalog_categories' => CatalogCategory::query()->count(),
                    'catalog_products' => CatalogProduct::query()->count(),
                    'banners' => $bannerCount,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(string $themeKey): array
    {
        return DB::transaction(fn (): array => [
            'counts' => $this->purgeDemoContent($themeKey),
        ]);
    }

    private function replaceMenuLocations(): void
    {
        $locations = collect($this->menuLocationRegistry->all())
            ->concat([
                ['label' => 'Primary Navigation', 'value' => 'primary-navigation'],
                ['label' => 'Product Navigation', 'value' => 'product-navigation'],
            ])
            ->unique('value')
            ->values()
            ->all();

        $this->menuLocationRegistry->save($locations);
    }

    private function purgeDemoContent(?string $themeKey = null): array
    {
        $query = ThemeDemoRecord::query();

        if ($themeKey !== null) {
            $query->where('theme_key', $themeKey);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return [
                'banners' => 0,
                'menus' => 0,
                'posts' => 0,
                'pages' => 0,
                'catalog_products' => 0,
                'catalog_categories' => 0,
                'cms_categories' => 0,
            ];
        }

        $deleteMap = [
            SiteBanner::class => ['model' => SiteBanner::class, 'key' => 'banners'],
            CmsMenu::class => ['model' => CmsMenu::class, 'key' => 'menus'],
            CmsPost::class => ['model' => CmsPost::class, 'key' => 'posts'],
            CmsPage::class => ['model' => CmsPage::class, 'key' => 'pages'],
            CatalogProduct::class => ['model' => CatalogProduct::class, 'key' => 'catalog_products'],
            CatalogCategory::class => ['model' => CatalogCategory::class, 'key' => 'catalog_categories'],
            CmsCategory::class => ['model' => CmsCategory::class, 'key' => 'cms_categories'],
        ];

        $counts = array_fill_keys(array_column($deleteMap, 'key'), 0);

        foreach ($deleteMap as $modelType => $config) {
            $ids = $records->where('model_type', $modelType)->pluck('model_id')->all();

            if ($ids === []) {
                continue;
            }

            $counts[$config['key']] = $config['model']::query()->whereKey($ids)->count();
            $config['model']::query()->whereKey($ids)->delete();
        }

        ThemeDemoRecord::query()->whereKey($records->pluck('id')->all())->delete();

        return $counts;
    }

    private function seedPages(array $preset, Carbon $timestamp, string $themeKey, array $pageSlugs): int
    {
        $isServicePreset = ($preset['catalog_style'] ?? 'commerce') === 'service';
        $garmentProfile = $this->garmentPresetProfile($preset);
        $pages = $this->isGarmentFamilyPreset($preset)
            ? [
                [
                    'title' => $this->isFashionPreset($preset) ? 'Về studio thời trang' : 'Về xưởng may',
                    'slug' => $pageSlugs['about'],
                    'excerpt' => $this->isFashionPreset($preset)
                        ? 'Câu chuyện thương hiệu, line bộ sưu tập và nhịp ra mắt seasonal drop của '.$preset['company_name']
                        : 'Hồ sơ năng lực, line sản xuất và quy trình duyệt mẫu của '.$preset['company_name'],
                    'body' => $this->isFashionPreset($preset)
                        ? '<h2>'.$preset['company_name'].'</h2><p>'.$preset['description'].'</p><p>Trang demo này mô phỏng cách TH0002 trình bày câu chuyện thương hiệu, line bộ sưu tập, capsule theo mùa và cách trưng bày lookbook cho retail fashion.</p><p>Hiện line demo nổi bật gồm '.$garmentProfile['about_lines'].', giúp review nhanh cách theme hiển thị section collection, editorial card và điều hướng từ hero sang catalog.</p><p>Sếp có thể thay nội dung này bằng brand story thật, hình campaign, guideline phối đồ và kế hoạch ra mắt collection.</p>'
                        : '<h2>'.$preset['company_name'].'</h2><p>'.$preset['description'].'</p><p>Trang demo này mô phỏng cách TH0002 trình bày năng lực xưởng, line may sỉ lẻ, dịch vụ OEM / ODM và quy trình làm việc với local brand, doanh nghiệp hoặc đại lý.</p><p>Line demo đang ưu tiên '.$garmentProfile['about_lines'].', để test rõ block giới thiệu năng lực, mô tả form dáng và nhịp CTA báo giá cho từng nhóm khách hàng.</p><p>Sếp có thể thay nội dung này bằng hồ sơ xưởng thật, ảnh chuyền may, năng lực in thêu và các mốc lead time sản xuất.</p>',
                ],
                [
                    'title' => $this->isFashionPreset($preset) ? 'Đặt lịch stylist & tư vấn' : 'Gửi yêu cầu may',
                    'slug' => $pageSlugs['contact'],
                    'excerpt' => $this->isFashionPreset($preset)
                        ? 'Kênh nhận lịch hẹn showroom, tư vấn phối đồ, đặt trước capsule và chăm sóc khách hàng retail'
                        : 'Kênh nhận techpack, bảng size, nhu cầu đồng phục và yêu cầu báo giá của khách sỉ lẻ',
                    'body' => $this->isFashionPreset($preset)
                        ? '<h2>Đặt lịch stylist & tư vấn</h2><p>Hotline: 1900 6760</p><p>Email: hello@'.$preset['domain'].'</p><p>Studio / showroom: '.$preset['address'].'</p><p>Hãy gửi trước nhu cầu về form dáng, tông màu, dịp sử dụng hoặc line sản phẩm quan tâm để đội ngũ tư vấn chuẩn bị set đồ phù hợp.</p><p>Preset hiện đang dựng sẵn '.$garmentProfile['contact_focus'].' để đội ngũ admin dễ đổi nhanh thành lịch hẹn showroom, capsule preview hoặc danh sách giữ size theo đợt mở bán.</p>'
                        : '<h2>Gửi yêu cầu may</h2><p>Hotline: 1900 6760</p><p>Email: hello@'.$preset['domain'].'</p><p>Xưởng / showroom: '.$preset['address'].'</p><p>Hãy gửi rõ form dáng, chất liệu, kỹ thuật in thêu, bảng size và số lượng dự kiến để đội ngũ kỹ thuật báo giá nhanh hơn.</p><p>Preset hiện đang dựng sẵn '.$garmentProfile['contact_focus'].' để admin dễ đổi nhanh thành luồng nhận techpack, duyệt mẫu và chốt line sản xuất thật.</p>',
                ],
            ]
            : ($this->isRealEstatePreset($preset)
                ? [
                    [
                        'title' => 'Giới thiệu dự án',
                        'slug' => $pageSlugs['about'],
                        'excerpt' => 'Tổng quan quy mô, vị trí, pháp lý và hệ tiện ích của '.$preset['company_name'],
                        'body' => '<h2>'.$preset['company_name'].'</h2><p>'.$preset['description'].'</p><p>Trang demo này mô phỏng cách LAN0201 trình bày tổng quan dự án, phân khu mở bán, lợi thế vị trí và các điểm nhấn tiện ích trên landing page bất động sản.</p><p>Preset hiện ưu tiên nhóm sản phẩm '.$preset['departments'][0]['name'].', để đội ngũ review nhanh hero, bảng hàng, CTA nhận brochure, lịch hẹn private tour và điều hướng sang listing.</p><p>Sếp có thể thay nội dung này bằng hồ sơ pháp lý, tiến độ thi công, bản đồ kết nối vùng, bộ tài liệu bán hàng và timeline ra hàng thật.</p>',
                    ],
                    [
                        'title' => 'Nhận bảng giá và đặt lịch xem',
                        'slug' => $pageSlugs['contact'],
                        'excerpt' => 'Kênh nhận tư vấn, lịch xem nhà mẫu và bộ tài liệu mở bán của '.$preset['company_name'],
                        'body' => '<h2>Nhận bảng giá và đặt lịch xem</h2><p>Hotline: 1900 6760</p><p>Email: hello@'.$preset['domain'].'</p><p>Sa bàn / nhà mẫu: '.$preset['address'].'</p><p>Hãy gửi rõ loại hình sản phẩm, ngân sách, mục đích mua ở hay đầu tư và khung giờ mong muốn để đội ngũ kinh doanh chuẩn bị bảng hàng phù hợp.</p><p>Preset hiện dựng sẵn luồng nhận brochure, giữ chỗ và hẹn tham quan để admin đổi nhanh sang quy trình bán hàng thật của dự án.</p>',
                    ],
                ]
                : [
                [
                    'title' => $isServicePreset ? 'Giới thiệu nhà xe' : 'Giới thiệu',
                    'slug' => $pageSlugs['about'],
                    'excerpt' => $isServicePreset ? 'Hồ sơ năng lực và đội xe demo cho '.$preset['label'] : 'Hồ sơ năng lực demo cho '.$preset['label'],
                    'body' => $isServicePreset
                        ? '<h2>'.$preset['company_name'].'</h2><p>'.$preset['description'].'</p><p>Website demo này được tạo để review theme '.$preset['theme_flavor'].' và khả năng mapping dữ liệu thật từ CMS, Catalog dịch vụ, bảng giá tham khảo và CTA báo giá nhanh.</p>'
                        : '<h2>'.$preset['company_name'].'</h2><p>'.$preset['description'].'</p><p>Website demo này được tạo để review theme '.$preset['theme_flavor'].' và khả năng mapping dữ liệu thật từ CMS/Catalog.</p>',
                ],
                [
                    'title' => $isServicePreset ? 'Báo giá và liên hệ' : 'Liên hệ',
                    'slug' => $pageSlugs['contact'],
                    'excerpt' => $isServicePreset ? 'Kênh nhận lịch trình, báo giá và CSKH' : 'Kênh liên hệ tư vấn và CSKH',
                    'body' => $isServicePreset
                        ? '<h2>Liên hệ báo giá</h2><p>Hotline: 1900 6760</p><p>Email: hello@'.$preset['domain'].'</p><p>Địa chỉ điều phối: '.$preset['address'].'</p><p>Hãy gửi số khách, điểm đón, điểm đến và ngày đi để được tư vấn nhanh.</p>'
                        : '<h2>Liên hệ tư vấn</h2><p>Hotline: 1900 6760</p><p>Email: hello@'.$preset['domain'].'</p><p>Địa chỉ: '.$preset['address'].'</p>',
                ],
            ]);

        foreach ($pages as $index => $page) {
            $record = CmsPage::query()->create([
                'title' => $page['title'],
                'slug' => $page['slug'],
                'status' => 'published',
                'excerpt' => $page['excerpt'],
                'body' => $page['body'],
                'meta_title' => $page['title'].' | '.$preset['company_name'],
                'meta_description' => $page['excerpt'],
                'template' => 'default',
                'publish_at' => $timestamp->copy()->subDays(10 - $index),
            ]);

            $this->recordDemoModel($record, $themeKey, $preset['key']);
        }

        return count($pages);
    }

    private function seedPosts(array $preset, int $categoryId, Carbon $timestamp, string $themeKey): int
    {
        $titles = ($preset['catalog_style'] ?? 'commerce') === 'service'
            ? [
                'Kinh nghiệm chọn '.$preset['short_label'].' cho nhu cầu thực tế',
                'Checklist cần chuẩn bị trước khi đặt '.$preset['short_label'],
                'Gợi ý tối ưu lịch trình để tiết kiệm chi phí vận hành',
                'Cách xây dựng landing page dịch vụ chuyển đổi tốt hơn',
            ]
            : ($this->isGarmentFamilyPreset($preset)
                ? [
                    ...($this->isFashionPreset($preset)
                        ? [
                            'Lookbook season mới cho line '.$preset['short_label'],
                            'Cách mix capsule retail để tăng giá trị giỏ hàng',
                            'Checklist set up visual merchandising cho drop cuối tuần',
                            'Gợi ý viết landing page collection để tăng tỉ lệ đặt lịch showroom',
                        ]
                        : [
                            'Lookbook capsule mới cho line '.$preset['short_label'],
                            'Checklist duyệt techpack và bảng size trước khi vào chuyền',
                            'Cách chốt chất liệu, in thêu và màu vải cho đơn sỉ lẻ',
                            'Gợi ý trưng bày landing page atelier để tăng lead báo giá',
                        ]),
                ]
                : ($this->isRealEstatePreset($preset)
                    ? [
                        'Checklist chọn căn đẹp trong đợt mở bán đầu tiên',
                        '5 điểm cần hỏi đội sales trước khi giữ chỗ',
                        'Cách đọc bảng giá, chính sách và tiến độ thanh toán nhanh hơn',
                        'Gợi ý triển khai landing page dự án để gom lead hiệu quả',
                    ]
                    : [
                'Top deal mới tuần này cho '.$preset['short_label'],
                '5 xu hướng mua sắm '.$preset['short_label'].' đang tăng mạnh',
                'Gợi ý chọn sản phẩm nổi bật cho chiến dịch cuối tuần',
                'Cách tối ưu landing page bán '.$preset['short_label'].' theo mùa',
            ]));

        $excerpt = ($preset['catalog_style'] ?? 'commerce') === 'service'
            ? 'Nội dung demo cho ngành '.$preset['label'].' nhằm kiểm tra block cẩm nang, trust content và lead-gen của theme.'
            : ($this->isGarmentFamilyPreset($preset)
                ? ($this->isFashionPreset($preset)
                    ? 'Nội dung demo cho mảng thời trang nhằm kiểm tra lookbook, editorial và block collection journal của theme.'
                    : 'Nội dung demo cho xưởng may nhằm kiểm tra lookbook, cẩm nang sản xuất và block tin tức của theme.')
                : ($this->isRealEstatePreset($preset)
                    ? 'Nội dung demo cho dự án mở bán nhằm kiểm tra block cẩm nang, trust content và CTA nhận tư vấn của theme.'
                    : 'Nội dung demo cho ngành '.$preset['label'].' nhằm kiểm tra block tin tức của theme.'));

        $bodyTemplate = ($preset['catalog_style'] ?? 'commerce') === 'service'
            ? '<p>'.$preset['description'].'</p><p>Bài viết demo số %d dùng để hiển thị cẩm nang, kinh nghiệm đặt dịch vụ và nội dung SEO của website.</p>'
            : ($this->isGarmentFamilyPreset($preset)
                ? ($this->isFashionPreset($preset)
                    ? '<p>'.$preset['description'].'</p><p>Bài viết demo số %d dùng để hiển thị lookbook theo mùa, editorial và nội dung retail fashion của website.</p>'
                    : '<p>'.$preset['description'].'</p><p>Bài viết demo số %d dùng để hiển thị lookbook, quy trình duyệt mẫu và nội dung bán sỉ lẻ cho xưởng may.</p>')
                : ($this->isRealEstatePreset($preset)
                    ? '<p>'.$preset['description'].'</p><p>Bài viết demo số %d dùng để hiển thị cẩm nang mở bán, thông tin thị trường và nội dung tư vấn cho website dự án.</p>'
                    : '<p>'.$preset['description'].'</p><p>Bài viết demo số %d dùng để hiển thị tin mới trên website.</p>'));

        foreach ($titles as $index => $title) {
            $record = CmsPost::query()->create([
                'title' => $title,
                'slug' => $this->demoSlug($themeKey, $title),
                'status' => 'published',
                'excerpt' => $excerpt,
                'body' => $this->buildDemoPostBody($preset, $title, $index + 1),
                'meta_title' => $title,
                'meta_description' => $this->isGarmentFamilyPreset($preset)
                    ? ($this->isFashionPreset($preset)
                        ? 'Lookbook, collection journal và visual merchandising demo cho '.$preset['company_name']
                        : 'Lookbook, quy trình duyệt mẫu và nội dung vận hành demo cho '.$preset['company_name'])
                    : 'Tin tức demo cho '.$preset['label'],
                'category_id' => $categoryId,
                'publish_at' => $timestamp->copy()->subDays($index + 1),
            ]);

            $this->recordDemoModel($record, $themeKey, $preset['key']);
        }

        return count($titles);
    }

    /**
     * @return array<int, array{parent: CatalogCategory, children: array<int, CatalogCategory>}>
     */
    private function seedCatalog(array $preset, Carbon $timestamp, string $themeKey): array
    {
        $categoryMap = [];
        $featuredCounter = 0;

        foreach ($preset['departments'] as $parentIndex => $department) {
            $parent = CatalogCategory::query()->create([
                'name' => $department['name'],
                'slug' => Str::slug($preset['key'].'-'.$department['name']),
                'description' => $this->isGarmentFamilyPreset($preset)
                    ? ($this->isFashionPreset($preset)
                        ? 'Line '.$department['name'].' dành cho website demo thời trang, phù hợp để test collection, filter retail và block lookbook.'
                        : 'Line '.$department['name'].' dành cho website demo xưởng may, phù hợp để test nhóm sản phẩm, block lookbook và tư vấn sỉ lẻ.')
                    : 'Danh mục '.$department['name'].' cho preset '.$preset['label'],
                'image_url' => $this->imageUrl($preset['key'].'-cat-'.$parentIndex, 320, 320),
                'sort_order' => $parentIndex,
                'is_active' => true,
            ]);
            $this->recordDemoModel($parent, $themeKey, $preset['key']);

            $children = [];
            foreach ($department['children'] as $childIndex => $childName) {
                $child = CatalogCategory::query()->create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'slug' => Str::slug($preset['key'].'-'.$department['name'].'-'.$childName),
                    'description' => $this->isGarmentFamilyPreset($preset)
                        ? ($this->isFashionPreset($preset)
                            ? 'Nhóm '.$childName.' thuộc line '.$department['name'].', dùng để test form dáng, phối đồ, chất liệu và nội dung retail của TH0002.'
                            : 'Nhóm '.$childName.' thuộc line '.$department['name'].', dùng để test form dáng, chất liệu và nội dung báo giá của TH0002.')
                        : 'Nhóm '.$childName.' thuộc '.$department['name'],
                    'image_url' => $this->imageUrl($preset['key'].'-child-'.$parentIndex.'-'.$childIndex, 320, 320),
                    'sort_order' => $childIndex,
                    'is_active' => true,
                ]);
                $this->recordDemoModel($child, $themeKey, $preset['key']);

                $children[] = $child;

                foreach (range(1, 4) as $productIndex) {
                    $productName = $this->buildProductName($preset, $department['name'], $childName, $productIndex);
                    $price = $this->buildPrice($preset, $parentIndex, $childIndex, $productIndex);
                    $isFeatured = $featuredCounter < 8;
                    $createdAt = $timestamp->copy()->subMinutes(($parentIndex * 10) + ($childIndex * 4) + $productIndex);
                    $isRealEstatePreset = $this->isRealEstatePreset($preset);

                    $product = CatalogProduct::query()->create([
                        'catalog_category_id' => $child->id,
                        'name' => $productName,
                        'slug' => Str::slug($productName),
                        'sku' => $this->buildProductSku($preset, $department['name'], $parentIndex, $childIndex, $productIndex),
                        'price' => $price,
                        'original_price' => $isRealEstatePreset
                            ? $price + (int) round($price * (0.035 + ($productIndex * 0.005)))
                            : $price + (($productIndex + 1) * 150000),
                        'stock' => $isRealEstatePreset
                            ? max(2, 12 - ($childIndex * 2) - $productIndex)
                            : 20 + ($parentIndex * 3) + $productIndex,
                        'short_description' => $this->buildProductShortDescription($preset, $department['name'], $childName),
                        'detail_content' => $this->buildProductDetailContent($preset, $department['name'], $childName, $productName),
                        'highlights' => $this->buildProductHighlights($preset, $department['name'], $childName),
                        'usage_terms' => $this->buildUsageTerms($preset, $department['name']),
                        'usage_location' => $this->buildUsageLocation($preset),
                        'image_url' => $this->productImageUrl($preset, $department['name'], $childName, $parentIndex, $childIndex, $productIndex, 640, 420),
                        'sold_count' => $isRealEstatePreset
                            ? 24 + ($parentIndex * 12) + ($childIndex * 5) + ($productIndex * 4)
                            : 3 + ($parentIndex * 2) + $productIndex,
                        'deal_end_at' => $isRealEstatePreset
                            ? $timestamp->copy()->addDays(5 + $parentIndex + $productIndex)
                            : $timestamp->copy()->addDays(10 + $parentIndex + $productIndex),
                        'is_featured' => $isFeatured,
                        'sort_order' => $productIndex,
                        'is_active' => true,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                    $this->recordDemoModel($product, $themeKey, $preset['key']);

                    foreach ($this->buildGalleryImages($preset, $department['name'], $childName, $parentIndex, $childIndex, $productIndex) as $galleryIndex => $galleryImage) {
                        $product->images()->create([
                            'catalog_product_id' => $product->id,
                            'image_url' => $galleryImage,
                            'sort_order' => $galleryIndex,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                    }

                    if ($isFeatured) {
                        $featuredCounter++;
                    }
                }
            }

            $categoryMap[] = ['parent' => $parent, 'children' => $children];
        }

        return $categoryMap;
    }

    private function seedMenus(array $preset, array $categoryMap, string $themeKey, array $pageSlugs): int
    {
        $isServicePreset = ($preset['catalog_style'] ?? 'commerce') === 'service';
        $productItems = collect($categoryMap)->map(function (array $entry, int $index): array {
            /** @var CatalogCategory $parent */
            $parent = $entry['parent'];

            return [
                'label' => $parent->name,
                'url' => '/danh-muc/'.$parent->slug,
                'target' => '_self',
                'icon' => $index === 0 ? '🔥' : '▣',
                'highlight' => $index === 0,
                'children' => collect($entry['children'])
                    ->map(fn (CatalogCategory $child): array => [
                        'label' => $child->name,
                        'url' => '/danh-muc/'.$child->slug,
                        'target' => '_self',
                    ])
                    ->all(),
            ];
        })->all();

        $primaryMenu = CmsMenu::query()->create([
            'name' => 'Primary Navigation',
            'location' => 'primary-navigation',
            'items' => $this->buildPrimaryMenuItems($preset, $pageSlugs),
        ]);
        $this->recordDemoModel($primaryMenu, $themeKey, $preset['key']);

        $productMenu = CmsMenu::query()->create([
            'name' => 'Product Navigation',
            'location' => 'product-navigation',
            'items' => $productItems,
        ]);
        $this->recordDemoModel($productMenu, $themeKey, $preset['key']);

        return 2;
    }

    private function buildPrimaryMenuItems(array $preset, array $pageSlugs): array
    {
        $isServicePreset = ($preset['catalog_style'] ?? 'commerce') === 'service';

        if (! $isServicePreset) {
            if ($this->isGarmentFamilyPreset($preset)) {
                return [
                    ['label' => 'Lookbook', 'url' => '/c', 'target' => '_self'],
                    ['label' => $this->isFashionPreset($preset) ? 'Về studio' : 'Về xưởng may', 'url' => '/'.$pageSlugs['about'], 'target' => '_self'],
                    ['label' => $this->isFashionPreset($preset) ? 'Đặt lịch stylist' : 'Gửi yêu cầu may', 'url' => '/'.$pageSlugs['contact'], 'target' => '_self'],
                ];
            }

            if ($this->isRealEstatePreset($preset)) {
                return [
                    ['label' => 'Tin thị trường', 'url' => '/c', 'target' => '_self'],
                    ['label' => 'Tổng quan dự án', 'url' => '/'.$pageSlugs['about'], 'target' => '_self'],
                    ['label' => 'Nhận bảng giá', 'url' => '/'.$pageSlugs['contact'], 'target' => '_self'],
                ];
            }

            return [
                ['label' => 'Tin tức', 'url' => '/c', 'target' => '_self'],
                ['label' => 'Giới thiệu', 'url' => '/'.$pageSlugs['about'], 'target' => '_self'],
                ['label' => 'Liên hệ', 'url' => '/'.$pageSlugs['contact'], 'target' => '_self'],
            ];
        }

        return [
            [
                'label' => 'Cẩm nang',
                'url' => '/c',
                'target' => '_self',
                'children' => [
                    ['label' => 'Kinh nghiệm đặt xe', 'summary' => 'Checklist, kinh nghiệm và nội dung SEO cho khách đặt tuyến.', 'url' => '/c', 'target' => '_self'],
                    ['label' => 'Lịch trình tối ưu', 'summary' => 'Gợi ý cách chọn route, loại xe và thời gian khởi hành phù hợp.', 'url' => '/c', 'target' => '_self'],
                ],
            ],
            [
                'label' => 'Giới thiệu',
                'url' => '/'.$pageSlugs['about'],
                'target' => '_self',
                'children' => [
                    ['label' => 'Về nhà xe', 'summary' => 'Tổng quan thương hiệu, năng lực điều phối và đội xe hiện có.', 'url' => '/'.$pageSlugs['about'], 'target' => '_self'],
                    ['label' => 'Quy trình phục vụ', 'summary' => 'Cách tiếp nhận lịch trình, xác nhận chuyến và chăm sóc khách hàng.', 'url' => '/'.$pageSlugs['about'], 'target' => '_self'],
                ],
            ],
            [
                'label' => 'Báo giá',
                'url' => '/'.$pageSlugs['contact'],
                'target' => '_self',
                'children' => [
                    ['label' => 'Gửi yêu cầu báo giá', 'summary' => 'Điền nhu cầu tuyến, số khách và khung giờ để nhận tư vấn nhanh.', 'url' => '/'.$pageSlugs['contact'], 'target' => '_self'],
                    ['label' => 'Liên hệ điều phối', 'summary' => 'Xem thông tin liên hệ và đầu mối hỗ trợ cho từng loại nhu cầu.', 'url' => '/'.$pageSlugs['contact'], 'target' => '_self'],
                ],
            ],
        ];
    }

    private function seedBanners(array $preset, string $themeKey, Carbon $timestamp): int
    {
        $isServicePreset = ($preset['catalog_style'] ?? 'commerce') === 'service';
        $records = [
            [
                'placement' => 'hero-main',
                'title' => $preset['hero_title'],
                'subtitle' => $preset['hero_subtitle'],
                'badge' => $preset['hero_badge'],
                'metadata' => [
                    'eyebrow' => $preset['hero_eyebrow'],
                    'summary' => $preset['description'],
                    'button_label' => $isServicePreset ? 'Nhận báo giá' : ($this->isGarmentFamilyPreset($preset) ? 'Xem bộ sưu tập' : 'Mua ngay'),
                    'button_label' => $isServicePreset ? 'Nhan bao gia' : ($this->isInteriorPreset($preset) ? 'Xem phong cach' : ($this->isGarmentFamilyPreset($preset) ? 'Xem bo suu tap' : 'Mua ngay')),
                ],
                'image_url' => $this->imageUrl($preset['key'].'-hero-main', 960, 520),
                'link_url' => '#featured',
                'sort_order' => 0,
            ],
        ];

        if ($isServicePreset) {
            $records = array_merge($records, $this->buildServiceHeroSliderRecords($preset));
        }

        foreach (array_slice($preset['departments'], 0, 4) as $index => $department) {
            $departmentSlug = Str::slug($preset['key'].'-'.$department['name']);
            $records[] = [
                'placement' => 'hero-side',
                'title' => $department['name'],
                'subtitle' => $isServicePreset
                    ? 'Giải pháp nổi bật cho '.$department['children'][0]
                    : ($this->isGarmentFamilyPreset($preset)
                        ? ($this->isFashionPreset($preset)
                            ? 'Khám phá '.$department['children'][0]
                            : 'Nhận may cho '.$department['children'][0])
                        : 'Ưu đãi mới cho '.$department['children'][0]),
                'subtitle' => $isServicePreset
                    ? 'Giai phap noi bat cho '.$department['children'][0]
                    : ($this->isInteriorPreset($preset)
                        ? 'Goi y moi cho '.$department['children'][0]
                        : ($this->isGarmentFamilyPreset($preset)
                            ? ($this->isFashionPreset($preset) ? 'Kham pha '.$department['children'][0] : 'Nhan may cho '.$department['children'][0])
                            : 'Uu dai moi cho '.$department['children'][0])),
                'badge' => null,
                'metadata' => [],
                'image_url' => $this->imageUrl($preset['key'].'-hero-side-'.$index, 360, 180),
                'link_url' => '/danh-muc/'.$departmentSlug,
                'sort_order' => $index,
            ];
        }

        foreach ($records as $record) {
            $banner = SiteBanner::query()->create([
                'theme_key' => $themeKey,
                'placement' => $record['placement'],
                'title' => $record['title'],
                'subtitle' => $record['subtitle'],
                'image_url' => $record['image_url'],
                'link_url' => $record['link_url'],
                'badge' => $record['badge'],
                'metadata' => $record['metadata'],
                'sort_order' => $record['sort_order'],
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $this->recordDemoModel($banner, $themeKey, $preset['key']);
        }

        return count($records);
    }

    private function buildServiceHeroSliderRecords(array $preset): array
    {
        $images = $this->serviceHeroBannerPool();
        $departments = array_slice($preset['departments'] ?? [], 0, count($images));

        if ($departments === [] || $images === []) {
            return [];
        }

        $records = [];

        foreach ($departments as $index => $department) {
            $departmentName = (string) ($department['name'] ?? 'Dịch vụ');
            $firstChild = (string) ($department['children'][0] ?? 'Lịch trình linh hoạt');
            $departmentSlug = Str::slug($preset['key'].'-'.$departmentName);

            $records[] = [
                'placement' => 'hero-slider',
                'title' => $index === 0
                    ? (string) ($preset['hero_title'] ?? $departmentName)
                    : $departmentName,
                'subtitle' => $index === 0
                    ? (string) ($preset['hero_subtitle'] ?? $preset['description'] ?? '')
                    : 'Giải pháp phù hợp cho '.$firstChild,
                'badge' => $index === 0
                    ? (string) ($preset['hero_badge'] ?? '')
                    : 'Linh hoạt theo nhu cầu',
                'metadata' => [
                    'eyebrow' => $index === 0
                        ? (string) ($preset['hero_eyebrow'] ?? 'Premium booking')
                        : 'Service spotlight',
                    'summary' => $index === 0
                        ? (string) ($preset['description'] ?? '')
                        : 'Tuyến '.$departmentName.' được dựng sẵn để test slider ảnh, overlay nội dung và CTA đặt xe cho theme service.',
                    'button_label' => 'Nhận báo giá',
                    'image_position' => match ($index % 4) {
                        1 => '62% 32%',
                        2 => '58% 34%',
                        3 => '54% 46%',
                        default => '70% center',
                    },
                    'show_caption' => false,
                ],
                'image_url' => $images[$index % count($images)],
                'link_url' => $index === 0 ? '#featured-services' : '/danh-muc/'.$departmentSlug,
                'sort_order' => $index,
            ];
        }

        return $records;
    }

    private function serviceHeroBannerPool(): array
    {
        return [
            '/theme-demo/service/ser0101-slide-01.svg?v=ser0101-20260511-01',
            '/theme-demo/service/ser0101-slide-02.svg?v=ser0101-20260511-01',
        ];
    }

    private function recordDemoModel(Model $model, string $themeKey, string $presetKey): void
    {
        ThemeDemoRecord::query()->updateOrCreate(
            [
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
            ],
            [
                'theme_key' => $themeKey,
                'preset_key' => $presetKey,
            ],
        );
    }

    private function buildProductName(array $preset, string $departmentName, string $childName, int $productIndex): string
    {
        if (($preset['catalog_style'] ?? 'commerce') === 'service') {
            $serviceTiers = ['Tiêu chuẩn', 'Linh hoạt', 'Khứ hồi', 'Doanh nghiệp'];

            return trim(sprintf('%s %s %s', $departmentName, $childName, $serviceTiers[$productIndex - 1] ?? 'Gói'));
        }

        if ($this->isRealEstatePreset($preset)) {
            $unitCodes = ['A1-05', 'A2-12', 'B1-08', 'C2-16'];

            return trim(sprintf('%s %s - Mã căn %s', $departmentName, $childName, $unitCodes[$productIndex - 1] ?? 'A1-01'));
        }

        if ($this->isInteriorPreset($preset)) {
            $lines = ['Oak', 'Linen', 'Nordic', 'Studio'];

            return trim(sprintf('%s %s %s %02d', $childName, $preset['product_prefix'], $lines[$productIndex - 1] ?? 'Home', $productIndex));
        }

        if ($this->isGarmentFamilyPreset($preset)) {
            $lines = $this->isFashionPreset($preset)
                ? ['Edit', 'Studio', 'Runway', 'Capsule']
                : ['Core', 'Studio', 'Workshop', 'Capsule'];

            return trim(sprintf('%s %s %s %02d', $departmentName, $childName, $lines[$productIndex - 1] ?? 'Line', $productIndex));
        }

        $suffixes = ['Pro', 'Max', 'Plus', 'Edition'];

        return trim($childName.' '.$preset['product_prefix'].' '.$suffixes[$productIndex - 1].' '.(64 + ($productIndex * 64)).'GB');
    }

    private function demoSlug(string $themeKey, string $value): string
    {
        return Str::slug('demo-'.$themeKey.'-'.$value);
    }

    private function buildPrice(array $preset, int $parentIndex, int $childIndex, int $productIndex): int
    {
        if (($preset['catalog_style'] ?? 'commerce') === 'service') {
            return 790000 + ($parentIndex * 250000) + ($childIndex * 120000) + ($productIndex * 180000);
        }

        if ($this->isRealEstatePreset($preset)) {
            $basePrices = [2150000000, 3480000000, 5290000000, 12800000000, 18900000000];
            $basePrice = $basePrices[$parentIndex] ?? 4200000000;

            return $basePrice + ($childIndex * 185000000) + ($productIndex * 95000000);
        }

        if ($this->isInteriorPreset($preset)) {
            $basePrices = [5900000, 8900000, 12500000, 3900000, 4900000, 3200000, 6800000, 5200000];
            $basePrice = $basePrices[$parentIndex] ?? 4500000;

            return $basePrice + ($childIndex * 850000) + ($productIndex * 420000);
        }

        return 390000 + ($parentIndex * 170000) + ($childIndex * 80000) + ($productIndex * 45000);
    }

    private function buildProductSku(array $preset, string $departmentName, int $parentIndex, int $childIndex, int $productIndex): string
    {
        if ($this->isRealEstatePreset($preset)) {
            $typeCode = match ($this->normalizePhotoContext($departmentName)) {
                'studio' => 'STD',
                '1pn' => '1PN',
                '2pn' => '2PN',
                'shophouse' => 'SHP',
                'villa' => 'VLA',
                default => 'REA',
            };

            return sprintf('LAN0201-%s-%02d%02d%02d', $typeCode, $parentIndex + 1, $childIndex + 1, $productIndex);
        }

        return strtoupper(Str::slug($preset['key'].'-'.$parentIndex.'-'.$childIndex.'-'.$productIndex, '-'));
    }

    private function buildGalleryImages(array $preset, string $departmentName, string $childName, int $parentIndex, int $childIndex, int $productIndex): array
    {
        return [
            $this->productImageUrl($preset, $departmentName, $childName, $parentIndex, $childIndex, $productIndex, 960, 720, 0),
            $this->productImageUrl($preset, $departmentName, $childName, $parentIndex, $childIndex, $productIndex, 960, 720, 1),
            $this->productImageUrl($preset, $departmentName, $childName, $parentIndex, $childIndex, $productIndex, 960, 720, 2),
            $this->productImageUrl($preset, $departmentName, $childName, $parentIndex, $childIndex, $productIndex, 960, 720, 3),
        ];
    }

    private function productImageUrl(array $preset, string $departmentName, string $childName, int $parentIndex, int $childIndex, int $productIndex, int $width, int $height, int $variantOffset = 0): string
    {
        $pool = $this->productPhotoPool($preset, $departmentName, $childName, $productIndex, $width, $height);

        $index = ($parentIndex * 13) + ($childIndex * 5) + $productIndex + $variantOffset;

        return $pool[$index % count($pool)];
    }

    private function productPhotoPool(array $preset, string $departmentName, string $childName, int $productIndex, int $width, int $height): array
    {
        $catalogStyle = (string) ($preset['catalog_style'] ?? 'commerce');

        if ($this->isGarmentFamilyPreset($preset)) {
            return $this->garmentPhotoPool();
        }

        $normalizedDepartment = $this->normalizePhotoContext($departmentName);
        $normalizedChild = $this->normalizePhotoContext($childName);

        foreach ($this->productPhotoKeywordGroups($catalogStyle, $preset, $normalizedDepartment, $normalizedChild, $productIndex) as $keywords) {
            $pool = $this->keywordPhotoPool($keywords, $width, $height);

            if ($pool !== []) {
                return $pool;
            }
        }

        return $catalogStyle === 'service'
            ? $this->servicePlaceholderPool()
            : $this->commercePlaceholderPool();
    }

    private function productPhotoKeywordGroups(string $catalogStyle, array $preset, string $departmentName, string $childName, int $productIndex): array
    {
        $groups = [];
        $context = trim($departmentName.' '.$childName);
        $phoneVariantKeywords = match ($productIndex) {
            1 => ['smartphone black', 'phone closeup black', 'premium phone black'],
            2 => ['smartphone silver', 'phone closeup silver', 'premium phone silver'],
            3 => ['smartphone blue', 'phone closeup blue', 'premium phone blue'],
            4 => ['smartphone white', 'phone closeup white', 'premium phone white'],
            default => ['smartphone', 'mobile phone', 'device'],
        };

        if (($preset['key'] ?? '') === 'phones-accessories') {
            if (str_contains($departmentName, 'smartphone')) {
                $groups[] = $phoneVariantKeywords;
                $groups[] = ['smartphone', 'mobile phone', 'device'];

                if (str_contains($childName, 'flagship')) {
                    $groups[] = ['premium smartphone', 'smartphone camera', 'android phone'];
                } elseif (str_contains($childName, 'tam trung')) {
                    $groups[] = ['midrange smartphone', 'android phone', 'mobile device'];
                } elseif (str_contains($childName, 'gia tot')) {
                    $groups[] = ['budget smartphone', 'mobile phone', 'android'];
                }
            }

            if (str_contains($departmentName, 'op lung')) {
                $groups[] = ['phone case', 'smartphone case', 'mobile accessories'];
            }

            if (str_contains($departmentName, 'tai nghe')) {
                $groups[] = str_contains($childName, 'over ear')
                    ? ['over-ear headphones', 'headphones', 'audio gear']
                    : ['wireless earbuds', 'earbuds', 'headphones'];
            }

            if (str_contains($departmentName, 'sac')) {
                $groups[] = str_contains($childName, 'khong day')
                    ? ['wireless charger', 'phone charger', 'charging dock']
                    : ['fast charger', 'phone charger', 'usb charger'];
            }

            if (str_contains($departmentName, 'dong ho')) {
                $groups[] = ['smartwatch', 'wearable', 'watch'];
            }

            if (str_contains($departmentName, 'loa mini')) {
                $groups[] = ['bluetooth speaker', 'portable speaker', 'audio'];
            }

            if (str_contains($departmentName, 'thiet bi ghi hinh')) {
                $groups[] = ['gimbal camera', 'camera accessory', 'content creator'];
            }

            if (str_contains($departmentName, 'bao hanh')) {
                $groups[] = ['smartphone repair', 'phone service', 'device care'];
            }

            if (str_contains($departmentName, 'may cu')) {
                $groups[] = ['used smartphone', 'refurbished phone', 'mobile phone'];
            }

            if (str_contains($departmentName, 'phu kien xe')) {
                $groups[] = ['car phone holder', 'car charger', 'car accessory'];
            }
        }

        if ($catalogStyle === 'service') {
            if (str_contains($departmentName, '16 cho')) {
                $groups[] = ['minibus', '16 seater bus', 'tour van'];
            } elseif (str_contains($departmentName, '29 cho')) {
                $groups[] = ['coach bus', 'tour bus', 'bus charter'];
            } elseif (str_contains($departmentName, '45 cho')) {
                $groups[] = ['large coach bus', 'charter bus', 'tour coach'];
            } elseif (str_contains($departmentName, 'san bay')) {
                $groups[] = ['airport transfer', 'shuttle bus', 'van transport'];
            } elseif (str_contains($departmentName, 'city transfer')) {
                $groups[] = ['city transfer', 'shuttle van', 'transport service'];
            } else {
                $groups[] = ['bus transport', 'shuttle service', 'van'];
            }
        } elseif ($this->isInteriorPreset($preset)) {
            $groups[] = match (true) {
                str_contains($context, 'sofa') || str_contains($context, 'phong khach') || str_contains($context, 'ban tra') || str_contains($context, 'ke tv') => ['modern living room', 'sofa interior', 'home interior'],
                str_contains($context, 'phong ngu') || str_contains($context, 'giuong') || str_contains($context, 'tu ao') => ['modern bedroom', 'bedroom furniture', 'interior bedroom'],
                str_contains($context, 'ban an') || str_contains($context, 'bep') || str_contains($context, 'ghe an') => ['dining room furniture', 'modern dining room', 'kitchen decor'],
                str_contains($context, 'den') || str_contains($context, 'decor') || str_contains($context, 'tham') || str_contains($context, 'tranh') => ['interior decor', 'lamp interior', 'home decor'],
                str_contains($context, 'lam viec') || str_contains($context, 'ergonomic') || str_contains($context, 'ke ho so') => ['home office interior', 'desk setup', 'modern workspace'],
                str_contains($context, 'ban cong') || str_contains($context, 'san vuon') || str_contains($context, 'chau cay') => ['balcony furniture', 'outdoor furniture', 'patio decor'],
                str_contains($context, 'vat lieu') || str_contains($context, 'san go') || str_contains($context, 'gach') || str_contains($context, 'tam op') => ['interior material', 'wood floor', 'tile texture'],
                str_contains($context, 'phong tam') || str_contains($context, 'lavabo') || str_contains($context, 'sen voi') || str_contains($context, 'tu guong') => ['modern bathroom', 'bathroom fixture', 'interior bathroom'],
                default => ['home interior', 'showroom interior', 'modern furniture'],
            };
        } elseif ($this->isRealEstatePreset($preset)) {
            $groups[] = match (true) {
                str_contains($context, 'studio') => ['studio apartment', 'show flat interior', 'project launch studio'],
                str_contains($context, '1pn') => ['1 bedroom apartment', 'apartment interior', 'project launch condo'],
                str_contains($context, '2pn') => ['2 bedroom apartment', 'family apartment interior', 'project launch condo'],
                str_contains($context, 'villa') || str_contains($context, 'biet thu') => ['real estate villa', 'luxury villa', 'project launch villa'],
                str_contains($context, 'shophouse') || str_contains($context, 'thuong mai') => ['real estate shophouse', 'mixed use building', 'project launch storefront'],
                str_contains($context, 'townhouse') || str_contains($context, 'lien ke') => ['real estate townhouse', 'row house project', 'project launch townhouse'],
                str_contains($context, 'penthouse') => ['real estate penthouse', 'luxury apartment', 'project launch penthouse'],
                default => ['real estate apartment', 'model home interior', 'project launch condo'],
            };
        } elseif ($groups === []) {
            $groups[] = match (true) {
                str_contains($context, 'dien thoai') || str_contains($context, 'android') || str_contains($context, 'iphone') || str_contains($context, 'gaming phone') => ['smartphone', 'mobile phone', 'device'],
                str_contains($context, 'laptop') || str_contains($context, 'ultrabook') || str_contains($context, 'workstation') => ['laptop', 'notebook computer', 'workspace'],
                str_contains($context, 'may tinh bang') || str_contains($context, 'tablet') || str_contains($context, 'ipad') => ['tablet device', 'ipad tablet', 'mobile screen'],
                str_contains($context, 'phu kien') || str_contains($context, 'thiet bi mang') => ['tech accessories', 'gadget', 'electronics'],
                str_contains($context, 'am thanh') || str_contains($context, 'tv') || str_contains($context, 'giai tri') => ['speaker', 'headphones', 'audio'],
                str_contains($context, 'camera') => ['camera', 'lens', 'recording gear'],
                str_contains($context, 'resort') || str_contains($context, 'tour') || str_contains($context, 'du lich') || str_contains($context, 've vui choi') || str_contains($context, 'du thuyen') || str_contains($context, 'team building') || str_contains($context, 'visa') || str_contains($context, 'city stay') || str_contains($context, 'bien') || str_contains($context, 'nui') => ['resort room', 'hotel stay', 'travel resort'],
                str_contains($context, 'spa') || str_contains($context, 'massage') || str_contains($context, 'wellness') || str_contains($context, 'detox') => ['spa treatment', 'massage spa', 'wellness'],
                str_contains($context, 'am thuc') || str_contains($context, 'buffet') || str_contains($context, 'hai san') || str_contains($context, 'cafe') => ['restaurant food', 'buffet', 'dining'],
                str_contains($context, 'my pham') || str_contains($context, 'cham soc da') || str_contains($context, 'trang diem') || str_contains($context, 'nuoc hoa') || str_contains($context, 'cham soc toc') || str_contains($context, 'body care') || str_contains($context, 'danh cho nam') || str_contains($context, 'thuc pham dep da') => ['skincare product', 'beauty serum', 'cosmetics'],
                str_contains($context, 'thiet bi ve sinh') || str_contains($context, 'nha tam') || str_contains($context, 'lavabo') || str_contains($context, 'sen voi') => ['bathroom fixture', 'sink faucet', 'interior bathroom'],
                str_contains($context, 'gach op lat') || str_contains($context, 'phong khach') || str_contains($context, 'noi that') || str_contains($context, 'cua') || str_contains($context, 'san') || str_contains($context, 'ngoai that') || str_contains($context, 'den trang tri') => ['home interior', 'showroom interior', 'kitchen decor'],
                str_contains($context, 'gaming') => ['gaming gear', 'keyboard mouse', 'pc accessory'],
                default => [],
            };
        }

        return array_values(array_filter($groups, fn (array $keywords): bool => $keywords !== []));
    }

    private function keywordPhotoPool(array $keywords, int $width, int $height): array
    {
        $curated = $this->curatedPhotoPoolForKeywords($keywords, $width, $height);

        if ($curated !== []) {
            return $curated;
        }

        $keywordString = implode(' ', $keywords);

        if (preg_match('/bus|coach|shuttle|minibus|transport/', $keywordString) === 1) {
            return $this->servicePlaceholderPool();
        }

        return $this->commercePlaceholderPool();
    }

    private function curatedPhotoPoolForKeywords(array $keywords, int $width, int $height): array
    {
        $keywordString = implode(' ', $keywords);

        return match (true) {
            preg_match('/studio apartment|show flat interior|project launch studio/', $keywordString) === 1
                => $this->realEstateStudioPhotoPool($width, $height),
            preg_match('/1 bedroom apartment|apartment interior/', $keywordString) === 1
                => $this->realEstateOneBedroomPhotoPool($width, $height),
            preg_match('/2 bedroom apartment|family apartment interior/', $keywordString) === 1
                => $this->realEstateTwoBedroomPhotoPool($width, $height),
            preg_match('/shophouse|storefront|mixed use building/', $keywordString) === 1
                => $this->realEstateShophousePhotoPool($width, $height),
            preg_match('/villa/', $keywordString) === 1
                => $this->realEstateVillaPhotoPool($width, $height),
            preg_match('/smartphone|mobile phone|android phone|premium phone|used smartphone|refurbished phone|device/', $keywordString) === 1
                => $this->smartphonePhotoPool(),
            preg_match('/phone case|smartphone case|mobile accessories/', $keywordString) === 1
                => $this->phoneCasePhotoPool(),
            preg_match('/charger|charging dock|usb charger|car charger/', $keywordString) === 1
                => $this->chargerPhotoPool(),
            preg_match('/smartwatch|wearable|watch/', $keywordString) === 1
                => $this->wearablePhotoPool(),
            preg_match('/bluetooth speaker|portable speaker/', $keywordString) === 1
                => $this->speakerPhotoPool(),
            preg_match('/headphones|earbuds|audio gear|speaker|bluetooth speaker|portable speaker/', $keywordString) === 1
                => $this->audioPhotoPool(),
            preg_match('/car phone holder|tech accessories/', $keywordString) === 1
                => $this->techAccessoryPhotoPool(),
            preg_match('/camera|gimbal|recording gear|content creator/', $keywordString) === 1
                => $this->cameraGearPhotoPool(),
            preg_match('/laptop|notebook computer|workspace/', $keywordString) === 1
                => $this->laptopPhotoPool(),
            preg_match('/tablet|ipad|mobile screen/', $keywordString) === 1
                => $this->tabletPhotoPool(),
            preg_match('/resort|hotel stay|travel resort|hotel room/', $keywordString) === 1
                => $this->resortPhotoPool(),
            preg_match('/spa|massage spa|wellness/', $keywordString) === 1
                => $this->spaPhotoPool(),
            preg_match('/restaurant food|buffet|dining/', $keywordString) === 1
                => $this->buffetPhotoPool(),
            preg_match('/skincare|beauty serum|cosmetics|beauty/', $keywordString) === 1
                => $this->beautyPhotoPool(),
            preg_match('/bathroom fixture|sink faucet|interior bathroom|bathroom/', $keywordString) === 1
                => $this->bathroomPhotoPool(),
            preg_match('/home interior|showroom interior|kitchen decor/', $keywordString) === 1
                => $this->interiorPhotoPool(),
            preg_match('/real estate|apartment|condo|villa|townhouse|shophouse|model home|project launch/', $keywordString) === 1
                => $this->realEstatePhotoPool(),
            preg_match('/bus|coach|shuttle|van transport|minibus|airport transfer|transport service/', $keywordString) === 1
                => $this->busPhotoPool(),
            default => [],
        };
    }

    private function localAssetPool(array $relativePaths): array
    {
        return array_map(fn (string $path): string => url('theme-demo/curated/'.$path), $relativePaths);
    }

    private function smartphonePhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/smartphones/phone-01.jpg',
            'phones/smartphones/phone-02.jpg',
            'phones/smartphones/phone-03.jpg',
            'phones/smartphones/phone-04.jpg',
            'phones/smartphones/phone-05.jpg',
        ]);
    }

    private function phoneCasePhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/cases/case-01.jpg',
            'phones/cases/case-02.jpg',
            'phones/cases/case-03.jpg',
            'phones/cases/case-04.jpg',
            'phones/cases/case-05.jpg',
        ]);
    }

    private function chargerPhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/chargers/charger-01.jpg',
            'phones/chargers/charger-02.jpg',
            'phones/chargers/charger-03.jpg',
            'phones/chargers/charger-04.jpg',
            'phones/chargers/charger-05.jpg',
        ]);
    }

    private function audioPhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/audio/audio-01.jpg',
            'phones/audio/audio-02.jpg',
            'phones/audio/audio-03.jpg',
            'phones/audio/audio-04.jpg',
            'phones/audio/audio-05.jpg',
        ]);
    }

    private function techAccessoryPhotoPool(): array
    {
        return array_values(array_merge($this->phoneCasePhotoPool(), $this->chargerPhotoPool()));
    }

    private function wearablePhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/watches/watch-01.jpg',
            'phones/watches/watch-02.jpg',
            'phones/watches/watch-03.jpg',
            'phones/watches/watch-04.jpg',
            'phones/watches/watch-05.jpg',
        ]);
    }

    private function speakerPhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/speakers/speaker-01.jpg',
            'phones/speakers/speaker-02.jpg',
            'phones/speakers/speaker-03.jpg',
            'phones/speakers/speaker-04.jpg',
            'phones/speakers/speaker-05.jpg',
        ]);
    }

    private function cameraGearPhotoPool(): array
    {
        return $this->localAssetPool([
            'phones/camera/camera-01.jpg',
            'phones/camera/camera-02.jpg',
            'phones/camera/camera-03.jpg',
        ]);
    }

    private function laptopPhotoPool(): array
    {
        return $this->localAssetPool([
            'commerce/laptops/laptop-01.jpg',
            'commerce/laptops/laptop-02.jpg',
            'commerce/laptops/laptop-03.jpg',
            'commerce/laptops/laptop-04.jpg',
            'commerce/laptops/laptop-05.jpg',
        ]);
    }

    private function tabletPhotoPool(): array
    {
        return $this->localAssetPool([
            'commerce/tablets/tablet-01.jpg',
            'commerce/tablets/tablet-02.jpg',
            'commerce/tablets/tablet-03.jpg',
            'commerce/tablets/tablet-04.jpg',
            'commerce/tablets/tablet-05.jpg',
        ]);
    }

    private function resortPhotoPool(): array
    {
        return $this->localAssetPool([
            'travel/resorts/resort-01.jpg',
            'travel/resorts/resort-02.jpg',
            'travel/resorts/resort-03.jpg',
            'travel/resorts/resort-04.jpg',
            'travel/resorts/resort-05.jpg',
        ]);
    }

    private function spaPhotoPool(): array
    {
        return $this->localAssetPool([
            'wellness/spa/spa-01.jpg',
            'wellness/spa/spa-02.jpg',
            'wellness/spa/spa-03.jpg',
            'wellness/spa/spa-04.jpg',
            'wellness/spa/spa-05.jpg',
        ]);
    }

    private function buffetPhotoPool(): array
    {
        return $this->localAssetPool([
            'food/buffet/buffet-01.jpg',
            'food/buffet/buffet-02.jpg',
            'food/buffet/buffet-03.jpg',
            'food/buffet/buffet-04.jpg',
            'food/buffet/buffet-05.jpg',
        ]);
    }

    private function beautyPhotoPool(): array
    {
        return $this->localAssetPool([
            'beauty/skincare/beauty-01.jpg',
            'beauty/skincare/beauty-02.jpg',
            'beauty/skincare/beauty-03.jpg',
            'beauty/skincare/beauty-04.jpg',
            'beauty/skincare/beauty-05.jpg',
        ]);
    }

    private function bathroomPhotoPool(): array
    {
        return $this->localAssetPool([
            'home/bathroom/bathroom-01.jpg',
            'home/bathroom/bathroom-02.jpg',
            'home/bathroom/bathroom-03.jpg',
            'home/bathroom/bathroom-04.jpg',
            'home/bathroom/bathroom-05.jpg',
        ]);
    }

    private function interiorPhotoPool(): array
    {
        return array_values(array_merge($this->resortPhotoPool(), $this->bathroomPhotoPool()));
    }

    private function realEstatePhotoPool(): array
    {
        return $this->localAssetPool([
            'real-estate/projects/project-01.svg',
            'real-estate/projects/project-02.svg',
            'real-estate/projects/project-03.svg',
            'real-estate/projects/project-04.svg',
            'real-estate/projects/project-05.svg',
        ]);
    }

    private function realEstateStudioPhotoPool(int $width, int $height): array
    {
        return $this->unsplashPhotoPool([
            'photo-1505693416388-ac5ce068fe85',
            'photo-1484154218962-a197022b5858',
            'photo-1502672260266-1c1ef2d93688',
        ], $width, $height);
    }

    private function realEstateOneBedroomPhotoPool(int $width, int $height): array
    {
        return $this->unsplashPhotoPool([
            'photo-1494526585095-c41746248156',
            'photo-1484154218962-a197022b5858',
            'photo-1502672260266-1c1ef2d93688',
        ], $width, $height);
    }

    private function realEstateTwoBedroomPhotoPool(int $width, int $height): array
    {
        return $this->unsplashPhotoPool([
            'photo-1502672260266-1c1ef2d93688',
            'photo-1494526585095-c41746248156',
            'photo-1505693416388-ac5ce068fe85',
        ], $width, $height);
    }

    private function realEstateShophousePhotoPool(int $width, int $height): array
    {
        return $this->unsplashPhotoPool([
            'photo-1600047509807-ba8f99d2cdde',
            'photo-1600566753086-00f18fb6b3ea',
            'photo-1600607687644-c7171b42498f',
        ], $width, $height);
    }

    private function realEstateVillaPhotoPool(int $width, int $height): array
    {
        return $this->unsplashPhotoPool([
            'photo-1512917774080-9991f1c4c750',
            'photo-1564013799919-ab600027ffc6',
            'photo-1600585154526-990dced4db0d',
            'photo-1600607687939-ce8a6c25118c',
            'photo-1600585152915-d208bec867a1',
        ], $width, $height);
    }

    private function unsplashPhotoPool(array $photoIds, int $width, int $height): array
    {
        return array_map(
            fn (string $photoId): string => sprintf('https://images.unsplash.com/%s?auto=format&fit=crop&w=%d&h=%d&q=80', $photoId, $width, $height),
            $photoIds,
        );
    }

    private function busPhotoPool(): array
    {
        return $this->localAssetPool([
            'transport/buses/bus-01.jpg',
            'transport/buses/bus-02.jpg',
            'transport/buses/bus-03.jpg',
            'transport/buses/bus-04.jpg',
            'transport/buses/bus-05.jpg',
            'transport/buses/bus-06.jpg',
        ]);
    }

    private function commercePlaceholderPool(): array
    {
        return $this->localAssetPool(['placeholders/commerce-generic.svg']);
    }

    private function servicePlaceholderPool(): array
    {
        return $this->localAssetPool(['placeholders/service-generic.svg']);
    }

    private function normalizePhotoContext(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        return (string) $normalized;
    }

    private function buildProductHighlights(array $preset, string $departmentName, string $childName): string
    {
        if ($this->isInteriorPreset($preset)) {
            return implode(PHP_EOL, [
                'Thiet ke cho nhom '.$childName.' thuoc khong gian '.$departmentName.', phu hop test card san pham, mega menu va goi y theo phong cua TH0020.',
                'Noi dung tap trung vao vat lieu, kich thuoc, mau sac va kha nang phoi combo trong can ho hoac nha pho.',
                'Co the chinh truc tiep de doi tu demo sang catalog noi that that, kem thong tin giao lap va bao hanh vat lieu.',
            ]);
        }

        if (($preset['catalog_style'] ?? 'commerce') === 'service') {
            return implode(PHP_EOL, [
                'Phù hợp cho nhu cầu '.$departmentName.' với cấu hình '.$childName.'.',
                'Dùng để test block dịch vụ, bảng giá tham khảo và CTA báo giá nhanh của theme '.$preset['company_name'].'.',
                'Có thể chỉnh trực tiếp để biến từ data demo sang gói dịch vụ thực tế cho nhà xe.',
            ]);
        }

        if ($this->isGarmentFamilyPreset($preset)) {
            return implode(PHP_EOL, [
                $this->isFashionPreset($preset)
                    ? 'Dòng '.$childName.' thuộc nhóm '.$departmentName.', phù hợp để test retail collection, styling card và seasonal capsule.'
                    : 'Dòng '.$childName.' thuộc nhóm '.$departmentName.', phù hợp cả đơn sỉ theo bộ size và đơn retail theo mẫu có sẵn.',
                $this->isFashionPreset($preset)
                    ? 'Dùng để test block gallery, form dáng, chất liệu, editorial note và visual merchandising của theme '.$preset['company_name'].'.'
                    : 'Dùng để test block gallery, form dáng, chất liệu, thông số in thêu và luồng đặt may của theme '.$preset['company_name'].'.',
                $this->isFashionPreset($preset)
                    ? 'Có thể chỉnh trực tiếp để chuyển từ data demo sang line collection vận hành thật cho studio thời trang.'
                    : 'Có thể chỉnh trực tiếp để chuyển từ data demo sang mẫu vận hành thật cho xưởng may.',
            ]);
        }

        if ($this->isRealEstatePreset($preset)) {
            return implode(PHP_EOL, [
                'Sản phẩm '.$childName.' thuộc phân khu '.$departmentName.', dùng để test card mở bán, banner ưu đãi và trang chi tiết listing cho LAN0201.',
                'Tập trung vào mã căn, vị trí, tiến độ mở bán và CTA nhận bảng giá để đội sales chuyển đổi lead nhanh hơn.',
                'Có thể chỉnh trực tiếp để chuyển từ data demo sang bảng hàng thật cho dự án đang mở bán.',
            ]);
        }

        return implode(PHP_EOL, [
            'Ưu đãi nổi bật cho nhóm '.$childName.' thuộc ngành '.$departmentName.'.',
            'Phù hợp để test bố cục deal nhiều khối như banner, card và trang detail.',
            'Có thể dùng ngay để review gallery nhiều ảnh và nội dung dài của theme '.$preset['company_name'].'.',
        ]);
    }

    private function buildUsageTerms(array $preset, string $departmentName): string
    {
        if ($this->isInteriorPreset($preset)) {
            return implode(PHP_EOL, [
                'Gia co the thay doi theo vat lieu, kich thuoc, mau hoan thien va khoang cach giao lap cua nhom '.$departmentName.'.',
                'Khuyen nghi xac nhan kich thuoc phong, loi di thang may va mat bang lap dat truoc khi chot don.',
                'Vui long cung cap anh khong gian, so do kich thuoc hoac nhu cau phoi combo de showroom tu van nhanh hon.',
                'Mot so san pham can dat coc hoac hen lich giao lap rieng khi chon mau vat lieu theo yeu cau.',
            ]);
        }

        if (($preset['catalog_style'] ?? 'commerce') === 'service') {
            return implode(PHP_EOL, [
                'Giá trên là mức tham khảo, có thể thay đổi theo lịch trình, thời gian và điểm đón thực tế.',
                'Khuyến nghị liên hệ trước để xác nhận lịch xe, loại xe và chi phí phát sinh cho nhóm '.$departmentName.'.',
                'Vui lòng cung cấp rõ ngày đi, số khách, điểm đón và điểm đến để được báo giá nhanh.',
                'Một số gói dịch vụ cần đặt cọc hoặc xác nhận trước với điều phối viên của '.$preset['company_name'].'.',
            ]);
        }

        if ($this->isGarmentFamilyPreset($preset)) {
            return implode(PHP_EOL, [
                $this->isFashionPreset($preset)
                    ? 'Giá có thể thay đổi theo chất liệu, line bộ sưu tập, phụ kiện styling và chương trình bán theo mùa của nhóm '.$departmentName.'.'
                    : 'Báo giá có thể thay đổi theo chất liệu, số lượng, kỹ thuật in thêu và yêu cầu hoàn thiện của nhóm '.$departmentName.'.',
                $this->isFashionPreset($preset)
                    ? 'Khuyến nghị chốt size run, màu chủ đạo và nhịp trưng bày trước khi mở bán collection.'
                    : 'Khuyến nghị chốt bảng size, màu vải và số lượng theo từng màu trước khi xác nhận đơn.',
                $this->isFashionPreset($preset)
                    ? 'Vui lòng ghi rõ nhu cầu phối đồ, dịp sử dụng hoặc line collection quan tâm để showroom tư vấn nhanh hơn.'
                    : 'Vui lòng gửi rõ techpack, mẫu tham chiếu hoặc form dáng mong muốn để xưởng báo giá nhanh hơn.',
                $this->isFashionPreset($preset)
                    ? 'Một số line capsule số lượng giới hạn cần đặt giữ size hoặc xác nhận lịch thử đồ trước.'
                    : 'Một số đơn OEM / ODM cần làm mẫu và đặt cọc trước khi vào chuyền sản xuất.',
            ]);
        }

        if ($this->isRealEstatePreset($preset)) {
            return implode(PHP_EOL, [
                'Bảng giá mang tính tham khảo và có thể thay đổi theo đợt mở bán, vị trí căn và chính sách ưu đãi của phân khu '.$departmentName.'.',
                'Khuyến nghị liên hệ phòng kinh doanh để xác nhận bảng giá, phương án thanh toán và sản phẩm còn hàng trước khi giữ chỗ.',
                'Vui lòng cung cấp mã căn, nhu cầu ở hoặc đầu tư và ngân sách dự kiến để đội ngũ tư vấn gợi ý nhanh hơn.',
                'Một số sản phẩm cần xác nhận lịch xem nhà mẫu và ký xác nhận thông tin trước khi đặt cọc.',
            ]);
        }

        return implode(PHP_EOL, [
            'Thời hạn ưu đãi linh hoạt theo chiến dịch của '.$preset['company_name'].'.',
            'Khuyến nghị liên hệ trước để xác nhận tình trạng áp dụng cho nhóm '.$departmentName.'.',
            'Vui lòng cung cấp mã SKU khi cần CSKH xử lý đơn hoặc hậu mãi nhanh.',
            'Không áp dụng đồng thời với các chương trình giảm giá nội bộ khác nếu không có ghi chú riêng.',
        ]);
    }

    private function buildUsageLocation(array $preset): string
    {
        if ($this->isRealEstatePreset($preset)) {
            return implode(PHP_EOL, [
                'Sa ban va nha mau: '.$preset['address'],
                'Phong kinh doanh: '.$preset['company_name'],
                'Hotline: 1900 6760',
                'Email: hello@'.$preset['domain'],
            ]);
        }

        return implode(PHP_EOL, [
            $preset['company_name'],
            $preset['address'],
            'Hotline: 1900 6760',
            'Email: hello@'.$preset['domain'],
        ]);
    }

    private function buildDemoPostBody(array $preset, string $title, int $index): string
    {
        if (($preset['catalog_style'] ?? 'commerce') === 'service') {
            return '<p>'.$preset['description'].'</p><p>Bài viết demo số '.$index.' dùng để hiển thị cẩm nang, kinh nghiệm đặt dịch vụ và nội dung SEO của website.</p>';
        }

        if ($this->isGarmentFamilyPreset($preset)) {
            $profile = $this->garmentPresetProfile($preset);

            return $this->isFashionPreset($preset)
                ? '<p>'.$preset['description'].'</p><p>Bài viết này xoay quanh '.$profile['post_theme'].', giúp test block lookbook, editorial card và section bài viết theo mùa của TH0002.</p><p>Nội dung seed đang gợi ý '.$profile['post_focus'].' để admin đổi nhanh thành bài campaign, collection note hoặc lịch mở bán thật.</p>'
                : '<p>'.$preset['description'].'</p><p>Bài viết này xoay quanh '.$profile['post_theme'].', giúp test block lookbook, cẩm nang sản xuất và section quy trình của TH0002.</p><p>Nội dung seed đang gợi ý '.$profile['post_focus'].' để admin đổi nhanh thành bài duyệt mẫu, báo giá line may hoặc checklist giao hàng thật.</p>';
        }

        return '<p>'.$preset['description'].'</p><p>Bài viết demo số '.$index.' dùng để hiển thị tin mới trên website.</p>';
    }

    private function garmentPresetProfile(array $preset): array
    {
        if ($this->isFashionPreset($preset)) {
            return [
                'about_lines' => 'new season capsule, ready-to-wear nữ và lookbook set theo dịp sử dụng',
                'contact_focus' => 'lịch stylist cho capsule mới, line ready-to-wear và lookbook set theo mùa',
                'post_theme' => 'lookbook season, capsule retail và cách set visual merchandising cho showroom',
                'post_focus' => 'editorial note cho từng drop, caption cho lookbook card và CTA đặt lịch thử đồ',
            ];
        }

        return [
            'about_lines' => 'đồng phục doanh nghiệp, local brand capsule và line OEM / ODM',
            'contact_focus' => 'luồng nhận techpack cho đồng phục, capsule local brand và đơn OEM / ODM',
            'post_theme' => 'duyệt techpack, chốt chất liệu và tối ưu luồng may sỉ lẻ theo từng line',
            'post_focus' => 'checklist size run, brief in thêu và mốc duyệt mẫu trước khi vào chuyền',
        ];
    }

    private function garmentLineProfile(array $preset, string $departmentName, string $childName): array
    {
        $department = $this->normalizePhotoContext($departmentName);
        $child = $this->normalizePhotoContext($childName);

        if ($this->isFashionPreset($preset)) {
            return match (true) {
                str_contains($department, 'new season capsule') => [
                    'category_focus' => 'seasonal drop mở bán nhanh',
                    'audience_phrase' => 'khách theo dõi drop mới và capsule giới hạn',
                    'focus_phrase' => 'phom lên outfit theo capsule và màu chủ đạo theo mùa',
                    'ops_phrase' => 'tag collection, visual trưng bày và lịch mở bán cuối tuần',
                    'closing_phrase' => 'tag capsule cùng note styling cho từng outfit',
                    'detail_intro' => 'seasonal drop, phối outfit theo capsule và cảm hứng lookbook',
                ],
                str_contains($department, 'ready to wear nu') => [
                    'category_focus' => 'retail nữ theo phom everyday đến occasion wear',
                    'audience_phrase' => 'khách nữ mua retail theo dịp đi làm và đi tiệc',
                    'focus_phrase' => 'phom blazer, dress và wide-leg để lên set đồ hoàn chỉnh',
                    'ops_phrase' => 'size run, bảng màu và gợi ý phối phụ kiện tại showroom',
                    'closing_phrase' => 'size run rõ cùng ghi chú phối phụ kiện',
                    'detail_intro' => 'ready-to-wear nữ, cân bằng giữa phom dáng ứng dụng và visual showroom',
                ],
                str_contains($department, 'ready to wear nam') => [
                    'category_focus' => 'retail nam theo line casual và smart everyday',
                    'audience_phrase' => 'khách nam cần set đồ gọn, dễ phối và dễ upsell',
                    'focus_phrase' => 'boxy shirt, relaxed pants và light outerwear theo set',
                    'ops_phrase' => 'gợi ý mix-match, trưng bày theo set và CTA đặt lịch thử đồ',
                    'closing_phrase' => 'set phối sẵn cùng CTA thử đồ nhanh',
                    'detail_intro' => 'ready-to-wear nam, tối ưu set phối và trải nghiệm thử tại showroom',
                ],
                str_contains($department, 'lookbook sets') => [
                    'category_focus' => 'bộ set hoàn chỉnh để chốt đơn theo outfit',
                    'audience_phrase' => 'khách mua theo outfit trọn bộ và concept sẵn',
                    'focus_phrase' => 'cách ghép monotone set, weekend set và office set lên landing page',
                    'ops_phrase' => 'ảnh campaign, card combo và nội dung visual merchandising',
                    'closing_phrase' => 'combo lookbook và CTA giữ set theo dịp',
                    'detail_intro' => 'lookbook set hoàn chỉnh, ưu tiên câu chuyện outfit và combo sản phẩm',
                ],
                str_contains($department, 'accessories') => [
                    'category_focus' => 'styling item hỗ trợ upsell theo outfit',
                    'audience_phrase' => 'khách cần phụ kiện chốt nhanh theo look hoàn chỉnh',
                    'focus_phrase' => 'canvas tote, cap và belt để tăng giá trị outfit',
                    'ops_phrase' => 'cross-sell theo set đồ và vị trí trưng bày cạnh main item',
                    'closing_phrase' => 'cross-sell phụ kiện theo từng look',
                    'detail_intro' => 'styling accessory, tăng giá trị giỏ hàng qua các điểm chạm trưng bày',
                ],
                default => [
                    'category_focus' => 'retail fashion theo collection và chất liệu',
                    'audience_phrase' => 'khách mua retail theo moodboard và dịp sử dụng',
                    'focus_phrase' => 'chất liệu, phom dáng và story của từng line '.$child,
                    'ops_phrase' => 'editorial note, tag collection và CTA đặt lịch showroom',
                    'closing_phrase' => 'story collection cùng tag chất liệu',
                    'detail_intro' => 'retail fashion theo line riêng, nhấn vào chất liệu và moodboard của collection',
                ],
            };
        }

        return match (true) {
            str_contains($department, 'dong phuc doanh nghiep') => [
                'category_focus' => 'đồng bộ nhận diện cho doanh nghiệp và đội ngũ vận hành',
                'audience_phrase' => 'doanh nghiệp cần đồng bộ hình ảnh theo bộ size và màu thương hiệu',
                'focus_phrase' => 'chất vải bền, bảng size tập thể và kỹ thuật in thêu logo',
                'ops_phrase' => 'duyệt mẫu nhanh, chốt size run và timeline giao đồng phục theo đợt',
                'closing_phrase' => 'bảng size tập thể cùng checklist duyệt logo',
                'detail_intro' => 'đồng phục doanh nghiệp, bám sát nhận diện thương hiệu và tiến độ bàn giao theo đội nhóm',
            ],
            str_contains($department, 'local brand capsule') => [
                'category_focus' => 'capsule cho local brand cần lên hàng nhanh và giữ chất riêng',
                'audience_phrase' => 'local brand chạy drop nhỏ cần giữ phom và chất liệu ổn định',
                'focus_phrase' => 'form dáng capsule, bảng màu giới hạn và nhịp lên mẫu theo drop',
                'ops_phrase' => 'duyệt fit, chốt wash/in và quản lý số lượng theo từng đợt mở bán',
                'closing_phrase' => 'fit sample cùng timeline lên drop',
                'detail_intro' => 'capsule collection cho local brand, ưu tiên tốc độ ra mẫu và độ ổn định qua từng drop',
            ],
            str_contains($department, 'oem odm') => [
                'category_focus' => 'phát triển mẫu riêng cho brand hoặc đại lý',
                'audience_phrase' => 'brand cần đối tác OEM / ODM có thể nhận brief và hoàn thiện mẫu',
                'focus_phrase' => 'techpack cơ bản, mẫu thử nhanh và private label theo tiêu chuẩn riêng',
                'ops_phrase' => 'tiếp nhận brief, dựng BOM, duyệt mẫu và chốt line sản xuất',
                'closing_phrase' => 'brief sản phẩm cùng mốc duyệt mẫu',
                'detail_intro' => 'OEM / ODM theo brief riêng, tập trung vào quy trình nhận techpack và kiểm soát mẫu trước sản xuất',
            ],
            str_contains($department, 'phu lieu va hoan thien') => [
                'category_focus' => 'điểm chạm hoàn thiện để tăng giá trị thành phẩm',
                'audience_phrase' => 'xưởng hoặc brand cần hoàn thiện in thêu, tem nhãn đồng bộ',
                'focus_phrase' => 'in lụa, thêu vi tính và tem nhãn để hoàn thiện trải nghiệm mặc',
                'ops_phrase' => 'test thông số in, vị trí thêu và checklist đóng gói cuối chuyền',
                'closing_phrase' => 'thông số hoàn thiện và checklist đóng gói',
                'detail_intro' => 'khâu hoàn thiện sau may, bám vào chất lượng in thêu và độ nhất quán tem nhãn',
            ],
            default => [
                'category_focus' => 'line may mặc theo form dáng và chất liệu',
                'audience_phrase' => 'khách sỉ lẻ cần mẫu may rõ form và dễ chốt số lượng',
                'focus_phrase' => 'form dáng, chất liệu và khả năng lặp lại chất lượng giữa các size',
                'ops_phrase' => 'bảng size, duyệt mẫu và timeline vào chuyền',
                'closing_phrase' => 'form dáng rõ cùng bảng size dễ chốt',
                'detail_intro' => 'line may mặc vận hành thực tế, ưu tiên mô tả rõ form dáng, chất liệu và cách lên chuyền',
            ],
        };
    }

    private function buildProductShortDescription(array $preset, string $departmentName, string $childName): string
    {
        if ($this->isRealEstatePreset($preset)) {
            return sprintf(
                'Bảng hàng %s thuộc phân khu %s, ưu tiên thông tin vị trí, chính sách mở bán và CTA đặt lịch xem nhà mẫu để đội sales chuyển đổi lead nhanh.',
                $childName,
                $departmentName,
            );
        }

        if ($this->isInteriorPreset($preset)) {
            return sprintf(
                'Mau %s cho nhom %s, tap trung vat lieu, kich thuoc, cach phoi phong va kha nang giao lap cho khong gian song that.',
                $childName,
                $departmentName,
            );
        }

        if (! $this->isGarmentFamilyPreset($preset)) {
            return 'Mẫu demo cho '.$childName.' trong preset '.$preset['label'].'.';
        }

        $lineProfile = $this->garmentLineProfile($preset, $departmentName, $childName);

        return $this->isFashionPreset($preset)
            ? sprintf(
                'Line %s được dựng theo hướng %s, ưu tiên %s và %s để admin dễ chỉnh thành collection retail thật.',
                $childName,
                $lineProfile['category_focus'],
                $lineProfile['focus_phrase'],
                $lineProfile['closing_phrase'],
            )
            : sprintf(
                'Line %s dành cho %s, nhấn vào %s và %s để admin dễ chuyển thành mẫu vận hành thật.',
                $childName,
                $lineProfile['audience_phrase'],
                $lineProfile['focus_phrase'],
                $lineProfile['closing_phrase'],
            );
    }

    private function buildProductDetailContent(array $preset, string $departmentName, string $childName, string $productName): string
    {
        if ($this->isInteriorPreset($preset)) {
            return implode(PHP_EOL.PHP_EOL, [
                $productName.' la du lieu demo duoc sinh cho preset '.$preset['label'].', giup kiem thu homepage TH0020, trang danh muc va trang chi tiet san pham noi that.',
                'Mau nay thuoc nhom '.$departmentName.' voi dong '.$childName.', nen noi dung dai tap trung vao vat lieu, kich thuoc, ty le phong va cach phoi cung cac san pham lien quan.',
                'Ban seed nay uu tien trai nghiem mua that: goi y showroom, tu van phoi phong, giao lap theo lich va thong tin bao hanh vat lieu de admin co the doi thanh catalog van hanh that.',
                'Co the sua truc tiep mo ta, gallery anh, quy cach, kich thuoc va ghi chu lap dat trong admin Catalog de bien trang demo thanh noi dung thuong mai dien tu noi that hoan chinh.',
            ]);
        }

        if (($preset['catalog_style'] ?? 'commerce') === 'service') {
            return implode(PHP_EOL.PHP_EOL, [
                $productName.' là gói dịch vụ demo được sinh cho preset '.$preset['label'].', giúp kiểm thử homepage service, trang danh mục và trang chi tiết dịch vụ của bộ theme service.',
                'Gói này thuộc nhóm '.$departmentName.' với cấu hình '.$childName.', nên nội dung được viết theo hướng báo giá tham khảo, năng lực vận hành và lưu ý khi đặt xe hoặc điều phối chuyến.',
                'Sếp có thể chỉnh lại mô tả, bảng giá, gallery ảnh xe, nội dung sử dụng và CTA để biến gói demo thành nội dung thật cho doanh nghiệp vận tải.',
            ]);
        }

        if ($this->isGarmentFamilyPreset($preset)) {
            $lineProfile = $this->garmentLineProfile($preset, $departmentName, $childName);

            return implode(PHP_EOL.PHP_EOL, [
                $productName.' là dữ liệu demo được sinh cho preset '.$preset['label'].', giúp kiểm thử homepage may mặc, trang danh mục và trang chi tiết sản phẩm của TH0002.',
                $this->isFashionPreset($preset)
                    ? 'Mẫu này thuộc nhóm '.$departmentName.' với cấu hình '.$childName.', nên nội dung dài được viết theo hướng '.$lineProfile['detail_intro'].' cùng nhịp trưng bày, phối chất liệu và visual merchandising.'
                    : 'Mẫu này thuộc nhóm '.$departmentName.' với cấu hình '.$childName.', nên nội dung dài được viết theo hướng '.$lineProfile['detail_intro'].' cùng mô tả form dáng, chất liệu, MOQ và quy cách hoàn thiện.',
                $this->isFashionPreset($preset)
                    ? 'Bản seed đang nhấn vào '.$lineProfile['focus_phrase'].' và '.$lineProfile['ops_phrase'].', để Sếp có thể đổi nhanh sang nội dung collection, editorial note hoặc chính sách đặt giữ size.'
                    : 'Bản seed đang nhấn vào '.$lineProfile['focus_phrase'].' và '.$lineProfile['ops_phrase'].', để Sếp có thể đổi nhanh sang hồ sơ đồng phục, capsule bán sỉ hoặc line OEM / ODM thực tế.',
                $this->isFashionPreset($preset)
                    ? 'Sếp có thể sửa trực tiếp mô tả, gallery ảnh, bảng giá, tag bộ sưu tập và ghi chú chất liệu trong admin Catalog để biến trang từ demo thành nội dung vận hành thật.'
                    : 'Sếp có thể sửa trực tiếp mô tả, gallery ảnh, bảng giá, số lượng và thông tin duyệt mẫu trong admin Catalog để biến trang từ demo thành nội dung vận hành thật.',
            ]);
        }

        if ($this->isRealEstatePreset($preset)) {
            return implode(PHP_EOL.PHP_EOL, [
                $productName.' là dữ liệu demo được sinh cho preset '.$preset['label'].', giúp kiểm thử homepage dự án, trang bảng hàng và trang chi tiết listing của LAN0201.',
                'Sản phẩm này thuộc nhóm '.$departmentName.' với cấu hình '.$childName.', nên nội dung dài được viết theo hướng giới thiệu vị trí, lợi thế phân khu, chính sách thanh toán và CTA nhận tư vấn.',
                'Bản seed đang nhấn vào thông tin mở bán, phương án giữ chỗ và lịch xem nhà mẫu để Sếp có thể đổi nhanh sang nội dung dự án thật trong admin Catalog.',
                'Sếp có thể sửa trực tiếp mô tả, gallery ảnh, bảng giá, mã căn, ghi chú chính sách và thông tin liên hệ để biến trang từ demo thành nội dung vận hành thật.',
            ]);
        }

        return implode(PHP_EOL.PHP_EOL, [
            $productName.' là dữ liệu demo được sinh cho preset '.$preset['label'].', giúp kiểm thử đầy đủ luồng hiển thị trang chi tiết sản phẩm theo phong cách deal page.',
            'Sản phẩm thuộc nhóm '.$childName.' trong ngành '.$departmentName.', vì vậy phần nội dung dài được thiết kế để hiển thị đẹp ở các block mô tả, điều kiện sử dụng và vị trí áp dụng trên theme TH0001.',
            'Sếp có thể sửa trực tiếp phần mô tả này, gallery ảnh, số lượng đã mua và thời gian kết thúc deal trong admin Catalog để biến trang từ demo thành nội dung vận hành thật.',
        ]);
    }

    private function imageUrl(string $seed, int $width, int $height): string
    {
        if (Str::startsWith($seed, 'ser-')) {
            return $this->serviceImageUrl($seed);
        }

        if (Str::contains($seed, 'real-estate-launchpad')) {
            return $this->realEstateImageUrl($seed);
        }

        if (Str::contains($seed, 'garment-workshop') || Str::contains($seed, 'fashion-studio')) {
            return $this->garmentImageUrl($seed);
        }

        return sprintf('https://picsum.photos/seed/%s/%d/%d', $seed, $width, $height);
    }

    private function serviceImageUrl(string $seed): string
    {
        if (Str::contains($seed, 'hero-main')) {
            return url('theme-demo/service/service-hero-main.svg');
        }

        if (Str::contains($seed, 'hero-side')) {
            $index = (abs(crc32($seed)) % 2) + 1;

            return url('theme-demo/service/service-banner-'.$index.'.svg');
        }

        $index = (abs(crc32($seed)) % 4) + 1;

        return url('theme-demo/service/service-card-'.$index.'.svg');
    }

    private function garmentImageUrl(string $seed): string
    {
        if (Str::contains($seed, 'hero-main')) {
            return url('theme-demo/garment/hero-main.svg');
        }

        if (Str::contains($seed, 'hero-side')) {
            $index = (abs(crc32($seed)) % 2) + 1;

            return url('theme-demo/garment/hero-side-'.$index.'.svg');
        }

        $index = (abs(crc32($seed)) % 5) + 1;

        return url('theme-demo/garment/product-0'.$index.'.svg');
    }

    private function realEstateImageUrl(string $seed): string
    {
        if (Str::contains($seed, 'hero-main')) {
            return url('theme-demo/real-estate/hero-main.svg');
        }

        if (Str::contains($seed, 'hero-side')) {
            $index = (abs(crc32($seed)) % 2) + 1;

            return url('theme-demo/real-estate/hero-side-'.$index.'.svg');
        }

        $index = (abs(crc32($seed)) % 5) + 1;

        return url('theme-demo/curated/real-estate/projects/project-0'.$index.'.svg');
    }

    private function buildDemoBranding(array $preset, bool $isServicePreset): array
    {
        $branding = [
            'company_name' => $preset['company_name'],
            'support_hotline' => '1900 6760',
            'support_email' => 'hello@'.$preset['domain'],
            'support_location' => $preset['address'],
            'demo_preset_key' => $preset['key'],
            'demo_preset_label' => $preset['label'],
            'demo_preset_description' => $preset['description'],
            'slogan' => $isServicePreset
                ? $preset['description']
                : ($this->isGarmentFamilyPreset($preset)
                    ? 'May sỉ lẻ, duyệt mẫu nhanh và chốt line sản xuất rõ tiến độ'
                    : 'Demo commerce preset cho '.$preset['company_name']),
        ];

        if ($isServicePreset) {
            $branding['logo_url'] = url('theme-demo/service/brand-mark.svg');
            $branding['favicon_url'] = url('theme-demo/service/brand-mark.svg');
        } elseif ($this->isGarmentFamilyPreset($preset)) {
            $branding['logo_url'] = url('theme-demo/garment/brand-mark.svg');
            $branding['favicon_url'] = url('theme-demo/garment/brand-mark.svg');
        } elseif ($this->isRealEstatePreset($preset)) {
            $branding['logo_url'] = url('theme-demo/real-estate/brand-mark.svg');
            $branding['favicon_url'] = url('theme-demo/real-estate/brand-mark.svg');
            $branding['support_hotline'] = '1900 0201';
        }

        return $branding;
    }

    private function isGarmentPreset(array $preset): bool
    {
        return ($preset['industry_family'] ?? null) === 'garment' && ($preset['garment_profile'] ?? 'workshop') !== 'fashion';
    }

    private function isGarmentFamilyPreset(array $preset): bool
    {
        return ($preset['industry_family'] ?? null) === 'garment';
    }

    private function isFashionPreset(array $preset): bool
    {
        return ($preset['garment_profile'] ?? null) === 'fashion';
    }

    private function isRealEstatePreset(array $preset): bool
    {
        return ($preset['industry_family'] ?? null) === 'real-estate';
    }

    private function isInteriorPreset(array $preset): bool
    {
        return ($preset['industry_family'] ?? null) === 'interior';
    }

    private function buildDemoNewsCategory(array $preset, string $themeKey): array
    {
        if ($this->isGarmentFamilyPreset($preset)) {
            return [
                'name' => $this->isFashionPreset($preset) ? 'Lookbook & editorial' : 'Lookbook & nhat ky xuong',
                'slug' => Str::slug('lookbook-'.$preset['key']),
                'description' => $this->isFashionPreset($preset)
                    ? 'Chuyen muc demo cho lookbook, bo suu tap theo mua va cap nhat visual merchandising cua TH0002.'
                    : 'Chuyen muc demo cho lookbook, quy trinh duyet mau va cap nhat van hanh cua TH0002.',
                'meta_title' => $this->isFashionPreset($preset)
                    ? 'Lookbook thời trang '.$preset['company_name']
                    : 'Lookbook xưởng may '.$preset['company_name'],
                'meta_description' => $this->isFashionPreset($preset)
                    ? 'Lookbook, bộ sưu tập theo mùa và nội dung visual merchandising demo cho '.$preset['company_name']
                    : 'Lookbook, quy trình duyệt mẫu và tin vận hành demo cho '.$preset['company_name'],
            ];
        }

        if ($this->isRealEstatePreset($preset)) {
            return [
                'name' => 'Tin mở bán & cẩm nang',
                'slug' => Str::slug('tin-mo-ban-'.$preset['key']),
                'description' => 'Chuyên mục demo cho tin thị trường, hướng dẫn chọn căn và cập nhật mở bán của LAN0201.',
                'meta_title' => 'Tin mở bán '.$preset['company_name'],
                'meta_description' => 'Tin thị trường, thông tin mở bán và bài viết tư vấn demo cho '.$preset['company_name'],
            ];
        }

        if ($this->isInteriorPreset($preset)) {
            return [
                'name' => 'Y tuong khong gian',
                'slug' => Str::slug('y-tuong-khong-gian-'.$preset['key']),
                'description' => 'Chuyen muc demo cho goi y phoi phong, vat lieu va cach chon noi that cua TH0020.',
                'meta_title' => 'Y tuong noi that '.$preset['company_name'],
                'meta_description' => 'Y tuong phoi phong, vat lieu va bai viet tu van demo cho '.$preset['company_name'],
            ];
        }

        return [
            'name' => 'Tin '.$preset['short_label'],
            'slug' => Str::slug('tin-'.$preset['key']),
            'description' => 'Chuyên mục cập nhật nội dung demo cho theme '.$themeKey,
            'meta_title' => 'Tin tức '.$preset['label'],
            'meta_description' => 'Tin tức và nội dung demo cho '.$preset['label'],
        ];
    }

    private function presetDefinitions(): array
    {
        return [
            [
                'key' => 'real-estate-launchpad',
                'label' => 'Bất động sản mở bán dự án',
                'short_label' => 'bất động sản',
                'description' => 'Preset cho LAN0201 theo hướng landing dự án mở bán, bảng hàng listing, CTA nhận brochure và đặt lịch xem nhà mẫu.',
                'company_name' => 'LAN0201 Project Landing',
                'domain' => 'lan0201landing.demo',
                'address' => 'Nhà mẫu ven sông, Thủ Đức, TP.HCM',
                'theme_flavor' => 'project launch landing',
                'hero_eyebrow' => 'Dự án mở bán',
                'hero_title' => 'Bảng hàng mở bán và ưu đãi cho dự án đang ra mắt',
                'hero_subtitle' => 'Dùng để test LAN0201 với hero landing, listing bất động sản, pricing ribbon, timeline mở bán và CTA nhận bảng giá.',
                'hero_badge' => 'Nhận bảng giá và brochure mới nhất',
                'product_prefix' => 'Căn',
                'industry_family' => 'real-estate',
                'departments' => [
                    ['name' => 'Studio', 'children' => ['Ban công đông nam', 'View sông', 'Nội thất liền tường']],
                    ['name' => '1PN', 'children' => ['Layout tối ưu', 'View công viên', 'Có phòng đa năng']],
                    ['name' => '2PN', 'children' => ['Căn góc 2 WC', 'View hồ bơi', 'Logia kép']],
                    ['name' => 'Shophouse', 'children' => ['Mặt tiền đại lộ', 'Gần quảng trường', 'Hai mặt thoáng']],
                    ['name' => 'Villa', 'children' => ['Đơn lập sân vườn', 'Song lập ven kênh', 'Hoàng hôn bên sông']],
                ],
            ],
            [
                'key' => 'garment-workshop',
                'label' => 'Xưởng may quần áo sỉ lẻ',
                'short_label' => 'xưởng may',
                'description' => 'Preset cho xưởng may quần áo phục vụ cả bán sỉ và bán lẻ, nhận OEM / ODM, đồng phục và local brand capsule.',
                'company_name' => 'TH0002 Atelier Works',
                'domain' => 'th0002atelier.demo',
                'address' => 'Cụm công nghiệp may mặc Tân Bình, TP.HCM',
                'theme_flavor' => 'garment workshop commerce',
                'hero_eyebrow' => 'Xưởng may theo yêu cầu',
                'hero_title' => 'May sỉ lẻ theo form dáng riêng cho thương hiệu của bạn',
                'hero_subtitle' => 'Dùng để test hero lookbook, sidebar ngành may mặc, sản phẩm catalog và luồng chốt đơn theo size, chất liệu, tiến độ sản xuất.',
                'hero_badge' => 'MOQ từ 30 sản phẩm',
                'product_prefix' => 'Atelier',
                'industry_family' => 'garment',
                'garment_profile' => 'workshop',
                'departments' => [
                    ['name' => 'Đồng phục doanh nghiệp', 'children' => ['Polo công ty', 'Sơ mi đồng phục', 'Áo khoác team']],
                    ['name' => 'Local brand capsule', 'children' => ['Oversize tee', 'Hoodie', 'Pants basic']],
                    ['name' => 'Retail may sẵn', 'children' => ['Áo thun', 'Sơ mi', 'Quần casual']],
                    ['name' => 'Thời trang học đường', 'children' => ['Áo lớp', 'Đồng phục học sinh', 'Áo sự kiện']],
                    ['name' => 'Workwear và cafe', 'children' => ['Tạp dề', 'Áo bếp', 'Đồng phục phục vụ']],
                    ['name' => 'OEM / ODM', 'children' => ['Techpack cơ bản', 'Mẫu thử nhanh', 'Private label']],
                    ['name' => 'Phụ liệu và hoàn thiện', 'children' => ['In lụa', 'Thêu vi tính', 'Tem nhãn']],
                    ['name' => 'Đơn sỉ đại lý', 'children' => ['Pack size S-XL', 'Màu cơ bản', 'Hàng lên chuyền nhanh']],
                ],
            ],
            [
                'key' => 'fashion-studio',
                'label' => 'Thời trang lookbook và retail',
                'short_label' => 'thời trang',
                'description' => 'Preset cho mảng thời trang retail, capsule collection và visual merchandising, vẫn dùng chung contract quản trị của TH0002.',
                'company_name' => 'TH0002 Maison Edit',
                'domain' => 'th0002maison.demo',
                'address' => 'Studio retail concept, Quận 1, TP.HCM',
                'theme_flavor' => 'fashion studio commerce',
                'hero_eyebrow' => 'New season lookbook',
                'hero_title' => 'Bộ sưu tập retail với nhịp lookbook, capsule và line theo mùa',
                'hero_subtitle' => 'Dùng để test hero editorial, category line-up, sản phẩm retail và bố cục visual merchandising trên TH0002.',
                'hero_badge' => 'Drop mới mỗi tuần',
                'product_prefix' => 'Maison',
                'industry_family' => 'garment',
                'garment_profile' => 'fashion',
                'departments' => [
                    ['name' => 'New season capsule', 'children' => ['Oversize tee', 'Cropped shirt', 'Pleated skirt']],
                    ['name' => 'Ready-to-wear nữ', 'children' => ['Blazer', 'Dress', 'Wide-leg pants']],
                    ['name' => 'Ready-to-wear nam', 'children' => ['Boxy shirt', 'Relaxed pants', 'Light jacket']],
                    ['name' => 'Essentials daily', 'children' => ['Tank top', 'Straight jeans', 'Basic knit']],
                    ['name' => 'Party & statement', 'children' => ['Sequin top', 'Slip dress', 'Structured blazer']],
                    ['name' => 'Streetwear edit', 'children' => ['Hoodie', 'Cargo pants', 'Varsity jacket']],
                    ['name' => 'Accessories & styling', 'children' => ['Canvas tote', 'Cap', 'Belt']],
                    ['name' => 'Lookbook sets', 'children' => ['Monotone set', 'Weekend set', 'Office set']],
                ],
            ],
            [
                'key' => 'electronics-superstore',
                'label' => 'Điện máy công nghệ',
                'short_label' => 'điện máy',
                'description' => 'Preset tập trung vào điện thoại, laptop, thiết bị gia dụng và phụ kiện công nghệ để test theme ecommerce nhiều block.',
                'company_name' => 'AIO Tech Market',
                'domain' => 'aiotechmarket.demo',
                'address' => '332 Lũy Bán Bích, Tân Phú, TP.HCM',
                'theme_flavor' => 'deal điện máy tốc độ cao',
                'hero_eyebrow' => 'Flash sale công nghệ',
                'hero_title' => 'Deal sốc cho điện thoại, laptop và điện gia dụng',
                'hero_subtitle' => 'Giá tốt mỗi ngày, giao nhanh toàn quốc cho khách hàng công nghệ.',
                'hero_badge' => 'Chỉ từ 399K',
                'product_prefix' => 'Flex',
                'departments' => [
                    ['name' => 'Điện thoại', 'children' => ['Android', 'iPhone', 'Gaming Phone']],
                    ['name' => 'Laptop', 'children' => ['Văn phòng', 'Gaming', 'Ultrabook']],
                    ['name' => 'Máy tính bảng', 'children' => ['Tablet Android', 'iPad', 'Tablet học tập']],
                    ['name' => 'Phụ kiện', 'children' => ['Tai nghe', 'Sạc dự phòng', 'Cáp sạc']],
                    ['name' => 'Âm thanh', 'children' => ['Loa Bluetooth', 'Micro', 'Soundbar']],
                    ['name' => 'TV & Giải trí', 'children' => ['Smart TV', 'Máy chiếu', 'Android Box']],
                    ['name' => 'Điện lạnh', 'children' => ['Điều hòa', 'Máy lọc không khí', 'Quạt thông minh']],
                    ['name' => 'Gia dụng', 'children' => ['Nồi chiên', 'Máy hút bụi', 'Máy pha cà phê']],
                    ['name' => 'Camera', 'children' => ['Camera wifi', 'Camera hành trình', 'Camera an ninh']],
                    ['name' => 'Thiết bị mạng', 'children' => ['Router', 'Mesh wifi', 'Bộ kích sóng']],
                ],
            ],
            [
                'key' => 'phones-accessories',
                'label' => 'Điện thoại và phụ kiện',
                'short_label' => 'điện thoại',
                'description' => 'Preset cho showroom điện thoại, phụ kiện và dịch vụ bảo hành mở rộng.',
                'company_name' => 'Mobile Hub',
                'domain' => 'mobilehub.demo',
                'address' => '85 Nguyễn Thị Minh Khai, Quận 1, TP.HCM',
                'theme_flavor' => 'showroom mobile',
                'hero_eyebrow' => 'Mobile deal',
                'hero_title' => 'Lên đời smartphone và phụ kiện chính hãng',
                'hero_subtitle' => 'Combo máy mới, trả góp linh hoạt và hậu mãi tại cửa hàng.',
                'hero_badge' => 'Giảm đến 35%',
                'product_prefix' => 'Nova',
                'departments' => [
                    ['name' => 'Smartphone', 'children' => ['Flagship', 'Tầm trung', 'Giá tốt']],
                    ['name' => 'Ốp lưng', 'children' => ['Chống sốc', 'Trong suốt', 'Da cao cấp']],
                    ['name' => 'Tai nghe', 'children' => ['True Wireless', 'Over-ear', 'Gaming Earbuds']],
                    ['name' => 'Sạc', 'children' => ['Sạc nhanh', 'Sạc không dây', 'Củ cáp combo']],
                    ['name' => 'Đồng hồ', 'children' => ['Smartwatch', 'Vòng tay', 'Đồng hồ trẻ em']],
                    ['name' => 'Loa mini', 'children' => ['Bluetooth', 'Loa karaoke', 'Loa du lịch']],
                    ['name' => 'Thiết bị ghi hình', 'children' => ['Gimbal', 'Selfie stick', 'Đèn livestream']],
                    ['name' => 'Bảo hành', 'children' => ['Gói rơi vỡ', 'Bảo hành pin', 'Thu cũ đổi mới']],
                    ['name' => 'Máy cũ', 'children' => ['Like new', 'Refurbished', 'Outlet']],
                    ['name' => 'Phụ kiện xe', 'children' => ['Giá đỡ', 'Sạc ô tô', 'Bluetooth car']],
                ],
            ],
            [
                'key' => 'computer-workstation',
                'label' => 'Máy tính và workstation',
                'short_label' => 'máy tính',
                'description' => 'Preset cho doanh nghiệp bán máy tính, workstation và thiết bị văn phòng.',
                'company_name' => 'Compute Center',
                'domain' => 'computecenter.demo',
                'address' => '12 Duy Tân, Cầu Giấy, Hà Nội',
                'theme_flavor' => 'workstation store',
                'hero_eyebrow' => 'Work smarter',
                'hero_title' => 'Thiết bị máy tính tối ưu cho văn phòng và sáng tạo',
                'hero_subtitle' => 'Danh mục dựng sẵn để test block chuyên ngành máy tính.',
                'hero_badge' => 'Quà tặng doanh nghiệp',
                'product_prefix' => 'Core',
                'departments' => [
                    ['name' => 'Laptop doanh nghiệp', 'children' => ['14 inch', '15 inch', '2-in-1']],
                    ['name' => 'PC đồng bộ', 'children' => ['Mini PC', 'Office PC', 'All in one']],
                    ['name' => 'Workstation', 'children' => ['3D Render', 'AI Training', 'Video Editing']],
                    ['name' => 'Màn hình', 'children' => ['2K', '4K', 'Ultrawide']],
                    ['name' => 'Thiết bị nhập', 'children' => ['Bàn phím', 'Chuột', 'Webcam']],
                    ['name' => 'Lưu trữ', 'children' => ['SSD', 'NAS', 'Ổ cứng di động']],
                    ['name' => 'Mạng văn phòng', 'children' => ['Switch', 'Router', 'Firewall']],
                    ['name' => 'Máy in', 'children' => ['Laser', 'Màu', 'In tem']],
                    ['name' => 'Phòng họp', 'children' => ['Camera hội nghị', 'Loa hội nghị', 'Bảng tương tác']],
                    ['name' => 'Server mini', 'children' => ['Tower', 'Rack 1U', 'Backup']],
                ],
            ],
            [
                'key' => 'travel-deals',
                'label' => 'Du lịch và trải nghiệm',
                'short_label' => 'du lịch',
                'description' => 'Preset cho website tour, combo nghỉ dưỡng và vé trải nghiệm cuối tuần.',
                'company_name' => 'Travel Burst',
                'domain' => 'travelburst.demo',
                'address' => '25 Trần Hưng Đạo, Hoàn Kiếm, Hà Nội',
                'theme_flavor' => 'travel flash deal',
                'hero_eyebrow' => 'Combo cuối tuần',
                'hero_title' => 'Săn tour, vé vui chơi và combo nghỉ dưỡng giá tốt',
                'hero_subtitle' => 'Layout phù hợp website deal du lịch nhiều banner và nhiều ngành hàng.',
                'hero_badge' => '2N1Đ từ 1.99M',
                'product_prefix' => 'Trip',
                'departments' => [
                    ['name' => 'Tour miền Bắc', 'children' => ['Sa Pa', 'Hạ Long', 'Hà Giang']],
                    ['name' => 'Tour miền Trung', 'children' => ['Đà Nẵng', 'Huế', 'Quy Nhơn']],
                    ['name' => 'Tour miền Nam', 'children' => ['Phú Quốc', 'Vũng Tàu', 'Cần Thơ']],
                    ['name' => 'Vé vui chơi', 'children' => ['Công viên', 'Thủy cung', 'Show diễn']],
                    ['name' => 'Resort', 'children' => ['Biển', 'Núi', 'City stay']],
                    ['name' => 'Du thuyền', 'children' => ['Hạ Long', 'Sài Gòn', 'Sông Hàn']],
                    ['name' => 'Spa du lịch', 'children' => ['Massage', 'Detox', 'Wellness']],
                    ['name' => 'Ẩm thực địa phương', 'children' => ['Buffet', 'Hải sản', 'Cafe view đẹp']],
                    ['name' => 'Visa & dịch vụ', 'children' => ['Hàn Quốc', 'Nhật Bản', 'Schengen']],
                    ['name' => 'Team building', 'children' => ['1 ngày', '2 ngày', 'MICE']],
                ],
            ],
            [
                'key' => 'interior-home',
                'label' => 'Noi that sang hien dai',
                'short_label' => 'noi that',
                'description' => 'Preset cho ecommerce noi that, decor va vat lieu hoan thien, phu hop TH0020 voi menu da cap, banner tren dau va block san pham theo khong gian.',
                'company_name' => 'TH0020 Living Studio',
                'domain' => 'th0020living.demo',
                'address' => '42 Nguyen Co Thach, Nam Tu Liem, Ha Noi',
                'theme_flavor' => 'interior commerce',
                'hero_eyebrow' => 'New living',
                'hero_title' => 'Noi that sang hien dai cho can ho va nha pho',
                'hero_subtitle' => 'Dung de test TH0020 voi room catalog, mega menu san pham, side banner va luong mua hang that tu Catalog.',
                'hero_badge' => 'Tu van phoi phong',
                'product_prefix' => 'Living',
                'industry_family' => 'interior',
                'departments' => [
                    ['name' => 'Phong khach', 'children' => ['Sofa', 'Ban tra', 'Ke TV']],
                    ['name' => 'Phong ngu', 'children' => ['Giuong ngu', 'Tu dau giuong', 'Tu ao']],
                    ['name' => 'Ban an va bep', 'children' => ['Ban an', 'Ghe an', 'Tu bep phu']],
                    ['name' => 'Decor va den', 'children' => ['Den trang tri', 'Tham', 'Binh va tranh']],
                    ['name' => 'Goc lam viec', 'children' => ['Ban lam viec', 'Ghe ergonomic', 'Ke ho so']],
                    ['name' => 'Ban cong va san vuon', 'children' => ['Ban ghe ban cong', 'Ghe thu gian', 'Chau cay']],
                    ['name' => 'Vat lieu hoan thien', 'children' => ['San go', 'Gach op lat', 'Tam op']],
                    ['name' => 'Phong tam', 'children' => ['Lavabo', 'Sen voi', 'Tu guong']],
                ],
            ],
            [
                'key' => 'cosmetics-beauty',
                'label' => 'Mỹ phẩm và làm đẹp',
                'short_label' => 'mỹ phẩm',
                'description' => 'Preset cho ecommerce mỹ phẩm, skincare, spa package và thiết bị beauty.',
                'company_name' => 'Beauty Bloom',
                'domain' => 'beautybloom.demo',
                'address' => '89 Võ Văn Tần, Quận 3, TP.HCM',
                'theme_flavor' => 'beauty commerce',
                'hero_eyebrow' => 'Glow every day',
                'hero_title' => 'Skincare, makeup và combo spa cho khách hàng nữ',
                'hero_subtitle' => 'Tổ chức block danh mục, sản phẩm nổi bật và tin tư vấn làm đẹp.',
                'hero_badge' => 'Voucher từ 89K',
                'product_prefix' => 'Glow',
                'departments' => [
                    ['name' => 'Chăm sóc da', 'children' => ['Làm sạch', 'Dưỡng ẩm', 'Chống nắng']],
                    ['name' => 'Trang điểm', 'children' => ['Nền', 'Môi', 'Mắt']],
                    ['name' => 'Nước hoa', 'children' => ['Nữ', 'Nam', 'Unisex']],
                    ['name' => 'Thiết bị beauty', 'children' => ['Máy rửa mặt', 'Máy nâng cơ', 'Máy xông']],
                    ['name' => 'Chăm sóc tóc', 'children' => ['Dầu gội', 'Ủ tóc', 'Tinh dầu']],
                    ['name' => 'Body care', 'children' => ['Sữa tắm', 'Dưỡng thể', 'Tẩy da chết']],
                    ['name' => 'Spa tại nhà', 'children' => ['Mask', 'Detox', 'Massage']],
                    ['name' => 'Dành cho nam', 'children' => ['Skincare nam', 'Wax tóc', 'Sữa rửa mặt']],
                    ['name' => 'Quà tặng', 'children' => ['Gift set', 'Mini size', 'Best seller']],
                    ['name' => 'Thực phẩm đẹp da', 'children' => ['Collagen', 'Vitamin', 'Detox']],
                ],
            ],
            [
                'key' => 'industrial-chemicals',
                'label' => 'Hóa chất và vật tư công nghiệp',
                'short_label' => 'hóa chất',
                'description' => 'Preset cho doanh nghiệp phân phối hóa chất, dung môi, vật tư phòng lab.',
                'company_name' => 'Lab Supply Pro',
                'domain' => 'labsupply.demo',
                'address' => 'Lô C2 KCN Tân Bình, TP.HCM',
                'theme_flavor' => 'industrial catalog',
                'hero_eyebrow' => 'Nguồn hàng công nghiệp',
                'hero_title' => 'Hóa chất, vật tư lab và thiết bị sản xuất theo ngành',
                'hero_subtitle' => 'Dùng để test theme B2B với nhiều danh mục kỹ thuật.',
                'hero_badge' => 'Báo giá trong 2h',
                'product_prefix' => 'Lab',
                'departments' => [
                    ['name' => 'Dung môi', 'children' => ['Ethanol', 'Acetone', 'IPA']],
                    ['name' => 'Hóa chất xử lý nước', 'children' => ['PAC', 'Polymer', 'Chlorine']],
                    ['name' => 'Phòng thí nghiệm', 'children' => ['Becher', 'Pipet', 'Tủ hút']],
                    ['name' => 'An toàn lao động', 'children' => ['Găng tay', 'Kính bảo hộ', 'Mặt nạ']],
                    ['name' => 'Thiết bị đo', 'children' => ['pH meter', 'Conductivity', 'Cân điện tử']],
                    ['name' => 'Hóa mỹ phẩm nền', 'children' => ['Glycerin', 'Surfactant', 'Tinh dầu']],
                    ['name' => 'Bao bì hóa chất', 'children' => ['Can nhựa', 'Phuy', 'IBC']],
                    ['name' => 'Hóa chất thực phẩm', 'children' => ['Phụ gia', 'Chất bảo quản', 'Màu thực phẩm']],
                    ['name' => 'Xi mạ', 'children' => ['Muối', 'Phụ gia bể', 'Thiết bị lọc']],
                    ['name' => 'Tư vấn kỹ thuật', 'children' => ['SDS', 'MSDS', 'Quy trình']],
                ],
            ],
            [
                'key' => 'construction-materials',
                'label' => 'Xây dựng và nội thất',
                'short_label' => 'xây dựng',
                'description' => 'Preset cho công ty vật liệu xây dựng, nội thất hoàn thiện và phụ kiện công trình.',
                'company_name' => 'Build Mart',
                'domain' => 'buildmart.demo',
                'address' => 'QL1A, Thủ Đức, TP.HCM',
                'theme_flavor' => 'building materials hub',
                'hero_eyebrow' => 'Nguồn hàng công trình',
                'hero_title' => 'Vật liệu hoàn thiện, nội thất và thiết bị công trình',
                'hero_subtitle' => 'Danh mục lớn để test nhiều block sản phẩm theo ngành.',
                'hero_badge' => 'Chiết khấu đại lý',
                'product_prefix' => 'Stone',
                'departments' => [
                    ['name' => 'Gạch ốp lát', 'children' => ['Phòng khách', 'Nhà tắm', 'Ngoại thất']],
                    ['name' => 'Thiết bị vệ sinh', 'children' => ['Bồn cầu', 'Lavabo', 'Sen vòi']],
                    ['name' => 'Sơn nước', 'children' => ['Nội thất', 'Ngoại thất', 'Chống thấm']],
                    ['name' => 'Nội thất bếp', 'children' => ['Chậu rửa', 'Máy hút mùi', 'Bếp từ']],
                    ['name' => 'Cửa & khóa', 'children' => ['Khóa điện tử', 'Cửa nhôm', 'Phụ kiện']],
                    ['name' => 'Vật liệu thô', 'children' => ['Xi măng', 'Thép', 'Cát đá']],
                    ['name' => 'Đèn trang trí', 'children' => ['Đèn thả', 'Đèn âm trần', 'Đèn tường']],
                    ['name' => 'Sàn', 'children' => ['Gỗ công nghiệp', 'Nhựa SPC', 'Thảm']],
                    ['name' => 'Ngoại thất', 'children' => ['Giàn phơi', 'Mái hiên', 'Lan can']],
                    ['name' => 'Dụng cụ thi công', 'children' => ['Khoan', 'Cắt gạch', 'Máy mài']],
                ],
            ],
            [
                'key' => 'tech-accessories',
                'label' => 'Phụ kiện công nghệ',
                'short_label' => 'phụ kiện',
                'description' => 'Preset gọn cho cửa hàng phụ kiện công nghệ, gaming gear và đồ thông minh.',
                'company_name' => 'Accessory Station',
                'domain' => 'accessorystation.demo',
                'address' => '248 Cầu Giấy, Hà Nội',
                'theme_flavor' => 'accessory flash sale',
                'hero_eyebrow' => 'Gear & gadget',
                'hero_title' => 'Tai nghe, gear, sạc nhanh và đồ smart-home bán chạy',
                'hero_subtitle' => 'Preset phù hợp cho layout hot deal, sản phẩm nhỏ nhưng danh mục dày.',
                'hero_badge' => 'Combo từ 129K',
                'product_prefix' => 'Pulse',
                'departments' => [
                    ['name' => 'Gaming Gear', 'children' => ['Chuột', 'Bàn phím', 'Lót chuột']],
                    ['name' => 'Sạc nhanh', 'children' => ['GaN', 'MagSafe', 'Củ cáp']],
                    ['name' => 'Âm thanh cá nhân', 'children' => ['Earbuds', 'Headphones', 'DAC']],
                    ['name' => 'Camera mini', 'children' => ['Webcam', 'Action cam', 'Livestream']],
                    ['name' => 'Nhà thông minh', 'children' => ['Ổ cắm', 'Đèn thông minh', 'Cảm biến']],
                    ['name' => 'Phụ kiện laptop', 'children' => ['Hub USB-C', 'Giá đỡ', 'Quạt tản']],
                    ['name' => 'Balo & túi', 'children' => ['Balo', 'Túi sleeve', 'Chống sốc']],
                    ['name' => 'Phụ kiện xe hơi', 'children' => ['Sạc xe', 'Cam hành trình', 'Giá đỡ']],
                    ['name' => 'Thiết bị văn phòng', 'children' => ['Bút trình chiếu', 'Docking', 'Ổ cắm kéo dài']],
                    ['name' => 'Quà công nghệ', 'children' => ['Gift set', 'Mini gadget', 'Best seller']],
                ],
            ],
            [
                'key' => 'ser-airport-city',
                'label' => 'Nhà xe sân bay và city transfer',
                'short_label' => 'nhà xe sân bay',
                'description' => 'Preset cho nhà xe tập trung vào đưa đón sân bay, city transfer và thuê xe gia đình ngắn hạn.',
                'company_name' => 'Saigon Airport Cars',
                'domain' => 'saigonairportcars.demo',
                'address' => '12 Trường Sơn, Tân Bình, TP.HCM',
                'theme_flavor' => 'airport transfer service',
                'hero_eyebrow' => 'Đúng giờ mỗi chuyến',
                'hero_title' => 'Đưa đón sân bay và city transfer đúng giờ mỗi ngày',
                'hero_subtitle' => 'Xe sạch, tài xế lịch sự, hỗ trợ nhanh cho khách gia đình, khách công tác và khách VIP.',
                'hero_badge' => 'Phục vụ 24/7',
                'catalog_style' => 'service',
                'product_prefix' => 'Ride',
                'departments' => [
                    ['name' => 'Đưa đón sân bay', 'children' => ['4 chỗ', '7 chỗ', '16 chỗ']],
                    ['name' => 'City transfer', 'children' => ['Nội đô', 'Liên quận', 'Khách sạn']],
                    ['name' => 'Xe gia đình', 'children' => ['Nửa ngày', 'Trọn ngày', 'Cuối tuần']],
                    ['name' => 'Xe công tác', 'children' => ['Doanh nhân', 'Đón đối tác', 'Lịch trình linh hoạt']],
                    ['name' => 'Xe VIP', 'children' => ['Sedan cao cấp', 'MPV cao cấp', 'Đón khuya']],
                    ['name' => 'Đi tỉnh gần', 'children' => ['Vũng Tàu', 'Mỹ Tho', 'Tây Ninh']],
                ],
            ],
            [
                'key' => 'ser-tour-coach',
                'label' => 'Nhà xe du lịch và xe đoàn',
                'short_label' => 'xe du lịch',
                'description' => 'Preset cho nhà xe chuyên tour đoàn, trường học, công ty và hành trình tỉnh.',
                'company_name' => 'Viet Tour Coach',
                'domain' => 'viettourcoach.demo',
                'address' => '85 Nguyễn Văn Linh, Đà Nẵng',
                'theme_flavor' => 'tour transport service',
                'hero_eyebrow' => 'Vận hành tour đoàn',
                'hero_title' => 'Thuê xe du lịch đoàn, tour công ty và hành trình tỉnh',
                'hero_subtitle' => 'Đội xe 16 đến 45 chỗ, hỗ trợ điều phối tour, team building và lịch trình đường dài.',
                'hero_badge' => 'Xe 16-45 chỗ',
                'catalog_style' => 'service',
                'product_prefix' => 'Tour',
                'departments' => [
                    ['name' => 'Thuê xe 16 chỗ', 'children' => ['Trong ngày', '2N1Đ', '3N2Đ']],
                    ['name' => 'Thuê xe 29 chỗ', 'children' => ['Doanh nghiệp', 'Trường học', 'Hành hương']],
                    ['name' => 'Thuê xe 45 chỗ', 'children' => ['Đoàn lớn', 'Sự kiện', 'Tour liên tỉnh']],
                    ['name' => 'Tuyến phổ biến', 'children' => ['Đà Nẵng - Huế', 'Đà Nẵng - Hội An', 'Đà Nẵng - Quy Nhơn']],
                    ['name' => 'Tour công ty', 'children' => ['Team building', 'MICE', 'Retreat']],
                    ['name' => 'Xe đoàn học sinh', 'children' => ['Tham quan', 'Dã ngoại', 'Ngoại khóa']],
                ],
            ],
            [
                'key' => 'ser-business-cargo',
                'label' => 'Shuttle doanh nghiệp và hàng nhẹ',
                'short_label' => 'shuttle doanh nghiệp',
                'description' => 'Preset cho doanh nghiệp vận hành shuttle nhân sự, hợp đồng tháng và vận chuyển hàng nhẹ.',
                'company_name' => 'Metro Shuttle Logistics',
                'domain' => 'metroshuttle.demo',
                'address' => 'Lô B3 KCN Sóng Thần, Bình Dương',
                'theme_flavor' => 'business shuttle and cargo',
                'hero_eyebrow' => 'Shuttle và logistics nhẹ',
                'hero_title' => 'Shuttle doanh nghiệp và vận chuyển hàng nhẹ theo hợp đồng',
                'hero_subtitle' => 'Phù hợp cho công ty, nhà máy, sự kiện và các tuyến giao nhận định kỳ.',
                'hero_badge' => 'Hợp đồng tháng',
                'catalog_style' => 'service',
                'product_prefix' => 'Shuttle',
                'departments' => [
                    ['name' => 'Shuttle công ty', 'children' => ['Theo ca', 'Theo tuyến', 'Theo tháng']],
                    ['name' => 'Xe đưa đón nhân sự', 'children' => ['7 chỗ', '16 chỗ', '29 chỗ']],
                    ['name' => 'Chở hàng nội thành', 'children' => ['Hàng nhẹ', 'Thiết bị sự kiện', 'Hàng gấp']],
                    ['name' => 'Chở hàng tuyến tỉnh', 'children' => ['Bình Dương', 'Đồng Nai', 'Long An']],
                    ['name' => 'Dịch vụ sự kiện', 'children' => ['Chở booth', 'Chở đạo cụ', 'Chở ekip']],
                    ['name' => 'Hợp đồng dài hạn', 'children' => ['Doanh nghiệp', 'Nhà máy', 'Trường học']],
                ],
            ],
        ];
    }

    private function garmentPhotoPool(): array
    {
        return [
            url('theme-demo/garment/product-01.svg'),
            url('theme-demo/garment/product-02.svg'),
            url('theme-demo/garment/product-03.svg'),
            url('theme-demo/garment/product-04.svg'),
            url('theme-demo/garment/product-05.svg'),
        ];
    }
}
