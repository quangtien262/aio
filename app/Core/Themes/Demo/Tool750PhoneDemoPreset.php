<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Support\Str;

/** Adapt only records owned by the TOOL750 demo, leaving customer content intact. */
class Tool750PhoneDemoPreset
{
    public static function apply(): void
    {
        $records = ThemeDemoRecord::query()->where('theme_key', 'TOOL750')->where('preset_key', 'tool750-industrial')->get();
        $owned = fn (string $model) => $model::query()->whereKey($records->where('model_type', $model)->pluck('model_id'))->orderBy('id')->get();
        $assets = ['/theme-demo/ec902/charger-wall.webp', '/theme-demo/ec902/charger-wireless.webp', '/theme-demo/ec902/earbuds-white.webp', '/theme-demo/ec909/headphone-black.png', '/theme-demo/ec909/headphone-beige.png', '/theme-demo/ec907/speaker.webp', '/theme-demo/ec909/speaker-oak.png'];
        $names = ['Củ sạc nhanh', 'Sạc không dây', 'Tai nghe True Wireless', 'Tai nghe chụp tai', 'Tai nghe thời trang', 'Loa di động', 'Loa để bàn'];
        $categories = $owned(CatalogCategory::class);
        foreach ($categories as $i => $category) {
            $category->update(['name' => $names[$i], 'slug' => Str::slug('tool750-'.$names[$i]), 'description' => 'Phụ kiện cho điện thoại và không gian nghe nhạc của bạn.', 'image_url' => $assets[$i]]);
        }
        $definitions = [
            [0, 'Củ sạc nhanh USB-C 20W', 190000, 250000],
            [1, 'Đế sạc không dây 15W', 390000, 490000],
            [2, 'Tai nghe True Wireless Air Mini', 590000, 790000],
            [3, 'Tai nghe Bluetooth Studio đen', 890000, 1090000],
            [4, 'Tai nghe Bluetooth màu kem', 990000, 1290000],
            [5, 'Loa Bluetooth Pocket', 490000, 650000],
            [6, 'Loa để bàn Oak Sound', 1490000, 1790000],
            [0, 'Củ sạc nhanh USB-C 30W', 290000, 390000],
            [1, 'Đế sạc không dây Compact', 290000, 390000],
            [2, 'Tai nghe True Wireless Air Plus', 790000, 990000],
            [3, 'Tai nghe Bluetooth Studio Pro', 1290000, 1590000],
            [5, 'Loa Bluetooth Pocket Plus', 690000, 890000],
        ];
        foreach ($owned(CatalogProduct::class) as $i => $product) {
            [$category, $name, $price, $original] = $definitions[$i];
            $product->update(['catalog_category_id' => $categories[$category]->id, 'name' => $name, 'slug' => Str::slug('tool750-'.$name), 'sku' => sprintf('T750-PHONE-%02d', $i + 1), 'price' => $price, 'original_price' => $original, 'image_url' => $assets[$category], 'short_description' => 'Phụ kiện nhỏ gọn cho nhu cầu kết nối và giải trí mỗi ngày. Sản phẩm minh họa.', 'detail_content' => '<h2>'.$name.'</h2><p>Thiết kế tiện dụng cho góc làm việc, học tập và những chuyến đi.</p><h3>Lựa chọn phù hợp</h3><p>Kiểm tra cổng kết nối, công suất và khả năng tương thích với điện thoại trước khi mua.</p><p>Dữ liệu và hình ảnh minh họa; vui lòng liên hệ để xác nhận thông số, giá và chính sách bảo hành.</p>']);
        }
        foreach ($owned(CmsCategory::class) as $category) {
            $category->update(['name' => 'Cẩm nang phụ kiện điện thoại', 'slug' => 'tool750-cam-nang-phu-kien', 'description' => 'Mẹo chọn sạc, tai nghe và loa phù hợp.']);
        }
        $posts = [
            ['Cách chọn củ sạc phù hợp với điện thoại', 'Kiểm tra cổng kết nối, chuẩn sạc và công suất thiết bị hỗ trợ.', $assets[0]],
            ['Chọn tai nghe cho học tập và làm việc', 'Cân nhắc độ vừa vặn, micro và thời lượng sử dụng theo nhu cầu.', $assets[2]],
            ['Bố trí góc nghe nhạc nhỏ gọn', 'Một chiếc loa phù hợp giúp không gian làm việc thêm thoải mái.', $assets[6]],
        ];
        foreach ($owned(CmsPost::class) as $i => $post) {
            [$title, $excerpt, $image] = $posts[$i];
            $post->update(['title' => $title, 'slug' => Str::slug('tool750-'.$title), 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p><p>Ưu tiên phụ kiện có thông tin xuất xứ và chính sách bảo hành rõ ràng. Đối chiếu thông số của phụ kiện với thiết bị bạn đang dùng.</p><p>Nội dung mẫu phục vụ kiểm thử giao diện.</p>']);
            $owned(CmsMedia::class)->firstWhere('id', $post->featured_media_id)?->update(['title' => $title, 'alt_text' => $title, 'file_url' => $image, 'mime_type' => str_ends_with($image, '.webp') ? 'image/webp' : 'image/png']);
        }
        foreach ($owned(CmsTestimonial::class) as $testimonial) {
            $testimonial->update(['role' => 'Khách hàng mua phụ kiện', 'company' => 'Đánh giá minh họa', 'quote' => 'Dễ tìm phụ kiện phù hợp, hình ảnh rõ ràng và tư vấn nhiệt tình.']);
        }
        foreach ($owned(CmsPartner::class) as $i => $partner) {
            $name = ['AIRWAVE', 'POCKET SOUND', 'CHARGE LAB', 'MOBILE LIFE', 'OAK AUDIO', 'CONNECT'][$i];
            $partner->update(['title' => $name, 'slug' => Str::slug('tool750-'.$name), 'image_alt' => $name, 'description' => 'Thương hiệu phụ kiện minh họa.']);
        }
        foreach ($owned(CmsPage::class) as $page) {
            $page->update(['excerpt' => 'Tư vấn phụ kiện phù hợp với điện thoại của bạn.', 'body' => '<p>Hãy chia sẻ mẫu điện thoại và nhu cầu sử dụng để được hỗ trợ chọn sạc, tai nghe hoặc loa phù hợp.</p>']);
        }
        $profile = SiteProfile::query()->first();
        $branding = (array) $profile->branding;
        foreach (['company_name' => ['TOOL750 Cơ Khí Việt', 'TOOL750 Mobile'], 'company_description' => ['Thiết bị cơ khí chính hãng cho xưởng máy, công trình và người thợ hiện đại.', 'Phụ kiện điện thoại cho cuộc sống kết nối.'], 'slogan' => ['Sức mạnh cho mọi công trình', 'Kết nối tiện lợi mỗi ngày'], 'support_location' => ['Trung tâm thiết bị cơ khí Hà Nội', 'Trung tâm phụ kiện điện thoại Hà Nội']] as $key => [$before, $after]) {
            if (($branding[$key] ?? null) === $before) {
                $branding[$key] = $after;
            }
        }
        $profile->update(['site_name' => 'TOOL750 Mobile', 'branding' => $branding]);
        foreach (LandingPageBlock::query()->whereIn('landing_page_id', $owned(LandingPage::class)->modelKeys())->get() as $block) {
            if ($block->media) {
                $block->update(['media' => ['image' => '/theme-demo/ec902/promo-accessories.webp']]);
            }
            foreach ($block->data as $data) {
                $en = $data->locale === 'en';
                $title = match ($block->block_type) {
                    'tool750_hero' => $en ? 'Connect your everyday' : 'Kết nối tiện lợi mỗi ngày',
                    'tool750_promo_banner' => $en ? 'Sound on the go' : 'Âm thanh theo bạn mọi nơi',
                    'tool750_reasons' => $en ? 'Shop with confidence' : 'An tâm chọn phụ kiện',
                    'tool750_category_grid' => $en ? 'Accessory categories' : 'Danh mục phụ kiện',
                    'tool750_news' => $en ? 'Accessory guide' : 'Cẩm nang phụ kiện',
                    default => $data->title,
                };
                $items = [];
                if ($block->block_type === 'tool750_promo_categories') {
                    foreach ([0, 2, 5] as $i) {
                        $items[] = ['title' => $names[$i], 'summary' => 'Khám phá bộ sưu tập', 'image' => $assets[$i], 'url' => route('site.catalog.category', ['slug' => $categories[$i]->slug])];
                    }
                } elseif ($block->block_type === 'tool750_reasons') {
                    $items = [['title' => 'Tư vấn tương thích', 'summary' => 'Chọn phụ kiện theo thiết bị và nhu cầu sử dụng.', 'icon' => 'fa-solid fa-headset'], ['title' => 'Thông tin rõ ràng', 'summary' => 'Dễ so sánh sản phẩm và giá trước khi lựa chọn.', 'icon' => 'fa-solid fa-circle-check'], ['title' => 'Hỗ trợ mua hàng', 'summary' => 'Liên hệ cửa hàng để được hướng dẫn và giải đáp.', 'icon' => 'fa-solid fa-comments']];
                }
                $data->update(['title' => $title, 'subtitle' => in_array($block->block_type, ['tool750_hero', 'tool750_promo_banner']) ? ($en ? 'PHONE ACCESSORIES' : 'PHỤ KIỆN ĐIỆN THOẠI') : null, 'description' => in_array($block->block_type, ['tool750_hero', 'tool750_promo_banner']) ? ($en ? 'Chargers, headphones and speakers for your daily life.' : 'Sạc, tai nghe và loa cho công việc, giải trí và những chuyến đi.') : null, 'content' => json_encode(['items' => $items], JSON_UNESCAPED_UNICODE)]);
            }
        }
        ThemeDemoRecord::query()->whereKey($records->modelKeys())->update(['preset_key' => 'tool750-phone-accessories']);
    }
}
