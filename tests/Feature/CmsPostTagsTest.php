<?php

namespace Tests\Feature;

use App\Enums\TranslationStatus;
use App\Models\Admin;
use App\Models\CmsPost;
use App\Models\CmsTag;
use App\Models\SiteProfile;
use App\Support\CmsPostTags;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\WebsiteLocaleManager;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsPostTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteProfile::query()->create(['website_key' => 'website-main', 'site_name' => 'Tags site', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->actingAs(Admin::factory()->create(['id' => 1]), 'admin');
    }

    public function test_admin_can_create_update_and_remove_tags_without_changing_other_posts(): void
    {
        $id = $this->postJson('/admin/api/cms/posts', ['title' => 'Tagged article', 'status' => 'published', 'tags' => [' Du lịch ', 'du lịch', 'Ẩm thực']])
            ->assertCreated()->assertJsonCount(2, 'data.tags')->json('data.id');
        $post = CmsPost::findOrFail($id);
        $second = CmsPost::create(['title' => 'Second', 'slug' => 'second', 'status' => 'published']);
        app(CmsPostTags::class)->sync($second, ['DU LỊCH']);
        $this->assertSame(2, CmsTag::count());
        $this->getJson('/admin/api/cms/posts')->assertOk()->assertJsonCount(2, 'data.tagOptions');
        $this->putJson('/admin/api/cms/posts/'.$id, ['title' => 'Tagged article', 'status' => 'published', 'tags' => []])->assertOk()->assertJsonPath('data.tags', []);
        $this->assertCount(1, $second->fresh()->tags);
        $this->assertSame(2, CmsTag::count());
        app(CmsPostTags::class)->sync($post, ['Existing']);
        $this->putJson('/admin/api/cms/posts/'.$id, ['title' => 'Tagged article', 'status' => 'published'])->assertOk();
        $this->assertCount(1, $post->fresh()->tags);
        $this->postJson('/admin/api/cms/posts', ['title' => 'Invalid', 'status' => 'published', 'tags' => [str_repeat('a', 81)]])->assertUnprocessable();
        $this->postJson('/admin/api/cms/posts', ['title' => 'Invalid', 'status' => 'published', 'tags' => array_fill(0, 21, 'Tag')])->assertUnprocessable();
        $this->assertDatabaseMissing('cms_posts', ['title' => 'Invalid']);
    }

    public function test_posts_remain_usable_until_the_tags_migration_is_applied(): void
    {
        $migration = require base_path('modules/Cms/database/migrations/2026_09_17_000001_create_cms_tags.php');
        foreach ([false, true] as $dropTags) {
            Schema::dropIfExists('cms_post_tag');
            if ($dropTags) {
                Schema::dropIfExists('cms_tags');
            }
            try {
                $id = $this->postJson('/admin/api/cms/posts', ['title' => 'Without tags '.(int) $dropTags, 'status' => 'published', 'tags' => []])
                    ->assertCreated()->assertJsonPath('data.tags', [])->json('data.id');
                $this->getJson('/admin/api/cms/posts')->assertOk()->assertJsonPath('data.tagsAvailable', false)->assertJsonPath('data.tagOptions', []);
                $this->putJson('/admin/api/cms/posts/'.$id, ['title' => 'Changed '.(int) $dropTags, 'status' => 'published', 'tags' => []])->assertOk();
                $this->putJson('/admin/api/cms/posts/'.$id, ['title' => 'Must roll back', 'status' => 'published', 'tags' => ['Tag']])
                    ->assertUnprocessable()->assertJsonValidationErrors('tags');
                $this->assertSame('Changed '.(int) $dropTags, CmsPost::findOrFail($id)->title);
                $this->get('/vi/n/'.CmsPost::findOrFail($id)->slug)->assertOk();
                $this->get('/vi/tags/missing')->assertNotFound();
            } finally {
                $migration->up();
            }
            $this->getJson('/admin/api/cms/posts')->assertOk()->assertJsonPath('data.tagsAvailable', true);
        }
    }

    public function test_tag_page_is_paginated_and_excludes_drafts_future_and_other_websites(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $post = CmsPost::create(['title' => 'Public '.$i, 'slug' => 'public-'.$i, 'status' => 'published', 'publish_at' => now()->subDay()]);
            app(CmsPostTags::class)->sync($post, ['Du lịch']);
        }
        $tag = CmsTag::firstOrFail();
        foreach ([['draft', null, 'website-main'], ['published', now()->addDay(), 'website-main'], ['published', null, 'website-other']] as $index => [$status, $date, $website]) {
            $hidden = CmsPost::create(['title' => 'Hidden sentinel '.$index, 'slug' => 'hidden-'.$index, 'status' => $status, 'publish_at' => $date, 'website_key' => $website]);
            app(CmsPostTags::class)->sync($hidden, ['Du lịch']);
        }
        $url = '/vi/tags/'.$tag->slug;
        $response = $this->get($url)->assertOk()->assertSee('Bài viết về: Du lịch')->assertDontSee('Hidden sentinel');
        $this->assertSame(31, $response->viewData('listingItems')->total());
        $this->assertCount(30, $response->viewData('listingItems')->items());
        $this->get($url.'?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.url($url).'?page=2">', false);
        $this->get('/vi/n/public-0')->assertOk()->assertSee('rel="tag"', false)->assertSee($url, false);
        $this->get('/vi/tags/missing')->assertNotFound();
    }

    public function test_tag_writes_require_existing_cms_permissions_and_post_delete_keeps_shared_tags(): void
    {
        $post = CmsPost::create(['title' => 'Private write', 'slug' => 'private-write', 'status' => 'published']);
        app(CmsPostTags::class)->sync($post, ['Shared tag']);
        $tag = CmsTag::firstOrFail();
        $this->actingAs(Admin::factory()->create(['status' => 'active', 'auth_version' => 1, 'must_change_password' => false]), 'admin');
        $this->putJson('/admin/api/cms/posts/'.$post->id, ['title' => 'Denied', 'status' => 'published', 'tags' => []])->assertForbidden();
        $this->putJson('/admin/api/localization/content/cms_tag/'.$tag->id.'/en', ['payload' => ['name' => 'Denied']])->assertForbidden();
        $this->assertDatabaseHas('cms_post_tag', ['cms_post_id' => $post->id, 'cms_tag_id' => $tag->id]);
        $post->delete();
        $this->assertDatabaseMissing('cms_post_tag', ['cms_post_id' => $post->id]);
        $this->assertDatabaseHas('cms_tags', ['id' => $tag->id]);
    }

    public function test_tag_identity_is_website_scoped_and_slug_collisions_are_safe(): void
    {
        $post = CmsPost::create(['title' => 'Post', 'slug' => 'post', 'status' => 'published']);
        app(CmsPostTags::class)->sync($post, ['C++', 'C#']);
        $this->assertSame(2, $post->tags()->count());
        $this->assertSame(2, $post->tags()->pluck('slug')->unique()->count());
        $other = CmsPost::create(['title' => 'Other', 'slug' => 'other', 'status' => 'published', 'website_key' => 'website-other']);
        app(SiteContext::class)->set(null, 'website-other');
        app(CmsPostTags::class)->sync($other, ['Other only']);
        $foreignTag = $other->tags()->firstOrFail();
        app(SiteContext::class)->set(null, 'website-main');
        $this->get('/vi/tags/'.$foreignTag->slug)->assertNotFound();
        $this->getJson('/admin/api/cms/posts')->assertOk()->assertJsonCount(2, 'data.tagOptions');
        $this->getJson('/admin/api/localization/content/cms_tag/'.$foreignTag->id)->assertNotFound();
    }

    public function test_tag_translation_api_validates_and_preserves_old_urls(): void
    {
        app(WebsiteLocaleManager::class)->updateLocale('website-main', 'en', ['is_published' => true]);
        $post = CmsPost::create(['title' => 'Post', 'slug' => 'post', 'status' => 'published']);
        app(CmsPostTags::class)->sync($post, ['Du lịch']);
        $tag = CmsTag::firstOrFail();
        $endpoint = '/admin/api/localization/content/cms_tag/'.$tag->id.'/en';
        $this->putJson($endpoint, ['payload' => ['name' => str_repeat('x', 81)], 'publish' => true])->assertUnprocessable();
        $this->putJson($endpoint, ['payload' => ['name' => 'Travel', 'slug' => 'travel'], 'publish' => true])->assertOk();
        $this->putJson($endpoint, ['payload' => ['name' => 'Tourism', 'slug' => 'tourism'], 'publish' => true])->assertOk();
        $this->get('/en/tags/travel')->assertRedirect('/en/tags/tourism')->assertStatus(301);
        $this->get('/en/tags/tourism')->assertOk()->assertSee('Posts tagged: Tourism');
        $this->assertSame('Du lịch', $tag->fresh()->name);
    }

    public function test_translated_tag_only_lists_published_current_post_translations(): void
    {
        app(WebsiteLocaleManager::class)->updateLocale('website-main', 'en', ['is_published' => true]);
        $post = CmsPost::create(['title' => 'Bài tiếng Việt', 'slug' => 'bai-viet', 'status' => 'published']);
        app(CmsPostTags::class)->sync($post, ['Du lịch']);
        $tag = CmsTag::firstOrFail();
        $repository = app(LocalizedContentRepository::class);
        $tagTranslation = $repository->saveDraftPayload('website-main', 'cms_tag', (string) $tag->id, 'en', ['name' => 'Travel', 'slug' => 'travel']);
        $repository->transition($repository->transition($tagTranslation, TranslationStatus::Ready), TranslationStatus::Published);
        $draft = $repository->saveDraftPayload('website-main', 'cms_post', (string) $post->id, 'en', ['title' => 'Travel story', 'slug' => 'travel-story']);
        $response = $this->get('/en/tags/travel')->assertOk()->assertDontSee('Travel story')->assertDontSee('Bài tiếng Việt');
        $this->assertSame(0, $response->viewData('listingItems')->total());
        $repository->transition($repository->transition($draft, TranslationStatus::Ready), TranslationStatus::Published);
        $this->get('/en/tags/travel')->assertOk()->assertSee('Travel story')->assertSee('/en/tags/travel', false)
            ->assertSee('hreflang="vi" href="'.url('/vi/tags/'.$tag->slug).'"', false);
        $this->get('/en/n/travel-story')->assertOk()->assertSee('Travel')->assertSee('/en/tags/travel', false);
        $post->update(['title' => 'Nguồn đã thay đổi']);
        $this->get('/en/tags/travel')->assertOk()->assertDontSee('Travel story');
    }
}
