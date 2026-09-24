<?php

namespace Tests\Feature;

use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeNewsListingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_category_pages_render_across_themes(): void
    {
        $profile = SiteProfile::create(['site_name' => 'News layout', 'website_type' => 'corporate', 'active_theme_key' => 'XD0320']);
        $category = CmsCategory::create(['name' => 'Kinh nghiệm và kiến thức', 'slug' => 'layout-news', 'description' => 'Góc chia sẻ kinh nghiệm và kiến thức hữu ích.']);
        for ($i = 1; $i <= 3; $i++) {
            CmsPost::create(['category_id' => $category->id, 'title' => 'Kiến thức và kinh nghiệm dành cho bạn '.$i, 'slug' => 'layout-post-'.$i, 'excerpt' => 'Nội dung giới thiệu bài viết giúp bạn tìm hiểu và lựa chọn thông tin phù hợp.', 'body' => '<p>Nội dung bài viết.</p>', 'status' => 'published', 'publish_at' => now()]);
        }
        foreach (glob(base_path('themes/*/views/news.blade.php')) as $file) {
            $theme = basename(dirname($file, 2));
            $profile->update(['active_theme_key' => $theme]);
            $response = $this->get('/vi/c/'.$category->slug)->assertOk();
            if (str_contains(file_get_contents($file), 'themes.common.news-listing-styles')) {
                $response->assertSee('xd-news-listing', false)->assertSee('Kiến thức và kinh nghiệm dành cho bạn 1');
            }
            if ($folder = getenv('NEWS_LISTING_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$theme.'.html', $response->getContent());
            }
        }
    }
}
