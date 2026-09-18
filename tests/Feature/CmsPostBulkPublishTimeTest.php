<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPostBulkPublishTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_publish_time_updates_only_selected_posts_and_validates_dates(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->actingAs(Admin::factory()->create(['id' => 1]), 'admin');
        $posts = collect(range(1, 3))->map(fn ($id) => CmsPost::create([
            'title' => 'Post '.$id, 'slug' => 'post-'.$id, 'status' => 'draft',
            'publish_at' => '2026-09-01 08:00:00',
        ]));
        $ids = $posts->take(2)->pluck('id')->all();
        $this->putJson('/admin/api/cms/posts/bulk', ['ids' => $ids, 'publish_at' => '2026-09-18 14:30:00'])
            ->assertOk()->assertJsonPath('data.updated', 2);
        foreach ($posts->take(2) as $post) {
            $this->assertSame('2026-09-18 14:30:00', $post->fresh()->publish_at->format('Y-m-d H:i:s'));
            $this->assertSame('draft', $post->fresh()->status);
        }
        $this->assertSame('2026-09-01 08:00:00', $posts->last()->fresh()->publish_at->format('Y-m-d H:i:s'));
        foreach ([null, 'invalid-date'] as $value) {
            $this->putJson('/admin/api/cms/posts/bulk', ['ids' => $ids, 'publish_at' => $value])
                ->assertUnprocessable()->assertJsonValidationErrors('publish_at');
        }
        $this->assertSame('2026-09-18 14:30:00', $posts->first()->fresh()->publish_at->format('Y-m-d H:i:s'));
    }
}
