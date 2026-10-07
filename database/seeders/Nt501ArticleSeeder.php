<?php

namespace Database\Seeders;

use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Nt501ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            'Cách chọn phong cách nội thất cho căn hộ' => 'Bắt đầu từ thói quen sinh hoạt, diện tích và những chất liệu bạn yêu thích để xác định phong cách phù hợp.',
            'Tối ưu không gian sống trong căn hộ nhỏ' => 'Ưu tiên lối đi thông thoáng, đồ nội thất đa năng và hệ lưu trữ vừa vặn với từng khu vực.',
            'Phối màu trung tính để ngôi nhà ấm áp hơn' => 'Kết hợp sắc trắng, be và màu gỗ, thử mẫu màu dưới ánh sáng thực tế trước khi lựa chọn.',
            'Ánh sáng cho phòng khách hiện đại' => 'Phối hợp ánh sáng tự nhiên, ánh sáng chung và đèn điểm nhấn theo nhu cầu sử dụng trong ngày.',
            'Lựa chọn vật liệu cho tủ bếp' => 'So sánh khả năng chịu ẩm, cách vệ sinh và ngân sách; kiểm tra mẫu vật liệu trước khi thi công.',
            'Bố trí góc làm việc tại nhà' => 'Chọn vị trí ít bị làm phiền, có ánh sáng phù hợp và đủ chỗ cho thiết bị cùng đồ dùng thường ngày.',
            'Thiết kế phòng ngủ để nghỉ ngơi thoải mái' => 'Giảm chi tiết gây rối mắt, bố trí lối đi cạnh giường và lựa chọn nguồn sáng dịu cho buổi tối.',
            'Lưu trữ gọn gàng cho nhà phố' => 'Tận dụng chiều cao và các khoảng trống, phân chia đồ dùng theo tần suất sử dụng để dễ tìm kiếm.',
            'Chuẩn bị trước khi cải tạo nội thất' => 'Ghi lại nhu cầu, khảo sát hiện trạng và thống nhất phạm vi, ngân sách cùng lịch thi công.',
            'Chăm sóc đồ gỗ và bề mặt nội thất' => 'Tham khảo hướng dẫn của nhà sản xuất, thử sản phẩm vệ sinh ở vùng nhỏ và giữ bề mặt khô thoáng.',
        ];
        $brief = json_decode(file_get_contents(resource_path('demo/remaining-themes.json')), true, 512, JSON_THROW_ON_ERROR)['NT501'];
        $keys = Site::where('theme_key', 'NT501')->pluck('website_key')
            ->merge(SiteProfile::withoutGlobalScope('current_website')->where('active_theme_key', 'NT501')->pluck('website_key'))->filter()->unique();
        DB::transaction(function () use ($keys, $articles, $brief): void {
            foreach ($keys as $websiteKey) {
                $index = 0;
                foreach ($articles as $title => $excerpt) {
                    $slug = Str::slug('NT501-'.$title);
                    if (CmsPost::query()->forWebsite($websiteKey)->where('slug', $slug)->exists()) {
                        $index++;

                        continue;
                    }
                    $image = CmsMedia::create([
                        'website_key' => $websiteKey, 'title' => $title, 'alt_text' => $title,
                        'file_path' => '', 'file_url' => $brief['images'][$index % count($brief['images'])],
                        'mime_type' => $index % 3 === 2 ? 'image/jpeg' : 'image/png',
                    ]);
                    $post = CmsPost::create([
                        'website_key' => $websiteKey, 'title' => $title, 'slug' => $slug,
                        'status' => 'published', 'publish_at' => now()->subDays($index),
                        'excerpt' => $excerpt, 'featured_media_id' => $image->id,
                        'body' => '<h2>'.e($title).'</h2><p>'.e($excerpt).'</p><h2>Gợi ý khi lên phương án</h2><p>Đối chiếu ý tưởng với kích thước thực tế, thói quen sử dụng và ngân sách. Trao đổi với đơn vị thiết kế để kiểm tra tính phù hợp của vật liệu, ánh sáng và bố trí trước khi triển khai.</p><p>Nội dung mẫu tham khảo cho website NT501.</p>',
                    ]);
                    foreach ([$image, $post] as $record) {
                        ThemeDemoRecord::create([
                            'website_key' => $websiteKey, 'theme_key' => 'NT501', 'preset_key' => $brief['preset'],
                            'model_type' => $record::class, 'model_id' => $record->id,
                        ]);
                    }
                    $index++;
                }
            }
        });
    }
}
