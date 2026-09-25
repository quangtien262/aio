<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\CmsPostComment;
use App\Models\Customer;
use App\Models\LandingPageBlock;
use App\Models\SiteProfile;
use App\Models\WebsiteLocale;
use App\Support\CmsPostTags;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\LocaleContext;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class News88ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_listing_has_thirty_posts_per_page_without_card_excerpts(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $category = CmsCategory::create(['name' => 'Listing category', 'slug' => 'listing-category']);
        for ($i = 1; $i <= 31; $i++) {
            CmsPost::create(['title' => 'Listing post '.$i, 'slug' => 'listing-post-'.$i, 'category_id' => $category->id,
                'excerpt' => 'CARD_EXCERPT_SHOULD_BE_HIDDEN', 'status' => 'published', 'publish_at' => now()->subMinutes($i)]);
        }
        foreach (['/vi/c', '/vi/c/listing-category'] as $url) {
            foreach ([1 => 30, 2 => 1] as $page => $expected) {
                $html = $this->get($url.'?page='.$page)->assertOk()->getContent();
                $this->assertSame(1, preg_match('/<section class="tnl-grid"[^>]*>(.*?)<\/section>/s', $html, $matches));
                $this->assertSame($expected, substr_count($matches[1], '<article class="tnl-card">'));
                $this->assertStringNotContainsString('CARD_EXCERPT_SHOULD_BE_HIDDEN', $matches[1]);
            }
        }
    }

    public function test_recent_footer_posts_match_home_on_child_pages(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88')->generate('news88-editorial');
        $block = LandingPageBlock::where('block_type', 'news88_footer_posts')->firstOrFail();
        $block->update(['settings' => ['source' => 'cms_posts', 'limit' => 5, 'featured_only' => false]]);
        $post = CmsPost::where('status', 'published')->firstOrFail();
        $expected = null;
        foreach (['/vi', '/vi/n/'.$post->slug, '/vi/c'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, preg_match('/<div class="n88-footer-posts">(.*?)<\/div>/s', $html, $matches));
            $this->assertSame(5, substr_count($matches[1], '<a '));
            $expected ??= $matches[1];
            $this->assertSame($expected, $matches[1]);
        }
        $block->update(['is_visible' => false]);
        $this->get('/vi/n/'.$post->slug)->assertOk()->assertDontSee('class="n88-footer-posts"', false);
    }

    public function test_news_columns_respect_limits_and_health_omits_excerpts(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88')->generate('news88-editorial');
        $types = ['news88_car_posts', 'news88_travel_posts', 'news88_entertainment_posts'];
        foreach ([5, 2] as $limit) {
            foreach (LandingPageBlock::whereIn('block_type', $types)->get() as $block) {
                $block->update(['settings' => ['source' => 'cms_posts', 'limit' => $limit, 'featured_only' => false]]);
            }
            $html = $this->get('/vi')->assertOk()->getContent();
            foreach ($types as $type) {
                $this->assertSame(1, preg_match('/<div[^>]*data-block-type="'.$type.'"[^>]*>(.*?)<\/div>/s', $html, $matches));
                $this->assertSame($limit, substr_count($matches[1], '<article '));
            }
            $this->assertSame(1, preg_match('/<section[^>]*id="suc-khoe"[^>]*>(.*?)<\/section>/s', $html, $matches));
            $this->assertStringContainsString('<article ', $matches[1]);
            $this->assertStringNotContainsString('<p>', $matches[1]);
        }
    }

    public function test_footer_shows_public_post_tags_instead_of_menu_keywords(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->get('/vi')->assertOk()->assertDontSee('class="n88-footer-tags"', false);
        $tags = app(CmsPostTags::class);
        $post = CmsPost::create(['title' => 'Public', 'slug' => 'public', 'status' => 'published']);
        $tags->sync($post, ['Công nghệ']);
        $draft = CmsPost::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
        $tags->sync($draft, ['Draft tag']);
        $other = CmsPost::create(['website_key' => 'other-site', 'title' => 'Other', 'slug' => 'other', 'status' => 'published']);
        $tags->sync($other, ['Other tag']);
        foreach (['/vi', '/vi/n/public'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame(1, preg_match('/<section class="n88-footer-tags">(.*?)<\/section>/s', $response->getContent(), $matches));
            $this->assertStringContainsString('/vi/tags/cong-nghe', $matches[1]);
            $this->assertStringContainsString('Công nghệ', $matches[1]);
            $this->assertStringNotContainsString('Draft tag', $matches[1]);
            $this->assertStringNotContainsString('Other tag', $matches[1]);
        }
    }

    public function test_video_block_renders_the_configured_number_of_posts(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88')->generate('news88-editorial');
        $block = LandingPageBlock::where('block_type', 'news88_video_posts')->firstOrFail();
        foreach ([4, 1] as $limit) {
            $block->update(['settings' => ['source' => 'cms_posts', 'limit' => $limit, 'featured_only' => false]]);
            $response = $this->get('/vi')->assertOk();
            $this->assertSame(1, preg_match('/<aside[^>]*id="video"[^>]*>(.*?)<\/aside>/s', $response->getContent(), $matches));
            $this->assertSame($limit, substr_count($matches[1], '<article '));
        }
    }

    public function test_demo_generation_respects_unpublished_english_on_new_websites(): void
    {
        $context = app(SiteContext::class);
        $previousSite = $context->site();
        $previousKey = $context->websiteKey();
        $context->set(null, 'news88-new-site');
        try {
            SiteProfile::create(['website_key' => 'news88-new-site', 'site_name' => 'New site', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
            WebsiteLocale::query()->where('website_key', 'news88-new-site')->where('locale', 'en')
                ->update(['is_enabled_for_editing' => true, 'is_published' => false]);
            app(LocaleContext::class)->flush('news88-new-site');
            $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88');
            $result = $provider->generate('news88-editorial');
            $this->assertSame(22, $result['counts']['posts']);
            $this->assertDatabaseHas('content_translations', ['website_key' => 'news88-new-site', 'resource_type' => 'cms_post', 'locale' => 'en', 'translation_status' => 'ready']);
            $this->assertDatabaseMissing('content_translations', ['website_key' => 'news88-new-site', 'locale' => 'en', 'translation_status' => 'published']);
            $this->assertFalse(app(LocaleContext::class)->isPublic('en', 'news88-new-site'));
            WebsiteLocale::query()->where('website_key', 'news88-new-site')->where('locale', 'en')->update(['is_enabled_for_editing' => false]);
            app(LocaleContext::class)->flush('news88-new-site');
            $this->assertSame(22, $provider->generate('news88-editorial')['counts']['posts']);
        } finally {
            $context->set($previousSite, $previousKey);
        }
    }

    public function test_article_sidebar_has_ten_latest_posts_and_tag_fallback(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $post = CmsPost::create(['title' => 'Current', 'slug' => 'current', 'status' => 'published']);
        foreach (range(1, 12) as $number) {
            CmsPost::create(['title' => 'Recent '.$number, 'slug' => 'recent-'.$number, 'status' => 'published', 'publish_at' => now()->subDays($number)]);
        }
        $response = $this->get('/vi/n/current')->assertOk()->assertDontSee('id="n88-sidebar-tags-title"', false);
        preg_match('/<div class="n88-article-latest">(.*?)<\/aside>/s', $response->getContent(), $matches);
        $this->assertSame(10, substr_count($matches[1], '<article>'));
        $this->assertStringContainsString('Recent 10', $matches[1]);
        $this->assertStringNotContainsString('Recent 11', $matches[1]);
        $tags = app(CmsPostTags::class);
        $tags->sync(CmsPost::where('slug', 'recent-1')->firstOrFail(), array_map(fn ($n) => 'Popular '.$n, range(1, 12)));
        $response = $this->get('/vi/n/current')->assertOk();
        preg_match('/<section class="n88-sidebar-tags"(.*?)<\/section>/s', $response->getContent(), $matches);
        $this->assertSame(10, substr_count($matches[1], 'rel="tag"'));
        $tags->sync($post, ['Own tag']);
        $response = $this->get('/vi/n/current')->assertOk();
        preg_match('/<section class="n88-sidebar-tags"(.*?)<\/section>/s', $response->getContent(), $matches);
        $this->assertSame(1, substr_count($matches[1], 'rel="tag"'));
        $this->assertStringContainsString('Own tag', $matches[1]);
        $this->assertStringContainsString('/vi/tags/own-tag', $matches[1]);
    }

    public function test_hotbar_uses_only_public_highlighted_posts_independently_of_hero(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        foreach (range(1, 7) as $number) {
            CmsPost::create(['title' => 'Regular '.$number, 'slug' => 'regular-'.$number, 'status' => 'published', 'is_highlight' => false, 'publish_at' => now()->subDay()]);
        }
        foreach (range(1, 2) as $number) {
            CmsPost::create(['title' => 'Highlighted '.$number, 'slug' => 'highlighted-'.$number, 'status' => 'published', 'is_highlight' => true, 'publish_at' => now()->subYear()]);
        }
        CmsPost::create(['title' => 'Future', 'slug' => 'future', 'status' => 'published', 'is_highlight' => true, 'publish_at' => now()->addDay()]);
        CmsPost::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'is_highlight' => true]);
        CmsPost::create(['website_key' => 'other-site', 'title' => 'Other', 'slug' => 'other', 'status' => 'published', 'is_highlight' => true]);
        $response = $this->get('/vi')->assertOk();
        preg_match('/<section class="n88-hotbar"[^>]*>(.*?)<\/section>/s', $response->getContent(), $matches);
        $hotbar = $matches[1];
        $this->assertSame(2, substr_count($hotbar, '<a '));
        $this->assertStringContainsString('Highlighted 1', $hotbar);
        $this->assertStringContainsString('Highlighted 2', $hotbar);
        CmsPost::query()->where('is_highlight', true)->update(['is_highlight' => false]);
        $response = $this->get('/vi')->assertOk();
        preg_match('/<section class="n88-hotbar"[^>]*>(.*?)<\/section>/s', $response->getContent(), $matches);
        $this->assertStringNotContainsString('<a ', $matches[1]);
    }

    public function test_admin_header_link_requires_an_admin_session(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->get('/vi')->assertOk()->assertDontSee('class="n88-auth-admin"', false);
        $this->actingAs(Customer::factory()->create(), 'customer');
        $this->get('/vi')->assertOk()->assertDontSee('class="n88-auth-admin"', false);
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['/vi', '/vi?mod=admin'] as $url) {
            $this->get($url)->assertOk()->assertSee('class="n88-auth-admin" href="'.url('/admin').'"', false);
        }
    }

    public function test_header_search_targets_news_and_filters_post_content(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        foreach (['title', 'excerpt', 'body'] as $field) {
            CmsPost::create(array_merge([
                'website_key' => 'website-main', 'title' => 'Article '.$field, 'slug' => 'search-'.$field,
                'status' => 'published', 'publish_at' => now()->subDay(),
            ], [$field => 'Needle '.$field]));
        }
        CmsPost::create(['title' => 'Unrelated article', 'slug' => 'unrelated', 'status' => 'published']);
        CmsPost::create(['title' => 'Needle draft', 'slug' => 'draft', 'status' => 'draft']);
        $this->get('/vi')->assertOk()->assertSee('action="'.url('/vi/c').'"', false);
        $response = $this->get('/vi/c?q=Needle')->assertOk()
            ->assertSee('search-title')->assertSee('search-excerpt')->assertSee('search-body')
            ->assertDontSee('Needle draft')
            ->assertSee('value="Needle"', false)->assertSee('Kết quả tìm kiếm cho');
        $this->assertSame(1, preg_match('/<section class="tnl-grid"[^>]*>(.*?)<\/section>/s', $response->getContent(), $matches));
        $this->assertStringNotContainsString('Unrelated article', $matches[1]);
        $this->get('/vi/c?q=NoMatchingKeyword')->assertOk()->assertSee('Không tìm thấy bài viết phù hợp.');
    }

    public function test_social_links_are_configurable_and_empty_links_are_hidden(): void
    {
        SiteProfile::query()->create(['website_key' => 'website-main', 'site_name' => 'Social test', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->actingAs(Admin::factory()->create(['id' => 1]), 'admin');
        $this->putJson('/admin/api/themes/NEWS88/settings', [
            'facebook_url' => 'https://facebook.com/example',
            'x_url' => 'https://x.com/example',
            'youtube_url' => 'https://youtube.com/@example',
        ])->assertOk();
        $this->get('/vi')->assertOk()->assertSee('href="https://facebook.com/example"', false)
            ->assertSee('href="https://x.com/example"', false)->assertSee('href="https://youtube.com/@example"', false);
        $html = $this->get('/vi')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<div class="n88-footer-social">(.*?)<\/div>/s', $html, $matches));
        foreach (['https://facebook.com/example', 'https://x.com/example', 'https://youtube.com/@example'] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $matches[1]);
        }
        $this->assertStringNotContainsString('href="#"', $matches[1]);
        $this->putJson('/admin/api/themes/NEWS88/settings', ['facebook_url' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->putJson('/admin/api/themes/NEWS88/settings', ['facebook_url' => null])->assertOk();
        $this->get('/vi')->assertOk()->assertDontSee('aria-label="Facebook"', false)->assertSee('aria-label="YouTube"', false);
    }

    public function test_news88_is_registered_with_editorial_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'NEWS88');
        $this->assertNotNull($theme);
        $this->assertSame('news', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/NEWS88/'.$theme['preview']['thumbnail']));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('NEWS88'));
        $this->assertSame([
            'news88_hero_posts', 'news88_latest_video', 'news88_video_posts', 'news88_health_posts',
            'news88_car_posts', 'news88_travel_posts', 'news88_entertainment_posts',
            'news88_footer_posts',
        ], collect($builder->availableBlocks('NEWS88'))->pluck('block_type')->all());
        $latestVideo = collect($builder->availableBlocks('NEWS88'))->firstWhere('block_type', 'news88_latest_video');
        $this->assertSame(8, data_get($latestVideo, 'settings_schema.limit.default'));
    }

    public function test_demo_provider_generates_localized_posts_and_preserves_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Bản tin khách hàng', 'website_type' => 'news', 'active_theme_key' => 'NEWS88',
            'branding' => ['logo_url' => '/storage/branding/customer-news.svg', 'support_email' => 'desk@example.test'],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88');
        $this->assertNotNull($provider);
        $result = $provider->generate('news88-editorial');
        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(22, data_get($result, 'counts.posts'));
        $this->assertDatabaseHas('content_translations', ['resource_type' => 'cms_post', 'locale' => 'en', 'translation_status' => 'published']);
        $this->assertSame('/storage/branding/customer-news.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('/storage/branding/customer-news.svg', false)
            ->assertSee('Cù lao được mệnh danh', false)
            ->assertSee('class="n88-topbar"', false)
            ->assertSee('class="n88-container n88-nav-wrap"', false)
            ->assertSee(route('customer.auth.login'), false)
            ->assertSee(route('customer.auth.register'), false)
            ->assertSee('class="n88-auth-links"', false)
            ->assertSee('data-storefront-language-switcher', false)
            ->assertSee('data-block-type="news88_health_posts"', false);
        $this->get(route('site.home', ['locale' => 'en']))->assertOk()
            ->assertSee('The Mekong island known as the kingdom of mangroves')
            ->assertSee('Health');

        $header = file_get_contents(base_path('themes/NEWS88/views/partials/header.blade.php'));
        $this->assertLessThan(
            strpos($header, 'class="n88-container n88-nav-wrap"'),
            strpos($header, "@include('partials.storefront-language-switcher')"),
        );
        $home = file_get_contents(base_path('themes/NEWS88/views/home.blade.php'));
        $this->assertStringContainsString('$latestItems->take(6)', $home);
        $this->assertStringContainsString('@foreach($videoItems as $item)', $home);
        $styles = file_get_contents(base_path('themes/NEWS88/views/partials/styles.blade.php'));
        $this->assertStringContainsString('.n88-footer::before,.n88-footer::after', $styles);
        $this->assertStringContainsString('background-size:44px 44px', $styles);
        $this->assertStringContainsString('linear-gradient(145deg,#081522', $styles);
        $this->assertStringContainsString('.n88-tags a:hover', $styles);
        $this->assertStringContainsString('.n88-auth-links{', $styles);
    }

    public function test_news88_admin_mode_exposes_landing_editor_controls(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'NEWS88', 'website_type' => 'news', 'active_theme_key' => 'NEWS88', 'branding' => [],
        ]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88')?->generate('news88-editorial');

        $admin = Admin::factory()->create();
        $response = $this->actingAs($admin, 'admin')->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('data-xd-edit-block=', false)
            ->assertSee('data-xd-editor-form', false)
            ->assertDontSee('const blocks = [];', false)
            ->assertDontSee('const updateUrlTemplate = "";', false)
            ->assertDontSee('const sourcePreviewUrlTemplate = "";', false);

        $this->assertSame(8, preg_match_all('/<button[^>]+data-xd-edit-block="[0-9]+"/', $response->getContent()));
        $this->assertSame(1, substr_count($response->getContent(), '<script data-xd-editor-runtime>'));
        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertDontSee('data-xd-edit-block=', false)
            ->assertDontSee('<script data-xd-editor-runtime>', false);
    }

    public function test_news_detail_shows_related_latest_and_requires_account_for_threaded_comments(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'NEWS88', 'website_type' => 'news', 'active_theme_key' => 'NEWS88', 'branding' => [],
        ]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('NEWS88')?->generate('news88-editorial');

        $post = CmsPost::query()->where('status', 'published')->firstOrFail();
        $otherPost = CmsPost::query()->whereKeyNot($post->getKey())->where('status', 'published')->firstOrFail();
        $detailUrl = route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]);

        $this->get($detailUrl)->assertOk()
            ->assertSee('id="n88-related-title"', false)
            ->assertSee('class="n88-article-sidebar"', false)
            ->assertSee('id="binh-luan"', false)
            ->assertSee(route('customer.auth.login', ['locale' => 'vi']), false)
            ->assertSee(route('customer.auth.register', ['locale' => 'vi']), false);

        $this->from($detailUrl)
            ->post(route('site.blog.comments.store', ['locale' => 'vi', 'post' => $post->getKey()]))
            ->assertRedirect(route('customer.auth.login', ['locale' => 'vi']));

        $customer = Customer::factory()->create(['name' => 'Độc giả NEWS88']);
        $this->actingAs($customer, 'customer')->from($detailUrl)
            ->post(route('site.blog.comments.store', ['locale' => 'vi', 'post' => $post->getKey()]), [
                'body' => '<script>alert(1)</script> Bình luận đầu tiên',
            ])->assertRedirect($detailUrl.'#binh-luan');

        $root = CmsPostComment::query()->firstOrFail();
        $this->assertSame('alert(1) Bình luận đầu tiên', $root->body);
        $this->assertSame($customer->getKey(), $root->customer_id);

        $this->actingAs($customer, 'customer')->from($detailUrl)
            ->post(route('site.blog.comments.store', ['locale' => 'vi', 'post' => $post->getKey()]), [
                'body' => 'Đây là câu trả lời',
                'parent_id' => $root->getKey(),
            ])->assertRedirect($detailUrl.'#binh-luan');

        $this->assertDatabaseHas('cms_post_comments', [
            'cms_post_id' => $post->getKey(),
            'parent_id' => $root->getKey(),
            'body' => 'Đây là câu trả lời',
        ]);

        $foreignParent = CmsPostComment::query()->create([
            'website_key' => $otherPost->website_key,
            'cms_post_id' => $otherPost->getKey(),
            'customer_id' => $customer->getKey(),
            'body' => 'Bình luận bài khác',
            'status' => 'published',
        ]);
        $this->actingAs($customer, 'customer')->post(
            route('site.blog.comments.store', ['locale' => 'vi', 'post' => $post->getKey()]),
            ['body' => 'Không được nối sai bài', 'parent_id' => $foreignParent->getKey()],
        )->assertNotFound();

        $this->get($detailUrl)->assertOk()
            ->assertSee('Độc giả NEWS88')
            ->assertSee('Bình luận đầu tiên')
            ->assertSee('Đây là câu trả lời');
    }
}
