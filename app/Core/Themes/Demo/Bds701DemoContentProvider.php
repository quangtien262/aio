<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Bds701DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'BDS701';
    private const PRESET_KEY = 'bds701-delta-platinum';

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {
    }

    public function themeKey(): string
    {
        return self::THEME_KEY;
    }

    public function defaultPreset(): string
    {
        return self::PRESET_KEY;
    }

    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'BDS701 Delta Platinum',
            'description' => 'Dữ liệu mẫu bất động sản gồm loại hình, tin bán/cho thuê, gallery, tin thị trường, menu và landing page.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho BDS701.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $photos = [
                'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1200&q=88',
                'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=88',
            ];
            $typeSeeds = [
                ['Biệt thự', 'biet-thu', 'fa-solid fa-house-chimney-window', $photos[0]],
                ['Nhà vườn', 'nha-vuon', 'fa-solid fa-house-chimney', $photos[2]],
                ['Nhà phố', 'nha-pho', 'fa-solid fa-city', $photos[3]],
                ['Chung cư', 'chung-cu', 'fa-solid fa-building', $photos[4]],
                ['Căn hộ', 'can-ho', 'fa-regular fa-building', $photos[5]],
            ];
            $types = collect();
            foreach ($typeSeeds as $index => [$name, $slug, $icon, $image]) {
                $type = RealEstatePropertyType::query()->firstOrNew([
                    'website_key' => $websiteKey,
                    'slug' => $slug,
                ]);
                $typeWasExisting = $type->exists;
                $type->fill([
                    'name' => $name, 'slug' => $slug, 'icon' => $icon, 'image_url' => $image,
                    'description' => 'Không gian '.$name.' được chọn lọc bởi Delta Platinum.',
                    'sort_order' => $index, 'is_active' => true, 'website_key' => $websiteKey,
                ])->save();
                if (! $typeWasExisting) {
                    $this->record($type);
                }
                $types->put($slug, $type);
            }

            $listings = [
                ['Cho thuê căn hộ, biệt thự cao cấp', 'can-ho', 'rent', 36000000, 'Phường 15', 'Bình Thạnh', 'Hồ Chí Minh', 3, 2, 175, true],
                ['Biệt thự Sunshine Group', 'biet-thu', 'sale', 15000000000, 'Quảng An', 'Tây Hồ', 'Hà Nội', 6, 6, 215, true],
                ['Bán biệt thự N03 Sài Đồng', 'biet-thu', 'sale', 18700000000, 'Sài Đồng', 'Long Biên', 'Hà Nội', 6, 5, 300, false],
                ['Cho thuê biệt thự hiện đại', 'biet-thu', 'rent', 48000000, 'Thảo Điền', 'Thủ Đức', 'Hồ Chí Minh', 4, 3, 220, false],
                ['Bán biệt thự hiện đại mới xây', 'biet-thu', 'sale', 12500000000, 'Xuân Thủy', 'Cầu Giấy', 'Hà Nội', 3, 4, 96, true],
                ['Biệt thự khu Jamona Golden Silk', 'nha-vuon', 'sale', 14000000000, 'Hiệp Bình Phước', 'Thủ Đức', 'Hồ Chí Minh', 4, 3, 162, false],
                ['Cho thuê căn hộ trung tâm', 'can-ho', 'rent', 24000000, 'Yên Xá', 'Hà Đông', 'Hà Nội', 3, 2, 110, false],
                ['Nhà phố thương mại ven sông', 'nha-pho', 'sale', 9200000000, 'An Phú', 'Thủ Đức', 'Hồ Chí Minh', 4, 4, 145, true],
            ];
            foreach ($listings as $index => [$title, $typeSlug, $transaction, $price, $ward, $district, $province, $bedrooms, $bathrooms, $area, $featured]) {
                $listingSlug = Str::slug('bds701-'.$title);
                $listing = RealEstateListing::query()->firstOrNew([
                    'website_key' => $websiteKey,
                    'slug' => $listingSlug,
                ]);
                $listingWasExisting = $listing->exists;
                $listing->fill([
                    'property_type_id' => $types[$typeSlug]->id,
                    'title' => $title,
                    'slug' => $listingSlug,
                    'code' => 'BDS701-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'publication_status' => 'published',
                    'availability_status' => 'available',
                    'transaction_type' => $transaction,
                    'price' => $price,
                    'price_unit' => $transaction === 'rent' ? 'tháng' : 'tổng',
                    'currency' => 'VND',
                    'province' => $province,
                    'district' => $district,
                    'ward' => $ward,
                    'address' => $ward.', '.$district.', '.$province,
                    'bedrooms' => $bedrooms,
                    'bathrooms' => $bathrooms,
                    'floors' => $typeSlug === 'can-ho' ? 1 : 3,
                    'land_area' => $area,
                    'floor_area' => $area,
                    'direction' => ['Đông Nam', 'Tây Bắc', 'Nam', 'Đông'][$index % 4],
                    'legal_status' => 'Sổ hồng riêng, pháp lý minh bạch',
                    'furnishing_status' => 'Nội thất hoàn thiện cao cấp',
                    'virtual_tour_url' => $index < 3 ? 'https://example.com/virtual-tour/'.$index : null,
                    'summary' => 'Bất động sản vị trí đẹp, không gian thoáng, tiện ích đồng bộ và phù hợp cho nhu cầu an cư hoặc đầu tư dài hạn.',
                    'content' => '<p>Không gian được quy hoạch chỉn chu với hệ thống tiện ích, giao thông thuận lợi và tiềm năng gia tăng giá trị bền vững.</p><p>Liên hệ Delta Platinum để nhận hồ sơ chi tiết, lịch xem thực tế và tư vấn tài chính.</p>',
                    'meta_title' => $title,
                    'meta_description' => 'Thông tin chi tiết '.$title.' tại '.$district.', '.$province.'.',
                    'is_featured' => $featured,
                    'is_hot' => in_array($index, [1, 4], true),
                    'sort_order' => $index,
                    'published_at' => now()->subDays($index),
                    'website_key' => $websiteKey,
                ])->save();
                foreach ([$photos[$index % count($photos)], $photos[($index + 2) % count($photos)]] as $mediaIndex => $url) {
                    $listing->media()->updateOrCreate([
                        'sort_order' => $mediaIndex,
                    ], [
                        'media_type' => 'image', 'media_url' => $url, 'alt_text' => $title,
                        'is_featured' => $mediaIndex === 0, 'sort_order' => $mediaIndex,
                    ]);
                }
                $listing->media()->where('sort_order', '>', 1)->delete();
                if (! $listingWasExisting) {
                    $this->record($listing);
                }
            }

            $category = CmsCategory::query()->firstOrNew([
                'website_key' => $websiteKey,
                'slug' => 'bds701-thi-truong-bat-dong-san',
            ]);
            $categoryWasExisting = $category->exists;
            $category->fill([
                'name' => 'Thị trường bất động sản',
                'slug' => 'bds701-thi-truong-bat-dong-san',
                'description' => 'Phân tích, chính sách và xu hướng thị trường bất động sản.',
                'website_key' => $websiteKey,
            ])->save();
            if (! $categoryWasExisting) {
                $this->record($category);
            }
            foreach ([
                'Những lý do đầy thu hút của dự án chung cư New City',
                'Tình hình thị trường bất động sản năm nay sẽ diễn ra như thế nào',
                'Chủ đầu tư và tiêu chí lựa chọn dự án bền vững',
                'Chia nhỏ căn hộ, cho thuê ngắn hạn lợi ít hay nhiều',
                'Hàng loạt rào cản kìm hãm nguồn cung căn hộ giá hợp lý',
                'Cuộc sống thượng lưu tại khu biệt thự ven sông',
            ] as $index => $title) {
                $postSlug = Str::slug('bds701-'.$title);
                $post = CmsPost::query()->firstOrNew([
                    'website_key' => $websiteKey,
                    'slug' => $postSlug,
                ]);
                $postWasExisting = $post->exists;
                $post->fill([
                    'category_id' => $category->id,
                    'title' => $title,
                    'slug' => $postSlug,
                    'status' => 'published',
                    'excerpt' => 'Cập nhật góc nhìn mới nhất về thị trường, dự án và cơ hội đầu tư bất động sản.',
                    'body' => '<p>Cập nhật góc nhìn mới nhất về thị trường, dự án và cơ hội đầu tư bất động sản.</p>',
                    'publish_at' => now()->subDays($index),
                    'is_highlight' => $index === 0,
                    'website_key' => $websiteKey,
                ])->save();
                if (! $postWasExisting) {
                    $this->record($post);
                }
            }

            $menu = CmsMenu::query()->firstOrNew([
                'website_key' => $websiteKey,
                'name' => 'BDS701 Main Menu',
            ]);
            $menuWasExisting = $menu->exists;
            $menu->fill([
                'name' => 'BDS701 Main Menu',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => route('site.home')],
                    ['label' => 'Tất cả tin rao', 'url' => route('site.real-estate.index')],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index')],
                    ['label' => 'Giới thiệu', 'url' => route('site.pages.show', ['slug' => 'gioi-thieu'])],
                    ['label' => 'Liên hệ', 'url' => route('site.contact')],
                ],
                'website_key' => $websiteKey,
            ])->save();
            if (! $menuWasExisting) {
                $this->record($menu);
            }

            $aboutPage = CmsPage::query()->firstOrCreate(
                ['website_key' => $websiteKey, 'slug' => 'gioi-thieu'],
                ['title' => 'Giới thiệu Delta Platinum', 'status' => 'published', 'excerpt' => 'Đồng hành cùng nhu cầu an cư và đầu tư.', 'body' => '<p>Delta Platinum cung cấp thông tin minh bạch và tư vấn bất động sản phù hợp theo từng nhu cầu.</p>', 'publish_at' => now()],
            );
            if ($aboutPage->wasRecentlyCreated) {
                $this->record($aboutPage);
            }

            $profile = SiteProfile::query()->firstOrNew(['website_key' => $websiteKey]);
            $profile->forceFill([
                'site_name' => 'Delta Platinum',
                'website_type' => 'real_estate',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'Delta Platinum',
                    'company_description' => 'Nền tảng tư vấn và giao dịch bất động sản chọn lọc.',
                    'support_hotline' => '0399162342',
                    'support_email' => 'hello@deltaplatinum.vn',
                    'support_location' => 'An Thượng, Hà Nội',
                ]),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'property_types' => count($typeSeeds), 'listings' => count($listings),
                    'posts' => 6, 'post_categories' => 1, 'menus' => 1,
                    'pages' => $aboutPage->wasRecentlyCreated ? 1 : 0,
                    'landing_pages' => $page && $existingPage === null ? 1 : 0,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['property_types' => 0, 'listings' => 0, 'posts' => 0, 'post_categories' => 0, 'menus' => 0, 'pages' => 0, 'landing_pages' => 0];

        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        foreach ([
            [RealEstateListing::class, 'listings'],
            [RealEstatePropertyType::class, 'property_types'],
            [CmsPost::class, 'posts'],
            [CmsCategory::class, 'post_categories'],
            [CmsPage::class, 'pages'],
            [CmsMenu::class, 'menus'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] = $model::query()->whereKey($modelIds)->delete();
            }
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->updateOrCreate([
            'theme_key' => self::THEME_KEY,
            'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ]);
    }
}
