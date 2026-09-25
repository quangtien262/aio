<?php

namespace Tests\Feature;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeNewsDetailLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_theme_renders_article_and_published_recommendations(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Article preview', 'website_type' => 'corporate', 'active_theme_key' => 'EC906']);
        $category = CmsCategory::create(['name' => 'Kinh nghiệm hữu ích', 'slug' => 'article-category']);
        $media = CmsMedia::create(['title' => 'Cover', 'file_path' => '', 'file_url' => '/theme-demo/xd-shared/travel-2.jpg', 'mime_type' => 'image/jpeg', 'size' => 0]);
        for ($i = 0; $i < 14; $i++) {
            CmsPost::create(['category_id' => $category->id, 'featured_media_id' => $i % 2 === 0 ? $media->id : null, 'title' => 'Khám phá những kinh nghiệm hữu ích cho cuộc sống '.$i, 'slug' => 'article-'.$i, 'excerpt' => 'Những gợi ý giúp bạn tìm hiểu thông tin và đưa ra lựa chọn phù hợp.', 'body' => '<h2>Chuẩn bị trước khi bắt đầu</h2><p>Nội dung thực tế dành cho bạn đọc.</p><h2 id="existing-heading">Những điều cần lưu ý</h2><p>Hãy kiểm tra thông tin theo nhu cầu của bạn.</p><h3>Tham khảo thêm</h3><p>Khám phá thêm những bài viết hữu ích.</p>', 'status' => 'published', 'publish_at' => now()->subDays($i + 1)]);
        }
        foreach (['draft', 'future', 'foreign'] as $kind) {
            CmsPost::create(['website_key' => $kind === 'foreign' ? 'other-demo' : 'website-main', 'title' => 'Forbidden '.$kind, 'slug' => 'forbidden-'.$kind, 'status' => $kind === 'draft' ? 'draft' : 'published', 'publish_at' => $kind === 'future' ? now()->addDay() : now()]);
        }
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            $response = $this->get('/vi/n/article-0')->assertOk()->assertSee('Khám phá những kinh nghiệm hữu ích cho cuộc sống 0')->assertSee('Nội dung thực tế dành cho bạn đọc.')->assertSee('/theme-demo/xd-shared/travel-2.jpg', false)
                ->assertDontSee('Forbidden draft')->assertDontSee('Forbidden foreign')->assertDontSee('Forbidden future');
            $this->assertNotEmpty($response->viewData('latestPosts'), $theme);
            $this->assertNotEmpty($response->viewData('relatedPosts'), $theme);
            $this->assertFalse($response->viewData('latestPosts')->contains('slug', 'article-0'), $theme);
            if ($folder = getenv('ARTICLE_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$theme.'.html', $response->getContent());
            }
        }
        $post = CmsPost::where('slug', 'article-0')->firstOrFail();
        CmsPost::where('id', '!=', $post->id)->delete();
        $post->update(['featured_media_id' => null, 'body' => '', 'excerpt' => 'Nội dung tóm tắt.']);
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            $response = $this->get('/vi/n/article-0')->assertOk();
            $this->assertCount(0, $response->viewData('latestPosts'), $theme);
            $this->assertCount(0, $response->viewData('relatedPosts'), $theme);
            if ($folder = getenv('ARTICLE_PREVIEW')) {
                file_put_contents($folder.'/'.$theme.'-sparse.html', $response->getContent());
            }
        }
        app()->setLocale('en');
        $html = view('themes.common.news-detail', ['entry' => $post->fresh(), 'activeTheme' => ['key' => 'BOOK920'], 'latestPosts' => collect(), 'relatedPosts' => collect()])->render();
        $this->assertStringContainsString('Copy link', $html);
        $this->assertStringNotContainsString('news-detail.', $html);
    }
}
