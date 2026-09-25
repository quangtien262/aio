<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\CmsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot403ThemeTest extends TestCase
{
    use RefreshDatabase;

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
