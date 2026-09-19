<?php

namespace Tests\Feature;

use App\Models\{Admin, CmsPost, LocalizedRoute, ModuleInstallation, Site, SiteProfile};
use App\Support\{CmsPostTags, SitemapService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'Sitemap', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
    }

    private function makePost(string $slug, array $values = []): CmsPost
    {
        return CmsPost::create(array_merge(['website_key' => 'website-main', 'title' => 'Title '.$slug, 'slug' => $slug,
            'status' => 'published', 'publish_at' => now()->subDay()], $values));
    }

    public function test_xml_filters_private_future_deleted_and_other_site_content_and_empty_tags(): void
    {
        $public = $this->makePost('public');
        $this->makePost('draft', ['status' => 'draft']);
        $this->makePost('future', ['publish_at' => now()->addDay()]);
        $this->makePost('other', ['website_key' => 'other-site']);
        $deleted = $this->makePost('deleted'); $deleted->delete();
        app(CmsPostTags::class)->sync($public, ['Visible tag']);
        app(CmsPostTags::class)->sync($this->makePost('hidden', ['status' => 'draft']), ['Empty tag']);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/sitemaps/posts-1.xml', false);
        $response = $this->get('/sitemaps/posts-1.xml')->assertOk()->assertSee('/vi/n/public', false)
            ->assertDontSee('/n/draft', false)->assertDontSee('/n/future', false)->assertDontSee('/n/deleted', false)->assertDontSee('/n/other', false);
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->get('/sitemaps/tags-1.xml')->assertOk()->assertSee('visible-tag')->assertDontSee('empty-tag');
        $this->get('/sitemaps/posts-999.xml')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertSee('/sitemap.xml')->assertSee('Disallow: /admin');
    }

    public function test_cache_updates_after_bulk_changes_and_due_publication_and_module_disable(): void
    {
        $post = $this->makePost('scheduled', ['publish_at' => now()->addMinutes(2)]);
        $this->get('/sitemap.xml')->assertDontSee('posts-1.xml');
        $this->travel(3)->minutes();
        $this->get('/sitemaps/posts-1.xml')->assertOk()->assertSee('scheduled');
        CmsPost::whereKey($post->id)->update(['status' => 'draft']);
        // Bulk changes bypass model events; the TTL bounds stale output to one minute.
        $this->travel(2)->minutes();
        $this->get('/sitemap.xml')->assertDontSee('posts-1.xml');
        $this->makePost('enabled');
        $this->get('/sitemap.xml')->assertSee('posts-1.xml');
        ModuleInstallation::create(['key' => 'cms', 'name' => 'CMS', 'version' => '1.0', 'status' => 'disabled']);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('posts-1.xml')->assertDontSee('pages-1.xml');
    }

    public function test_splitting_domains_hreflang_and_admin_refresh(): void
    {
        Site::updateOrCreate(['website_key' => 'website-main'], ['domain' => 'news.example.test', 'theme_key' => 'NEWS88', 'status' => 'active']);
        config(['sitemap.urls_per_file' => 2]);
        foreach (range(1, 3) as $i) $this->makePost('article-'.$i);
        $this->get('/sitemap.xml')->assertOk()->assertSee('https://news.example.test/sitemaps/posts-2.xml', false)->assertDontSee('localhost');
        $this->get('/sitemaps/posts-1.xml')->assertOk()->assertSee('hreflang="vi"', false);
        $this->actingAs(Admin::factory()->create(['id' => 1]), 'admin');
        $this->getJson('/admin/api/sitemap')->assertOk()->assertJsonPath('data.url', 'https://news.example.test/sitemap.xml');
        $this->postJson('/admin/api/sitemap/refresh')->assertOk()->assertJsonStructure(['data' => ['total', 'files', 'generated_at']]);
    }
}
