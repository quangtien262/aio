<?php

namespace Tests\Feature;

use App\Models\{Admin, CmsPost, CmsTopic, SiteProfile};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsTopicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'Topics', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->actingAs(Admin::factory()->create(['id' => 1]), 'admin');
    }

    public function test_topic_image_accepts_library_paths_and_can_be_cleared(): void
    {
        $payload = ['name' => 'Image topic', 'slug' => 'image-topic', 'is_active' => true, 'image_url' => '/storage/cms/topic.jpg'];
        $id = $this->postJson('/admin/api/cms/topics', $payload)->assertCreated()->assertJsonPath('data.image_url', $payload['image_url'])->json('data.id');
        $this->get('/vi/topics')->assertOk()->assertSee('src="/storage/cms/topic.jpg"', false);
        $this->putJson('/admin/api/cms/topics/'.$id, [...$payload, 'image_url' => 'https://example.com/topic.jpg'])->assertOk();
        foreach (['javascript:alert(1)', '//example.com/topic.jpg'] as $invalid) {
            $this->putJson('/admin/api/cms/topics/'.$id, [...$payload, 'image_url' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('image_url');
        }
        $this->putJson('/admin/api/cms/topics/'.$id, [...$payload, 'image_url' => null])->assertOk()->assertJsonPath('data.image_url', null);
    }

    public function test_topics_crud_post_assignments_and_tenant_boundaries(): void
    {
        $payload = ['name' => 'Sống xanh', 'slug' => 'song-xanh', 'is_active' => true];
        $id = $this->postJson('/admin/api/cms/topics', $payload)->assertCreated()->json('data.id');
        $this->postJson('/admin/api/cms/topics', $payload)->assertUnprocessable()->assertJsonValidationErrors('slug');
        $second = CmsTopic::create(['name' => 'Tết', 'slug' => 'tet', 'is_active' => true]);
        $other = CmsTopic::create(['website_key' => 'other-site', 'name' => 'Private topic', 'slug' => 'private', 'is_active' => true]);
        $this->getJson('/admin/api/cms/topics')->assertOk()->assertJsonCount(2, 'data.items');
        $postId = $this->postJson('/admin/api/cms/posts', ['title' => 'Topic post', 'status' => 'published', 'topic_ids' => [$id, $second->id]])->assertCreated()->assertJsonCount(2, 'data.topic_ids')->json('data.id');
        $this->putJson('/admin/api/cms/posts/'.$postId, ['title' => 'Topic post', 'status' => 'published', 'topic_ids' => [$other->id]])->assertUnprocessable()->assertJsonValidationErrors('topic_ids.0');
        $this->putJson('/admin/api/cms/topics/'.$other->id, $payload)->assertNotFound();
        $this->deleteJson('/admin/api/cms/topics/'.$other->id)->assertNotFound();
        $this->putJson('/admin/api/cms/topics/'.$id, [...$payload, 'name' => 'Sống xanh mới'])->assertOk();
        $this->getJson('/admin/api/cms/posts')->assertOk()->assertJsonCount(2, 'data.topics');
        $this->deleteJson('/admin/api/cms/topics/'.$id)->assertOk();
        $this->assertDatabaseHas('cms_posts', ['id' => $postId]);
        $this->assertCount(1, CmsPost::findOrFail($postId)->topics);
        $this->putJson('/admin/api/cms/posts/'.$postId, ['title' => 'Topic post', 'status' => 'published', 'topic_ids' => []])->assertOk()->assertJsonPath('data.topic_ids', []);
    }

    public function test_bulk_topics_replace_clear_and_reject_other_websites(): void
    {
        $first = CmsTopic::create(['name' => 'First', 'slug' => 'first']);
        $second = CmsTopic::create(['name' => 'Second', 'slug' => 'second']);
        $foreign = CmsTopic::create(['website_key' => 'other-site', 'name' => 'Other', 'slug' => 'other']);
        $posts = collect(['one', 'two', 'untouched'])->map(fn ($slug) => CmsPost::create(['title' => $slug, 'slug' => $slug, 'status' => 'draft']));
        foreach ($posts as $post) $post->topics()->attach($first);
        $ids = $posts->take(2)->pluck('id')->all();
        $this->putJson('/admin/api/cms/posts/bulk', ['ids' => $ids, 'topic_ids' => [$second->id]])->assertOk()->assertJsonPath('data.updated', 2);
        foreach ($posts->take(2) as $post) $this->assertSame([$second->id], $post->topics()->pluck('cms_topics.id')->all());
        $this->assertSame([$first->id], $posts->last()->topics()->pluck('cms_topics.id')->all());
        $this->putJson('/admin/api/cms/posts/bulk', ['ids' => $ids, 'topic_ids' => [$foreign->id]])->assertUnprocessable();
        $otherPost = CmsPost::create(['website_key' => 'other-site', 'title' => 'Other', 'slug' => 'other', 'status' => 'draft']);
        $this->putJson('/admin/api/cms/posts/bulk', ['ids' => [...$ids, $otherPost->id], 'topic_ids' => []])->assertUnprocessable();
        $this->assertSame([$second->id], $posts->first()->topics()->pluck('cms_topics.id')->all());
        $this->putJson('/admin/api/cms/posts/bulk', ['ids' => $ids, 'topic_ids' => [$first->id, $second->id]])->assertOk();
        $this->assertCount(2, $posts->first()->fresh()->topics);
        $this->putJson('/admin/api/cms/posts/bulk', ['ids' => $ids, 'topic_ids' => []])->assertOk();
        foreach ($posts->take(2) as $post) $this->assertCount(0, $post->fresh()->topics);
    }

    public function test_public_topic_page_menu_links_and_sitemap(): void
    {
        SiteProfile::firstOrFail()->update(['branding' => ['cms' => ['menu_locations' => [['label' => 'Menu chính', 'value' => 'primary-navigation']]]]]);
        $topic = CmsTopic::create(['name' => 'Sống xanh', 'slug' => 'song-xanh', 'description' => 'Chuyên đề môi trường', 'is_active' => true]);
        $this->postJson('/admin/api/cms/menus', ['name' => 'Topics menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Sống xanh', 'url' => '/topics/song-xanh', 'link_type' => 'post-topic', 'link_value' => (string) $topic->id]]])->assertCreated();
        for ($i = 1; $i <= 12; $i++) {
            $post = CmsPost::create(['title' => 'Public topic '.$i, 'slug' => 'public-topic-'.$i, 'status' => 'published', 'publish_at' => now()->subDays($i)]);
            $post->topics()->attach($topic);
        }
        foreach ([['status' => 'draft'], ['status' => 'published', 'publish_at' => now()->addDay()], ['status' => 'published', 'website_key' => 'other-site']] as $i => $values) {
            $post = CmsPost::create(['title' => 'Hidden sentinel '.$i, 'slug' => 'hidden-'.$i, ...$values]);
            $post->topics()->attach($topic);
        }
        $this->get('/vi/topics/song-xanh')->assertOk()->assertSee('Sống xanh')->assertSee('Public topic 1')->assertDontSee('Hidden sentinel')->assertSee('page=2', false);
        $this->get('/vi/topics/song-xanh?page=2')->assertOk()->assertSee('Public topic 12');
        $this->getJson('/admin/api/cms/menus')->assertOk()->assertJsonPath('data.linkOptions.postTopics.0.value', (string) $topic->id);
        $this->get('/vi')->assertOk()->assertSee('/vi/topics/song-xanh', false);
        $this->get('/sitemaps/topics-1.xml')->assertOk()->assertSee('/vi/topics/song-xanh', false);
        $topic->update(['slug' => 'song-xanh-moi']);
        $this->get('/vi/topics/song-xanh')->assertRedirect('/vi/topics/song-xanh-moi');
        $topic->update(['is_active' => false]);
        $this->get('/vi/topics/song-xanh-moi')->assertNotFound();
    }

    public function test_existing_posts_and_menus_work_before_topics_migration(): void
    {
        $migration = require base_path('modules/Cms/database/migrations/2026_09_20_000001_create_cms_topics_tables.php');
        $migration->down();
        try {
            $this->getJson('/admin/api/cms/posts')->assertOk()->assertJsonPath('data.topics', []);
            $this->getJson('/admin/api/cms/menus')->assertOk()->assertJsonPath('data.linkOptions.postTopics', []);
            $this->postJson('/admin/api/cms/posts', ['title' => 'Without topics', 'status' => 'published', 'topic_ids' => []])->assertCreated();
            $this->postJson('/admin/api/cms/posts', ['title' => 'Invalid', 'status' => 'published', 'topic_ids' => [1]])->assertUnprocessable()->assertJsonValidationErrors('topic_ids');
            $this->getJson('/admin/api/cms/topics')->assertStatus(503);
        } finally {
            $migration->up();
        }
    }

    public function test_topic_translations_use_published_localized_slugs_and_posts(): void
    {
        app(\App\Support\Localization\WebsiteLocaleManager::class)->updateLocale('website-main', 'en', ['is_published' => true]);
        $topic = CmsTopic::create(['name' => 'Sống xanh', 'slug' => 'song-xanh', 'is_active' => true]);
        $post = CmsPost::create(['title' => 'Bài gốc', 'slug' => 'bai-goc', 'status' => 'published']);
        $post->topics()->attach($topic);
        $endpoint = '/admin/api/localization/content/cms_topic/'.$topic->id.'/en';
        $this->getJson('/admin/api/cms/topics?locale=en')->assertOk()->assertJsonPath('data.items.0._translation_status', 'missing')->assertJsonPath('data.items.0.name', 'Sống xanh');
        $this->putJson($endpoint, ['payload' => ['name' => 'Green draft', 'slug' => 'green-draft'], 'publish' => false])->assertOk();
        $this->getJson('/admin/api/cms/topics?locale=en')->assertOk()->assertJsonPath('data.items.0._translation_status', 'draft');
        $this->get('/en/topics/green-draft')->assertNotFound();
        $this->putJson($endpoint, ['payload' => ['name' => 'Green living', 'slug' => 'green-living'], 'publish' => true])->assertOk();
        $this->getJson('/admin/api/cms/topics?locale=en')->assertOk()->assertJsonPath('data.items.0.name', 'Green living')->assertJsonPath('data.items.0._source.name', 'Sống xanh')->assertJsonPath('data.items.0._translation_status', 'published');
        $this->getJson('/admin/api/cms/posts?locale=en')->assertOk()->assertJsonPath('data.topics.0.label', 'Green living');
        $this->getJson('/admin/api/cms/menus?locale=en')->assertOk()->assertJsonPath('data.linkOptions.postTopics.0.label', 'Green living')->assertJsonPath('data.linkOptions.postTopics.0.url', '/topics/green-living');
        $this->get('/en/topics/green-living')->assertOk()->assertDontSee('Bài gốc');
        $this->putJson('/admin/api/localization/content/cms_post/'.$post->id.'/en', ['payload' => ['title' => 'Green story', 'slug' => 'green-story'], 'publish' => true])->assertOk();
        $this->get('/en/topics/green-living')->assertOk()->assertSee('Green story')->assertSee('/en/n/green-story', false);
        $this->putJson($endpoint, ['payload' => ['name' => 'Green future', 'slug' => 'green-future'], 'publish' => true])->assertOk();
        $this->get('/en/topics/green-living')->assertRedirect('/en/topics/green-future');
        $this->assertSame('Sống xanh', $topic->fresh()->name);
    }
}



