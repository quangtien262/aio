<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\MainWebsiteTemplateSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SiteMappingBulkTemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_domain_creation_syncs_main_website_only_for_ht_vietnam_demo_domain(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $synchronizer = Mockery::mock(MainWebsiteTemplateSynchronizer::class);
        $synchronizer->shouldReceive('supports')
            ->once()
            ->with('demo.htvietnam.vn')
            ->andReturnTrue();
        $synchronizer->shouldReceive('syncThemes')
            ->once()
            ->withArgs(fn ($themes, $rootDomain): bool => $themes->isNotEmpty()
                && $rootDomain === 'demo.htvietnam.vn')
            ->andReturn([
                'inserted' => 3,
                'updated' => 2,
                'items' => [],
            ]);
        $this->app->instance(MainWebsiteTemplateSynchronizer::class, $synchronizer);

        $this->postJson('/admin/api/site-mappings/bulk', [
            'root_domain' => 'https://demo.htvietnam.vn/',
            'content_mode' => 'blank',
        ])
            ->assertCreated()
            ->assertJsonPath('data.website_templates.inserted', 3)
            ->assertJsonPath('data.website_templates.updated', 2);
    }

    public function test_bulk_domain_creation_does_not_sync_an_unrelated_root_domain(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $synchronizer = Mockery::mock(MainWebsiteTemplateSynchronizer::class);
        $synchronizer->shouldReceive('supports')
            ->once()
            ->with('demo.example.com')
            ->andReturnFalse();
        $synchronizer->shouldNotReceive('syncThemes');
        $this->app->instance(MainWebsiteTemplateSynchronizer::class, $synchronizer);

        $this->postJson('/admin/api/site-mappings/bulk', [
            'root_domain' => 'demo.example.com',
            'content_mode' => 'blank',
        ])
            ->assertCreated()
            ->assertJsonPath('data.website_templates', null);
    }
}
