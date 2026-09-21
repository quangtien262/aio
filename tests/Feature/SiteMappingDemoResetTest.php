<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\Site;
use App\Models\ThemeDemoRecord;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SiteMappingDemoResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_demo_replaces_old_theme_samples_only_in_target_website(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');
        $site = Site::query()->create(['domain' => 'reset.demo.test', 'website_key' => 'reset-demo', 'theme_key' => 'SHOP601', 'status' => 'active']);
        $ids = [];
        foreach (['old-sample', 'manual', 'other-site'] as $slug) {
            $ids[$slug] = DB::table('cms_pages')->insertGetId([
                'website_key' => $slug === 'other-site' ? 'untouched-site' : $site->website_key,
                'title' => $slug, 'slug' => $slug, 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['old-sample', 'other-site'] as $slug) {
            ThemeDemoRecord::withoutGlobalScopes()->create([
                'website_key' => $slug === 'other-site' ? 'untouched-site' : $site->website_key,
                'theme_key' => 'OLD-THEME', 'preset_key' => 'electronics', 'model_type' => CmsPage::class, 'model_id' => $ids[$slug],
            ]);
        }
        $this->postJson("/admin/api/site-mappings/{$site->id}/demo-data", [
            'preset' => 'shop601-bean-style', 'reset_demo' => true,
        ])->assertOk()->assertJsonPath('data.site.checklist.demo_data_created', true);
        $this->assertDatabaseMissing('cms_pages', ['id' => $ids['old-sample']]);
        $this->assertDatabaseHas('cms_pages', ['id' => $ids['manual']]);
        $this->assertDatabaseHas('cms_pages', ['id' => $ids['other-site']]);
        $this->assertDatabaseHas('theme_demo_records', ['website_key' => 'untouched-site', 'theme_key' => 'OLD-THEME']);
        $this->assertDatabaseHas('catalog_products', ['website_key' => $site->website_key]);
        $this->assertSame('website-main', app(SiteContext::class)->websiteKey());
    }

    public function test_failed_generation_rolls_back_reset_and_restores_site_context(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');
        $site = Site::query()->create(['domain' => 'failed.demo.test', 'website_key' => 'failed-demo', 'theme_key' => 'SHOP601', 'status' => 'active']);
        $id = DB::table('cms_pages')->insertGetId(['website_key' => $site->website_key, 'title' => 'Old demo', 'slug' => 'old-demo', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $this->partialMock(ThemeDemoContentGenerator::class, function ($mock) use ($id, $site) {
            $mock->shouldReceive('presetsForTheme')->andReturn([['key' => 'shop601-bean-style']]);
            $mock->shouldReceive('generate')->once()->with('SHOP601', 'shop601-bean-style', true)->andReturnUsing(function () use ($id, $site) {
                $this->assertSame($site->website_key, app(SiteContext::class)->websiteKey());
                DB::table('cms_pages')->where('id', $id)->delete();
                throw new InvalidArgumentException('Generation failed');
            });
        });
        $this->postJson("/admin/api/site-mappings/{$site->id}/demo-data", ['preset' => 'shop601-bean-style', 'reset_demo' => true])->assertUnprocessable();
        $this->assertDatabaseHas('cms_pages', ['id' => $id]);
        $this->assertSame('website-main', app(SiteContext::class)->websiteKey());
    }

    public function test_guest_cannot_reset_demo_content(): void
    {
        $site = Site::query()->firstOrFail();
        $this->postJson("/admin/api/site-mappings/{$site->id}/demo-data", ['preset' => 'shop601-bean-style', 'reset_demo' => true])->assertUnauthorized();
    }
}
