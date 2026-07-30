<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Support\SiteContentInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteMappingCopyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_copy_content_between_site_mappings(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $source = Site::query()->create([
            'domain' => 'source.demo.test',
            'website_key' => 'copy-source',
            'theme_key' => 'FOOT403',
            'status' => 'active',
        ]);
        $target = Site::query()->create([
            'domain' => 'target.demo.test',
            'website_key' => 'copy-target',
            'theme_key' => 'FOOT403',
            'status' => 'active',
        ]);

        $categoryId = DB::table('catalog_categories')->insertGetId([
            'name' => 'API Category',
            'slug' => 'api-category',
            'website_key' => $source->website_key,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('catalog_products')->insert([
            'catalog_category_id' => $categoryId,
            'name' => 'API Product',
            'slug' => 'api-product',
            'sku' => 'API-COPY-001',
            'price' => 100000,
            'stock' => 5,
            'website_key' => $source->website_key,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/admin/api/site-mappings/{$source->id}/copy-content", [
            'target_site_id' => $target->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.source.id', $source->id)
            ->assertJsonPath('data.target.id', $target->id)
            ->assertJsonPath('data.counts.products', 1)
            ->assertJsonPath('data.counts.product_categories', 1);

        $this->assertDatabaseHas('catalog_products', [
            'website_key' => $target->website_key,
            'sku' => 'API-COPY-001',
            'name' => 'API Product',
        ]);
    }

    public function test_authorized_admin_can_generate_selected_demo_preset_for_one_domain(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $site = Site::query()->create([
            'domain' => 'electronics.demo.test',
            'website_key' => 'electronics-demo',
            'theme_key' => 'SHOP601',
            'name' => 'Electronics Demo',
            'status' => 'active',
        ]);

        $this->postJson("/admin/api/site-mappings/{$site->id}/demo-data", [
            'preset' => 'shop601-bean-style',
        ])
            ->assertOk()
            ->assertJsonPath('data.site.website_key', 'electronics-demo')
            ->assertJsonPath('data.site.checklist.demo_data_created', true)
            ->assertJsonPath('data.initialization.mode', 'sample')
            ->assertJsonPath('data.initialization.preset', 'shop601-bean-style');

        $this->assertDatabaseHas('site_profiles', [
            'website_key' => 'electronics-demo',
            'active_theme_key' => 'SHOP601',
        ]);
        $this->assertDatabaseHas('catalog_products', [
            'website_key' => 'electronics-demo',
        ]);
        $this->assertDatabaseHas('landing_pages', [
            'website_key' => 'electronics-demo',
            'theme_key' => 'SHOP601',
            'is_home' => true,
        ]);
    }

    public function test_domain_demo_endpoint_rejects_a_preset_not_supported_by_its_theme(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $site = Site::query()->create([
            'domain' => 'nt502.demo.test',
            'website_key' => 'nt502-demo',
            'theme_key' => 'NT502',
            'status' => 'active',
        ]);

        $this->postJson("/admin/api/site-mappings/{$site->id}/demo-data", [
            'preset' => 'electronics-superstore',
        ])->assertUnprocessable()->assertJsonValidationErrors('preset');
    }

    public function test_default_domain_uses_active_theme_from_site_profile_for_demo_data(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $site = Site::query()->where('website_key', 'website-main')->firstOrFail();
        $site->forceFill(['theme_key' => null, 'status' => 'active'])->save();
        SiteProfile::query()->withoutGlobalScope('current_website')->updateOrCreate(
            ['website_key' => 'website-main'],
            ['site_name' => 'Default website', 'active_theme_key' => 'NT502'],
        );

        $this->getJson('/admin/api/site-mappings')
            ->assertOk()
            ->assertJsonPath('data.0.theme_key', 'NT502')
            ->assertJsonPath('meta.demo_presets_by_theme.NT502.0.key', 'nt502-dola-furniture');

        $this->postJson("/admin/api/site-mappings/{$site->id}/demo-data", [
            'preset' => 'nt502-dola-furniture',
        ])
            ->assertOk()
            ->assertJsonPath('data.site.theme_key', 'NT502');
    }

    public function test_creating_a_domain_with_sample_content_reuses_existing_fallback_slugs(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        DB::table('cms_categories')->insert([
            'website_key' => 'dn302-demo',
            'name' => 'Tin tức đã có',
            'slug' => 'tin-tuc',
            'description' => 'Dữ liệu còn lại từ lần tạo trước.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/admin/api/site-mappings', [
            'domain' => 'dn302.demo.test',
            'website_key' => 'dn302-demo',
            'theme_key' => 'DN302',
            'name' => 'DN302 Demo',
            'status' => 'active',
            'content_mode' => 'sample',
        ])
            ->assertCreated()
            ->assertJsonPath('data.website_key', 'dn302-demo')
            ->assertJsonPath('data.checklist.demo_data_created', true)
            ->assertJsonPath('meta.initialization.mode', 'sample');

        $this->assertSame(1, DB::table('cms_categories')
            ->where('website_key', 'dn302-demo')
            ->where('slug', 'tin-tuc')
            ->count());
        $this->assertDatabaseHas('cms_posts', ['website_key' => 'dn302-demo']);
        $this->assertDatabaseHas('landing_pages', [
            'website_key' => 'dn302-demo',
            'theme_key' => 'DN302',
            'is_home' => true,
        ]);

        $site = Site::query()->where('website_key', 'dn302-demo')->firstOrFail();
        app(SiteContentInitializer::class)->initialize($site, SiteContentInitializer::MODE_SAMPLE);

        $this->assertSame(3, DB::table('cms_posts')->where('website_key', 'dn302-demo')->count());
        $this->assertSame(3, DB::table('cms_services')->where('website_key', 'dn302-demo')->count());
        $this->assertSame(2, DB::table('cms_projects')->where('website_key', 'dn302-demo')->count());
        $this->assertSame(3, DB::table('cms_team_members')->where('website_key', 'dn302-demo')->count());
        $this->assertSame(2, DB::table('cms_testimonials')->where('website_key', 'dn302-demo')->count());
    }

    public function test_admin_can_update_each_domain_checklist_inline(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');

        $site = Site::query()->create([
            'domain' => 'checklist.demo.test',
            'website_key' => 'checklist-demo',
            'theme_key' => 'DN302',
            'status' => 'active',
            'settings' => [],
        ]);

        $this->patchJson("/admin/api/site-mappings/{$site->id}/checklist", [
            'tested' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.checklist.tested', true)
            ->assertJsonPath('data.checklist.demo_data_created', false);

        $this->patchJson("/admin/api/site-mappings/{$site->id}/checklist", [
            'demo_data_created' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.checklist.tested', true)
            ->assertJsonPath('data.checklist.demo_data_created', true);

        $this->assertTrue((bool) data_get($site->fresh()->settings, 'checklist.tested'));
        $this->assertTrue((bool) data_get($site->fresh()->settings, 'checklist.demo_data_created'));
    }
}
