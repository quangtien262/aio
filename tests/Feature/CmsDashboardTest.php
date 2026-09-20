<?php

namespace Tests\Feature;

use App\Models\{Admin, CmsPost, SiteProfile};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_current_website_and_separates_scheduled_posts(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'CMS', 'website_type' => 'news']);
        $this->actingAs(Admin::factory()->create(['id' => 1]), 'admin');
        foreach (['draft', 'published', 'scheduled', 'foreign'] as $slug) {
            CmsPost::create(['title' => $slug, 'slug' => $slug, 'status' => $slug === 'draft' ? 'draft' : 'published', 'publish_at' => $slug === 'scheduled' ? now()->addDay() : now()->subDay(), 'website_key' => $slug === 'foreign' ? 'other-site' : 'website-main']);
        }
        $this->getJson('/admin/api/cms/dashboard')->assertOk()
            ->assertJsonPath('data.posts.total', 3)
            ->assertJsonPath('data.posts.published', 1)
            ->assertJsonPath('data.posts.draft', 1)
            ->assertJsonPath('data.posts.scheduled', 1)
            ->assertJsonCount(3, 'data.recent_posts')->assertDontSee('foreign');
        $module = app(\App\Core\Modules\ModuleRegistry::class)->all()->firstWhere('key', 'cms');
        $this->assertSame('/admin/cms/dashboard', $module['menus'][0]['route']);
    }
}
