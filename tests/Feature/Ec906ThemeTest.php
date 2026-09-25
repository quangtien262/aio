<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ec906ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec906_is_registered_with_editable_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC906');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC906'));
        $this->assertSame([
            'hero_slider',
            'ec906_benefits',
            'ec906_flash_sale',
            'ec906_family_care',
            'ec906_category_promos',
            'ec906_kitchen_products',
            'ec906_latest_posts',
            'ec906_brand_strip',
            'ec906_newsletter',
        ], collect($builder->availableBlocks('EC906'))->pluck('block_type')->all());

        $products = collect($builder->availableBlocks('EC906'))->firstWhere('block_type', 'ec906_flash_sale');
        $posts = collect($builder->availableBlocks('EC906'))->firstWhere('block_type', 'ec906_latest_posts');
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($posts, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($products, 'settings_schema'));
    }

    public function test_ec906_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC906');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec906-ega-minimart');

        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(20, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC906', 'placement' => 'ec906-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC906', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-block-type="ec906_flash_sale"', false)
            ->assertSee('data-block-type="ec906_family_care"', false)
            ->assertSee('data-block-type="ec906_kitchen_products"', false)
            ->assertSee('data-block-type="ec906_latest_posts"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('EGA Mini Mart');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Nước']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức mới nhất');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ EGA Mini Mart');

        $page = LandingPage::query()->where('theme_key', 'EC906')->where('is_home', true)->firstOrFail();
        $this->assertCount(9, $page->blocks);
    }

    public function test_news_seed_is_complete_repeatable_and_scoped_to_the_current_website(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC906');
        $context = app(SiteContext::class);
        $context->set(null, 'other-demo');
        $provider->generate('ec906-ega-minimart');
        $foreignPost = CmsPost::firstOrFail();
        $context->set(null, 'website-main');
        $manual = CmsPost::create(['title' => 'Bài viết biên tập riêng', 'slug' => 'manual-news', 'status' => 'published', 'body' => '<p>Nội dung riêng.</p>']);
        foreach (range(1, 2) as $run) {
            $result = $provider->generate('ec906-ega-minimart');
            $this->assertSame(12, $result['counts']['posts']);
            $this->assertSame(3, $result['counts']['post_categories']);
            $this->assertSame(12, $result['counts']['media']);
            $this->assertSame(13, CmsPost::count());
            $this->assertTrue(CmsPost::whereKey($manual->id)->exists());
            $this->assertTrue(CmsPost::forWebsite('other-demo')->whereKey($foreignPost->id)->exists());
        }
        $posts = CmsPost::where('slug', 'like', 'ec906-%')->with('featuredMedia')->orderByDesc('publish_at')->get();
        foreach ($posts as $post) {
            $this->assertSame('published', $post->status);
            $this->assertTrue($post->publish_at->isPast());
            $this->assertFileExists(public_path(ltrim($post->featuredMedia->file_url, '/')));
            $this->assertGreaterThanOrEqual(3, substr_count($post->body, '<h2>'));
            $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        }
        $response = $this->get('/vi/c')->assertOk()->assertSee($posts->first()->title)->assertSee('page=2', false);
        foreach (CmsCategory::where('slug', 'like', 'ec906-%')->get() as $category) {
            $this->get('/vi/c/'.$category->slug)->assertOk()->assertSee($category->name);
        }
        if ($path = getenv('EC906_NEWS_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }

    public function test_article_recommendations_exclude_current_unpublished_future_and_foreign_posts(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC906')->generate('ec906-ega-minimart');
        $post = CmsPost::orderByDesc('publish_at')->firstOrFail();
        foreach (['draft', 'future', 'foreign'] as $kind) {
            CmsPost::create([
                'website_key' => $kind === 'foreign' ? 'other-demo' : 'website-main',
                'title' => 'Excluded '.$kind, 'slug' => 'excluded-'.$kind,
                'status' => $kind === 'draft' ? 'draft' : 'published',
                'publish_at' => $kind === 'future' ? now()->addDay() : now(),
                'body' => '<p>Excluded</p>',
            ]);
        }
        $response = $this->get('/vi/n/'.$post->slug)->assertOk()
            ->assertSee('Tin mới nhất')->assertSee('Có thể bạn quan tâm')
            ->assertSee('data-ec96-toc', false)->assertSee('data-ec96-copy', false)
            ->assertDontSee('Excluded draft')->assertDontSee('Excluded future')->assertDontSee('Excluded foreign');
        $latest = $response->viewData('latestPosts');
        $related = $response->viewData('relatedPosts');
        $this->assertCount(5, $latest);
        $this->assertCount(3, $related);
        $this->assertFalse($latest->contains('id', $post->id));
        $this->assertFalse($related->contains('id', $post->id));
        $this->assertEmpty($latest->pluck('id')->intersect($related->pluck('id'))->all());
        if ($path = getenv('EC906_ARTICLE_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
        CmsPost::where('id', '!=', $post->id)->delete();
        $this->get('/vi/n/'.$post->slug)->assertOk()->assertViewHas('latestPosts', fn ($items) => $items->isEmpty())
            ->assertViewHas('relatedPosts', fn ($items) => $items->isEmpty());
    }

    public function test_ec906_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC906')?->generate('ec906-ega-minimart');

        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
