<?php

namespace App\Core\Themes\Demo;

use App\Models\{CatalogCategory, CatalogProduct, CatalogProductImage, CmsCategory, CmsMenu, CmsPage, CmsMedia, CmsPost, CmsProject, CmsProjectImage, CmsService, CmsServiceCategory, ThemeDemoRecord};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Additional content owned by the XD0307 preset, not by the shared finalizer. */
class Xd0307DemoCatalog
{
    private const PRESET = 'xd0307-cleaning-services';

    private function record(Model $model): void
    {
        ThemeDemoRecord::create(['theme_key' => 'XD0307', 'preset_key' => self::PRESET, 'model_type' => $model::class, 'model_id' => $model->id]);
    }

    private function media(string $title, string $file): CmsMedia
    {
        $media = CmsMedia::create(['title' => $title, 'alt_text' => $title, 'file_path' => '', 'file_url' => '/theme-demo/xd0307/'.$file, 'mime_type' => 'image/webp']);
        $this->record($media);
        return $media;
    }

    public function seed(CmsCategory $newsCategory): array
    {
        $equipment = CatalogCategory::create(['name' => 'Thiết bị làm sạch', 'slug' => 'xd0307-thiet-bi-lam-sach', 'description' => 'Thiết bị hỗ trợ vệ sinh và chăm sóc không gian sống.', 'is_active' => true, 'image_url' => '/theme-demo/xd0307/robot-vacuum.webp']);
        $air = CatalogCategory::create(['name' => 'Chăm sóc không gian sống', 'slug' => 'xd0307-cham-soc-khong-gian', 'is_active' => true, 'image_url' => '/theme-demo/xd0307/air-purifier.webp']);
        $this->record($equipment); $this->record($air);
        foreach ([
            ['Robot hút bụi tự động', 'robot-vacuum.webp', 4990000, $equipment, 'Hỗ trợ hút bụi sàn nhà trong lịch chăm sóc hằng ngày.'],
            ['Máy giặt cửa trước', 'washing-machine.webp', 7990000, $equipment, 'Thiết bị giặt đồ gia dụng cho nhu cầu chăm sóc vải thường xuyên.'],
            ['Máy rửa bát gia đình', 'dishwasher.webp', 8990000, $equipment, 'Hỗ trợ làm sạch bát đĩa và tổ chức công việc nhà bếp.'],
            ['Máy lọc không khí', 'air-purifier.webp', 2990000, $air, 'Bổ sung thiết bị chăm sóc không khí cho phòng sinh hoạt.'],
        ] as $index => [$name, $file, $price, $category, $summary]) {
            $product = CatalogProduct::create(['catalog_category_id' => $category->id, 'name' => $name, 'slug' => Str::slug('xd0307-'.$name), 'sku' => 'KL-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'price' => $price, 'original_price' => $price + 500000, 'stock' => 12, 'short_description' => $summary, 'detail_content' => '<h2>Giới thiệu sản phẩm</h2><p>'.$summary.'</p><h2>Tư vấn trước khi chọn mua</h2><p>Trao đổi diện tích sử dụng, vị trí lắp đặt và nhu cầu thực tế để chọn thiết bị phù hợp. Đây là sản phẩm minh họa của bộ dữ liệu mẫu; thông số và chính sách cần được cập nhật theo hàng thực tế.</p>', 'highlights' => "Sản phẩm minh họa\nTư vấn theo nhu cầu sử dụng\nKiểm tra hướng dẫn trước khi vận hành", 'image_url' => '/theme-demo/xd0307/'.$file, 'is_active' => true, 'is_featured' => true, 'sort_order' => $index]);
            $this->record($product);
            $photo = CatalogProductImage::create(['catalog_product_id' => $product->id, 'image_url' => $product->image_url, 'alt_text' => $name, 'sort_order' => 0]);
            $this->record($photo);
        }
        $serviceCategory = CmsServiceCategory::create(['name' => 'Dịch vụ vệ sinh Klean', 'slug' => 'xd0307-dich-vu-ve-sinh', 'is_active' => true]);
        $this->record($serviceCategory);
        CmsService::whereKey(ThemeDemoRecord::where('theme_key', 'XD0307')->where('model_type', CmsService::class)->pluck('model_id'))->update(['cms_service_category_id' => $serviceCategory->id]);
        $aboutMedia = $this->media('Đội ngũ vệ sinh Klean', 'reasons-team.webp');
        $pages = [];
        foreach ([
            ['gioi-thieu', 'Giới thiệu Klean Services', 'Dịch vụ vệ sinh cho nhà ở và doanh nghiệp.', '<h2>Chăm sóc từng không gian</h2><p>Klean Services cung cấp vệ sinh nhà ở, văn phòng, công trình sau xây dựng và nội thất. Mỗi công việc bắt đầu bằng việc trao đổi nhu cầu và thống nhất phạm vi thực hiện.</p><h2>Quy trình minh bạch</h2><p>Tiếp nhận thông tin → khảo sát → báo giá → thực hiện → kiểm tra và bàn giao. Khách hàng được thông báo trước về lịch làm việc và các khu vực cần chuẩn bị.</p><h2>Con người và thiết bị</h2><p>Đội ngũ phối hợp dụng cụ, thiết bị và phương pháp phù hợp với từng bề mặt. Chúng tôi hướng dẫn cách duy trì vệ sinh sau buổi bàn giao.</p>'],
            ['chinh-sach-dich-vu', 'Chính sách dịch vụ', 'Thông tin tham khảo khi đặt dịch vụ vệ sinh.', '<h2>Đặt lịch và xác nhận</h2><p>Phạm vi, thời gian và báo giá được xác nhận trước khi triển khai.</p><h2>Thay đổi lịch</h2><p>Liên hệ đội ngũ tư vấn khi cần điều chỉnh để thống nhất phương án phù hợp.</p><h2>Bàn giao</h2><p>Khách hàng và đội ngũ cùng kiểm tra các hạng mục đã thống nhất. Chính sách này là nội dung mẫu, cần được chủ website duyệt trước khi sử dụng chính thức.</p>'],
            ['huong-dan-dat-lich', 'Hướng dẫn đặt lịch', 'Chuẩn bị thông tin để nhận tư vấn phù hợp.', '<h2>Thông tin cần cung cấp</h2><p>Địa điểm, diện tích, loại bề mặt, hạng mục cần làm sạch và thời gian mong muốn.</p><h2>Gửi yêu cầu</h2><p>Sử dụng biểu mẫu liên hệ hoặc gọi hotline. Đội ngũ sẽ trao đổi để xác nhận phạm vi và lịch thực hiện.</p>'],
        ] as [$slug, $title, $excerpt, $body]) {
            $page = CmsPage::create(['title' => $title, 'slug' => 'xd0307-'.$slug, 'excerpt' => $excerpt, 'body' => $body, 'featured_media_id' => $slug === 'gioi-thieu' ? $aboutMedia->id : null, 'status' => 'published', 'publish_at' => now(), 'meta_title' => $title]);
            $this->record($page); $pages[$slug] = $page;
        }
        foreach (CmsPost::where('category_id', $newsCategory->id)->orderBy('id')->get() as $index => $post) {
            $media = $this->media($post->title, ['clean-home.webp', 'service-deep-clean.webp', 'service-upholstery.webp'][$index % 3]);
            $post->update(['featured_media_id' => $media->id]);
        }
        foreach ([['Vệ sinh văn phòng sau cải tạo', 'service-deep-clean.webp'], ['Làm sạch khu bếp gia đình', 'gallery-kitchen.webp'], ['Chăm sóc sân và lối đi', 'hero-pressure-washing.webp']] as $index => [$title, $file]) {
            $project = CmsProject::create(['title' => $title, 'slug' => Str::slug('xd0307-'.$title), 'summary' => 'Dự án minh họa quy trình khảo sát, làm sạch và bàn giao.', 'content' => '<h2>Phạm vi công việc</h2><p>Khảo sát bề mặt, bảo vệ đồ vật, thực hiện vệ sinh theo khu vực và kiểm tra kết quả cùng khách hàng.</p><h2>Hình ảnh minh họa</h2><p>Nội dung thuộc bộ demo Klean Services, không phải hồ sơ công trình thực tế.</p>', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true, 'sort_order' => $index]);
            $this->record($project);
            $photo = CmsProjectImage::create(['cms_project_id' => $project->id, 'image_url' => '/theme-demo/xd0307/'.$file, 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
            $this->record($photo);
        }
        $menu = CmsMenu::whereKey(ThemeDemoRecord::where('theme_key', 'XD0307')->where('model_type', CmsMenu::class)->pluck('model_id'))->where('location', 'primary-navigation')->firstOrFail();
        $menu->update(['items' => [
            ['label' => 'Trang chủ', 'link_type' => 'home', 'url' => route('site.home', [], false)],
            ['label' => 'Giới thiệu', 'link_type' => 'page', 'link_value' => (string) $pages['gioi-thieu']->id, 'url' => route('site.pages.show', ['slug' => $pages['gioi-thieu']->slug], false)],
            ['label' => 'Dịch vụ', 'link_type' => 'service-category', 'link_value' => (string) $serviceCategory->id, 'url' => route('site.services.category', ['slug' => $serviceCategory->slug], false)],
            ['label' => 'Sản phẩm', 'link_type' => 'catalog-index', 'url' => route('site.catalog.search', [], false)],
            ['label' => 'Dự án', 'link_type' => 'project-index', 'url' => route('site.projects.index', [], false)],
            ['label' => 'Tin tức', 'link_type' => 'post-category', 'link_value' => (string) $newsCategory->id, 'url' => route('site.blog.category', ['slug' => $newsCategory->slug], false)],
            ['label' => 'Liên hệ', 'link_type' => 'contact', 'url' => route('site.contact', [], false)],
        ]]);
        return ['products' => 4, 'categories' => 2, 'pages' => 3, 'projects' => 3, 'service_categories' => 1, 'media' => 4];
    }

    public function delete(): array
    {
        $counts = [];
        foreach ([CatalogProductImage::class, CatalogProduct::class, CatalogCategory::class, CmsProjectImage::class, CmsProject::class, CmsPage::class, CmsMedia::class, CmsServiceCategory::class] as $model) {
            $records = ThemeDemoRecord::where('theme_key', 'XD0307')->where('preset_key', self::PRESET)->where('model_type', $model)->get();
            $counts[(new $model)->getTable()] = $model::whereKey($records->pluck('model_id'))->delete();
            ThemeDemoRecord::whereKey($records->pluck('id'))->delete();
        }
        return $counts;
    }
}
