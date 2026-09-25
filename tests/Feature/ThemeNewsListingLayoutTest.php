<?php

namespace Tests\Feature;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeNewsListingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_lists_render_real_articles_across_every_theme(): void
    {
        $profile = SiteProfile::create(['site_name' => 'News layout', 'website_type' => 'corporate', 'active_theme_key' => 'XD0320']);
        $category = CmsCategory::create(['name' => 'Kinh nghiệm và kiến thức', 'slug' => 'layout-news', 'description' => 'Góc chia sẻ kinh nghiệm và kiến thức hữu ích.']);
        $media = CmsMedia::create(['title' => 'News cover', 'file_path' => '', 'file_url' => '/theme-demo/xd-shared/travel-2.jpg', 'mime_type' => 'image/jpeg', 'size' => 0]);
        for ($i = 1; $i <= 31; $i++) {
            CmsPost::create(['category_id' => $category->id, 'featured_media_id' => $i % 2 ? $media->id : null, 'title' => 'Kiến thức và kinh nghiệm dành cho bạn '.$i, 'slug' => 'layout-post-'.$i, 'excerpt' => 'Nội dung giới thiệu bài viết giúp bạn tìm hiểu và lựa chọn thông tin phù hợp.', 'body' => '<p>Nội dung bài viết.</p>', 'status' => 'published', 'publish_at' => now()->subMinutes($i)]);
        }
        CmsPost::create(['category_id' => $category->id, 'title' => 'Hidden draft', 'slug' => 'hidden-draft', 'body' => 'Draft', 'status' => 'draft']);
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            foreach (['index' => '/vi/c', 'category' => '/vi/c/'.$category->slug, 'empty' => '/vi/c?q=does-not-exist'] as $mode => $url) {
                $response = $this->get($url)->assertOk()->assertSee('class="theme-news-listing"', false)->assertDontSee('Hidden draft');
                if ($mode !== 'empty') {
                    $response->assertSee('Kiến thức và kinh nghiệm dành cho bạn 1')->assertSee('/vi/n/layout-post-1', false)->assertSee('page=2', false)->assertSee('/theme-demo/xd-shared/travel-2.jpg', false);
                } else {
                    $response->assertSee('Không tìm thấy bài viết phù hợp.');
                }
                if ($folder = getenv('NEWS_LISTING_PREVIEW')) {
                    if (! is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }file_put_contents($folder.'/'.$theme.'-'.$mode.'.html', $response->getContent());
                }
            }
        }
        $second = $this->get('/vi/c/'.$category->slug.'?page=2')->assertOk()->assertSee('Kiến thức và kinh nghiệm dành cho bạn 11');
        app()->setLocale('en');
        $html = view('themes.common.news-listing', ['listingItems' => collect(), 'pageTitle' => 'News', 'activeTheme' => ['key' => 'BOOK920']])->render();
        $this->assertStringContainsString('No articles yet', $html);
        $this->assertStringNotContainsString('news-listing.', $html);
    }
}
