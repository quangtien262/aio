<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\ThemeDemoRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot403ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_news_uses_published_posts_instead_of_static_cards(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('FOOT403', 'foot403-complete');
        $page = LandingPage::where('theme_key', 'FOOT403')->firstOrFail();
        $block = $page->blocks()->where('block_type', 'bizmax_latest_posts')->firstOrFail();
        $this->assertSame('cms_posts', $block->settings['source']);
        ThemeDemoRecord::where('model_type', LandingPage::class)->where('model_id', $page->id)->delete();
        $block->update(['settings' => ['source' => 'custom', 'limit' => 3]]);
        $generator->generate('FOOT403', 'foot403-complete');
        $this->assertSame('cms_posts', $block->fresh()->settings['source']);
        $posts = CmsPost::where('status', 'published')->get();
        $this->assertCount(3, $posts);
        CmsPost::create(['title' => 'Scheduled news', 'slug' => 'scheduled-news', 'status' => 'published', 'publish_at' => now()->addDay()]);
        CmsPost::create(['title' => 'Draft news', 'slug' => 'draft-news', 'status' => 'draft']);
        CmsPost::create(['website_key' => 'other-website', 'title' => 'Other website news', 'slug' => 'other-website-news', 'status' => 'published']);
        $response = $this->get('/vi')->assertOk()->assertDontSee('Admin Dola')->assertDontSee('Scheduled news')->assertDontSee('Draft news')->assertDontSee('Other website news');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $section = $xpath->query('//section[@id="tin-tuc"]')->item(0);
        $html = $dom->saveHTML($section);
        $this->assertSame(3, $xpath->query('.//article', $section)->length);
        foreach ($posts as $post) {
            $this->assertStringContainsString($post->title, $html);
            $this->assertStringContainsString($post->featuredMedia->file_url, $html);
            $this->assertStringContainsString($post->publish_at->format('d/m/Y'), $html);
            $url = route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]);
            $this->assertStringContainsString($url, $html);
            $this->get($url)->assertOk()->assertSee($post->body, false);
        }
        CmsPost::query()->update(['status' => 'draft']);
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$this->get('/vi')->assertOk()->getContent());
        $this->assertSame(0, (new \DOMXPath($dom))->query('//section[@id="tin-tuc"]//article')->length);
    }

    public function test_featured_categories_use_seeded_catalog_data_and_live_links(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        CatalogCategory::create(['name' => 'Combo gia đình', 'slug' => 'foot403-combo-gia-dinh', 'image_url' => '/theme-demo/foot409/promo-pizza.png', 'description' => 'Existing category', 'is_active' => true, 'sort_order' => 0]);
        $generator->generate('FOOT403', 'foot403-complete');
        $page = LandingPage::where('theme_key', 'FOOT403')->firstOrFail();
        ThemeDemoRecord::where('model_type', LandingPage::class)->where('model_id', $page->id)->delete();
        $block = $page->blocks()->where('block_type', 'featured_categories')->firstOrFail();
        $block->update(['settings' => ['source' => 'custom', 'limit' => 8]]);
        $generator->generate('FOOT403', 'foot403-complete');
        $this->assertSame('catalog_categories', $block->fresh()->settings['source']);
        $this->assertSame('Existing category', CatalogCategory::where('slug', 'foot403-combo-gia-dinh')->firstOrFail()->description);
        $categories = CatalogCategory::orderBy('sort_order')->get();
        $this->assertCount(3, $categories);
        $this->assertSame(['Combo gia đình', 'Pizza', 'Burger'], $categories->pluck('name')->all());
        $response = $this->get('/vi')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//section[@id="danh-muc"]//a[@class="dr-category-link"]');
        $this->assertSame(3, $links->length);
        foreach ($categories as $index => $category) {
            $this->assertSame(1, $category->products()->count());
            $this->assertFileExists(public_path($category->image_url));
            $this->assertStringContainsString($category->name, $links->item($index)->textContent);
            $url = $links->item($index)->getAttribute('href');
            $this->assertStringContainsString($category->slug, $url);
            $this->get($url)->assertOk()->assertSee($category->products()->first()->name);
        }
        CatalogCategory::query()->update(['is_active' => false]);
        $html = $this->get('/vi')->assertOk()->getContent();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $this->assertSame(0, (new \DOMXPath($dom))->query('//section[@id="danh-muc"]//article')->length);
    }

    public function test_seeded_news_links_render_the_article_body_and_cms_fallback(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('FOOT403', 'foot403-complete');
        $listing = $this->get('/vi/c')->assertOk();
        foreach (CmsPost::where('status', 'published')->get() as $post) {
            $url = route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]);
            $listing->assertSee($url, false);
            $response = $this->get($url)->assertOk()->assertSee($post->title)->assertSee($post->body, false)
                ->assertDontSee("@include('theme-foot403::partials.scripts')", false);
            $this->assertStringContainsString('data-dr-order-open', $response->getContent());
            $fallback = view('theme-foot403::cms', $response->original->getData())->render();
            $this->assertStringContainsString($post->body, $fallback);
            $this->assertStringContainsString(e($post->title), $fallback);
            $this->assertStringNotContainsString("@include('theme-foot403::partials.scripts')", $fallback);
            if ($path = getenv('FOOT403_DETAIL_PREVIEW')) {
                file_put_contents($path, $response->getContent());
            }
        }
        $page = CmsPage::firstOrFail();
        $this->get(route('site.pages.show', ['locale' => 'vi', 'slug' => $page->slug]))->assertOk()->assertSee($page->title)->assertSee($page->body, false);
    }

    public function test_foot403_storefront_admin_mode_renders_landing_block_editor(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('FOOT403', 'foot403-complete');
        $this->actingAs(Admin::factory()->create(), 'admin');

        $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']));

        $response
            ->assertOk()
            ->assertSee('data-xd-editor', false)
            ->assertSee('data-xd-edit-block', false)
            ->assertSee('Sửa khối');

        $this->assertSame(9, substr_count($response->getContent(), 'data-xd-edit-block='));
        $this->assertSame(9, substr_count($response->getContent(), 'data-landing-block-id='));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertDontSee('data-xd-editor', false)
            ->assertDontSee('data-xd-edit-block', false);
    }
}
