<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CmsPost;
use App\Models\CmsPostComment;
use App\Models\Customer;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class News88ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_news88_is_registered_with_editorial_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'NEWS88');
        $this->assertNotNull($theme);
        $this->assertSame('news', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/NEWS88/preview-news88.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('NEWS88'));
        $this->assertSame([
            'news88_hero_posts', 'news88_latest_video', 'news88_health_posts',
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
        $this->assertStringContainsString('$latestItems->slice(6, 2)', $home);
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
        $this->actingAs($admin, 'admin')->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('data-xd-edit-block=', false)
            ->assertSee('data-xd-editor-form', false);
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
