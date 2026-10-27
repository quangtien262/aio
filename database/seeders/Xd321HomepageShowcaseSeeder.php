<?php

namespace Database\Seeders;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Xd321HomepageShowcaseSeeder extends Seeder
{
    private const PRESET_KEY = 'xd321-homepage-showcase';

    public function run(): void
    {
        $websiteKeys = Site::query()->where('theme_key', 'XD321')->where('status', 'active')->pluck('website_key')
            ->merge(SiteProfile::query()->withoutGlobalScopes()->where('active_theme_key', 'XD321')->pluck('website_key'))
            ->unique();

        foreach ($websiteKeys as $websiteKey) {
            $counts = $this->seedForWebsite($websiteKey);
            $this->command?->info($websiteKey.': '.json_encode($counts, JSON_UNESCAPED_UNICODE));
        }

        if ($websiteKeys->isEmpty()) {
            $this->command?->info('Không có website XD321 để bổ sung dữ liệu mẫu.');
        }
    }

    /** Add demo records once, preserving existing content and website context. */
    public function seedForWebsite(string $websiteKey): array
    {
        $context = app(SiteContext::class);
        $previousSite = $context->site();
        $previousWebsite = $context->websiteKey();
        $site = Site::query()->where('website_key', $websiteKey)->first();
        $profile = SiteProfile::query()->withoutGlobalScopes()->where('website_key', $websiteKey)->first();

        if ($site?->theme_key !== 'XD321' && $profile?->active_theme_key !== 'XD321') {
            throw new RuntimeException('Seeder này chỉ dành cho website đang dùng theme XD321.');
        }

        try {
            $context->set($site, $websiteKey);

            return DB::transaction(function () use ($websiteKey): array {
                $counts = ['services' => 0, 'products' => 0, 'testimonials' => 0, 'partners' => 0];
                $now = now();
                foreach ([
                    ['xd321-demo-air-freight', 'Vận tải hàng không', 'Giải pháp giao nhận hàng không cho lô hàng cần tốc độ, kiểm soát lịch bay và cập nhật hành trình rõ ràng.', 'logistics-3.jpg'],
                    ['xd321-demo-customs', 'Khai báo hải quan', 'Hỗ trợ chuẩn bị hồ sơ, đối chiếu chứng từ và phối hợp thông quan để hàng hóa lưu chuyển thuận lợi.', 'logistics-1.jpg'],
                ] as $index => [$slug, $title, $summary, $image]) {
                    $service = CmsService::query()->firstOrCreate(['slug' => $slug], [
                        'title' => $title, 'summary' => $summary, 'content' => '<p>'.$summary.'</p>',
                        'status' => 'published', 'publish_at' => $now, 'button_label' => 'Xem chi tiết',
                        'is_featured' => true, 'is_highlight' => true, 'sort_order' => 4 + $index,
                    ]);
                    if ($service->wasRecentlyCreated) {
                        CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => '/theme-demo/xd-shared/'.$image, 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                        $this->record($service);
                        $counts['services']++;
                    }
                }

                $category = CatalogCategory::query()->where('is_active', true)->orderBy('sort_order')->first();
                if ($category === null) {
                    $category = CatalogCategory::query()->firstOrCreate(['slug' => 'xd321-demo-logistics-supplies'], ['name' => 'Vật tư logistics', 'is_active' => true, 'sort_order' => 0]);
                    if ($category->wasRecentlyCreated) {
                        $this->record($category);
                    }
                }
                foreach ([
                    ['xd321-demo-stretch-film', 'Màng PE quấn pallet', 185000, 'stretch-film.svg'],
                    ['xd321-demo-security-seal', 'Seal niêm phong container', 35000, 'security-seal.svg'],
                    ['xd321-demo-cargo-straps', 'Dây đai chằng hàng', 320000, 'cargo-straps.svg'],
                    ['xd321-demo-packaging-tape', 'Băng keo đóng thùng', 28000, 'packaging-tape.svg'],
                ] as $index => [$slug, $name, $price, $image]) {
                    $product = CatalogProduct::query()->firstOrCreate(['slug' => $slug], [
                        'catalog_category_id' => $category->id, 'name' => $name, 'sku' => 'X321-DEMO-'.($index + 1),
                        'price' => $price, 'stock' => 100, 'short_description' => 'Sản phẩm minh họa cho bộ vật tư hỗ trợ logistics XD321.',
                        'detail_content' => '<p>Vật tư hỗ trợ đóng gói, bảo vệ và vận chuyển hàng hóa. Dữ liệu sản phẩm dùng để minh họa giao diện.</p>',
                        'image_url' => '/theme-demo/xd321/'.$image, 'is_active' => true, 'is_featured' => true, 'is_highlight' => true, 'sort_order' => 4 + $index,
                    ]);
                    if ($product->wasRecentlyCreated) {
                        $this->record($product);
                        $counts['products']++;
                    }
                }

                foreach ([
                    ['Chị Mai', 'An Phát Trading · Minh họa', 'Kế hoạch giao nhận được thống nhất ngay từ đầu. Đội ngũ chủ động cập nhật từng mốc, giúp chúng tôi dễ dàng phối hợp với nhà cung cấp.', 'person-2.jpg'],
                    ['Anh Tuấn', 'Bình Minh Retail · Minh họa', 'Các lô hàng được sắp xếp khoa học và bàn giao đầy đủ. Chúng tôi đánh giá cao cách phản hồi nhanh khi cần thay đổi lịch giao.', 'person-1.jpg'],
                    ['Chị Linh', 'Hải Nam Export · Minh họa', 'Hồ sơ và phạm vi dịch vụ được giải thích rõ ràng. Quá trình giao nhận diễn ra thuận lợi, phù hợp với nhu cầu xuất hàng định kỳ.', 'person-2.jpg'],
                    ['Anh Quang', 'Đông Á Industrial · Minh họa', 'Phương án đóng gói và vận chuyển được tư vấn sát với đặc điểm hàng hóa. Đội vận hành phối hợp tốt trong từng bước triển khai.', 'person-3.jpg'],
                    ['Chị Hương', 'Minh Long Distribution · Minh họa', 'Từ tiếp nhận yêu cầu đến bàn giao, thông tin luôn được trao đổi nhất quán. Đây là trải nghiệm dịch vụ mà đội ngũ của chúng tôi mong đợi.', 'person-2.jpg'],
                ] as $index => [$name, $company, $quote, $image]) {
                    $testimonial = CmsTestimonial::query()->firstOrCreate(['name' => $name, 'company' => $company], [
                        'role' => 'Khách hàng doanh nghiệp', 'quote' => $quote, 'image_url' => '/theme-demo/xd-shared/'.$image,
                        'image_alt' => $name, 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => 3 + $index,
                    ]);
                    if ($testimonial->wasRecentlyCreated) {
                        $this->record($testimonial);
                        $counts['testimonials']++;
                    }
                }

                foreach (['Northline Logistics', 'Blueport Cargo', 'Eastway Supply'] as $index => $title) {
                    $partner = CmsPartner::query()->firstOrCreate(['slug' => 'xd321-demo-partner-'.($index + 1)], [
                        'title' => $title, 'description' => 'Đối tác minh họa cho giao diện XD321.',
                        'image_url' => '/theme-demo/xd-shared/partner-'.($index + 4).'.svg', 'image_alt' => $title,
                        'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => 5 + $index,
                    ]);
                    if ($partner->wasRecentlyCreated) {
                        $this->record($partner);
                        $counts['partners']++;
                    }
                }

                $limits = ['content_mosaic' => 6, 'featured_products' => 12, 'testimonials' => 8, 'partner_logos' => 9];
                foreach (LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', 'XD321')->where('is_home', true)->with('blocks')->get() as $page) {
                    foreach ($page->blocks as $block) {
                        if (! isset($limits[$block->block_type]) || data_get($block->settings, 'source') === 'custom') {
                            continue;
                        }
                        $settings = (array) $block->settings;
                        $newLimit = max((int) ($settings['limit'] ?? 0), $limits[$block->block_type]);
                        if (($settings['limit'] ?? null) !== $newLimit) {
                            $block->update(['settings' => array_merge($settings, ['limit' => $newLimit])]);
                        }
                    }
                }

                return $counts;
            });
        } finally {
            $context->set($previousSite, $previousWebsite);
        }
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => 'XD321', 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
