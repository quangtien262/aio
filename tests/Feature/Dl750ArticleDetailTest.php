<?php

namespace Tests\Feature;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dl750ArticleDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_renders_editorial_layout_and_only_public_related_posts(): void
    {
        SiteProfile::create(['site_name' => 'Forest Camp', 'website_type' => 'ecommerce', 'active_theme_key' => 'DL750']);
        $category = CmsCategory::create(['name' => 'Kinh nghiệm dã ngoại', 'slug' => 'kinh-nghiem']);
        $media = CmsMedia::create(['title' => 'Hành trình', 'file_path' => '', 'file_url' => '/theme-demo/xd-shared/travel-3.jpg', 'alt_text' => 'Khám phá giữa thiên nhiên']);
        $post = CmsPost::create([
            'category_id' => $category->id, 'featured_media_id' => $media->id,
            'title' => 'Cách đánh giá một phương án du lịch cắm trại phù hợp', 'slug' => 'cam-trai-phu-hop',
            'status' => 'published', 'publish_at' => now()->subDay(),
            'excerpt' => 'Tìm hiểu lịch trình, trang bị và những điều cần chuẩn bị để tận hưởng chuyến đi giữa thiên nhiên.',
            'body' => '<h2 id="your-plan">Xác định lịch trình</h2><p>Lựa chọn điểm đến và thời gian phù hợp với cả nhóm.</p><h3>Chuẩn bị trang bị</h3><p>Kiểm tra những vật dụng cần thiết trước khi khởi hành.</p><blockquote>Một kế hoạch rõ ràng giúp bạn tận hưởng hành trình.</blockquote><h2>Trao đổi trước chuyến đi</h2><p>Xác nhận phương án và các hạng mục dịch vụ.</p>',
        ]);
        foreach (['Chọn điểm cắm trại', 'Sắp xếp hành trang'] as $i => $title) {
            CmsPost::create(['category_id' => $category->id, 'title' => $title, 'slug' => 'related-'.$i, 'status' => 'published', 'publish_at' => now()->subDays(2), 'excerpt' => 'Gợi ý cho những hành trình ngoài trời.', 'featured_media_id' => $media->id]);
        }
        CmsPost::create(['category_id' => $category->id, 'title' => 'Nội dung hẹn giờ', 'slug' => 'future', 'status' => 'published', 'publish_at' => now()->addDays(3)]);
        CmsPost::create(['category_id' => $category->id, 'title' => 'Bản nháp riêng', 'slug' => 'draft', 'status' => 'draft']);
        CmsPost::create(['website_key' => 'another-site', 'title' => 'Bài website khác', 'slug' => 'other-site', 'status' => 'published', 'publish_at' => now()]);
        $response = $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))
            ->assertOk()->assertSee('Kinh nghiệm dã ngoại')->assertSee('Khám phá giữa thiên nhiên')
            ->assertSee('id="your-plan"', false)->assertSee('data-dl-article-toc', false)
            ->assertSee('phút đọc · ước tính')->assertSee('Chọn điểm cắm trại')->assertSee('Sắp xếp hành trang')
            ->assertDontSee('Nội dung hẹn giờ')->assertDontSee('Bản nháp riêng')->assertDontSee('Bài website khác');
        foreach ([0, 1] as $i) {
            $url = route('site.blog.show', ['locale' => 'vi', 'slug' => 'related-'.$i]);
            $response->assertSee($url, false);
            $this->get($url)->assertOk();
        }
        if (getenv('DL750_ARTICLE_PREVIEW')) {
            file_put_contents(getenv('DL750_ARTICLE_PREVIEW'), $response->getContent());
        }
    }

    public function test_article_without_optional_content_has_no_empty_cover_or_related_section(): void
    {
        SiteProfile::create(['site_name' => 'Forest Camp', 'website_type' => 'ecommerce', 'active_theme_key' => 'DL750']);
        $post = CmsPost::create(['title' => 'Ghi chép hành trình', 'slug' => 'ghi-chep', 'status' => 'published', 'publish_at' => now()]);
        $response = $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))
            ->assertOk()->assertSee('Nội dung bài viết đang được cập nhật.')
            ->assertDontSee('<figure class="dl-article-cover">', false)
            ->assertDontSee('id="dl-article-related-title"', false)->assertDontSee('phút đọc · ước tính');
        if (getenv('DL750_ARTICLE_PREVIEW')) {
            file_put_contents(getenv('DL750_ARTICLE_PREVIEW').'.empty.html', $response->getContent());
        }
    }
}
