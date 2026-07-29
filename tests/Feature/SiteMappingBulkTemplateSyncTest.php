<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Site;
use App\Support\MainWebsiteTemplateSynchronizer;
use App\Support\SiteContentInitializer;
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

    public function test_bulk_domain_creation_keeps_demo_data_checklist_unchecked_when_sample_content_is_selected(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $initializer = Mockery::mock(SiteContentInitializer::class);
        $initializer->shouldReceive('initialize')
            ->atLeast()
            ->once()
            ->withArgs(fn (Site $site, string $mode): bool => $site->exists
                && $mode === SiteContentInitializer::MODE_SAMPLE)
            ->andReturn([
                'mode' => SiteContentInitializer::MODE_SAMPLE,
                'counts' => [],
            ]);
        $this->app->instance(SiteContentInitializer::class, $initializer);

        $synchronizer = Mockery::mock(MainWebsiteTemplateSynchronizer::class);
        $synchronizer->shouldReceive('supports')
            ->once()
            ->with('demo.example.com')
            ->andReturnFalse();
        $synchronizer->shouldNotReceive('syncThemes');
        $this->app->instance(MainWebsiteTemplateSynchronizer::class, $synchronizer);

        $response = $this->postJson('/admin/api/site-mappings/bulk', [
            'root_domain' => 'demo.example.com',
            'content_mode' => 'sample',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created.0.checklist.demo_data_created', false);

        $this->assertGreaterThan(0, Site::query()->count());
        Site::query()->each(function (Site $site): void {
            $this->assertFalse((bool) data_get($site->settings, 'checklist.demo_data_created'));
        });
    }
}
