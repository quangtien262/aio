<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFoundationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_foundation_dashboard_endpoints(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'metrics' => ['admins', 'customers', 'roles', 'permissions', 'modules', 'themes'],
                'setup' => ['website_type', 'active_theme_key', 'is_setup_completed', 'completed_steps'],
            ]);

        $this->getJson('/admin/api/modules')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['key', 'name', 'version', 'description', 'website_types', 'dependencies', 'permissions', 'status', 'is_installed', 'is_enabled'],
                ],
            ]);

        $this->getJson('/admin/api/themes')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['key', 'name', 'version', 'description', 'website_type', 'blocks', 'status', 'is_installed', 'is_active'],
                ],
            ]);

        $this->getJson('/admin/api/setup')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['site_name', 'website_type', 'active_theme_key', 'is_setup_completed', 'steps'],
            ]);
    }

    public function test_admin_can_manage_module_theme_and_setup_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $this->postJson('/admin/api/modules/cms/enable')
            ->assertOk();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'cms',
            'status' => 'enabled',
        ]);

        $this->postJson('/admin/api/themes/corporate-starter/activate')
            ->assertOk();

        $this->assertDatabaseHas('theme_installations', [
            'key' => 'corporate-starter',
            'is_active' => true,
        ]);

        $this->putJson('/admin/api/setup', [
            'site_name' => 'AIO Demo',
            'website_type' => 'corporate',
        ])->assertOk();

        $this->postJson('/admin/api/setup/steps/branding')
            ->assertOk();

        $this->postJson('/admin/api/setup/steps/finish')
            ->assertOk();

        $siteProfile = SiteProfile::query()->firstOrFail();

        $this->assertSame('AIO Demo', $siteProfile->site_name);
        $this->assertSame('corporate', $siteProfile->website_type);
        $this->assertSame('corporate-starter', $siteProfile->active_theme_key);
        $this->assertTrue($siteProfile->is_setup_completed);
        $this->assertContains('branding', $siteProfile->completed_steps);
        $this->assertContains('finish', $siteProfile->completed_steps);

        $this->assertSame('enabled', ModuleInstallation::query()->where('key', 'cms')->value('status'));
        $this->assertTrue((bool) ThemeInstallation::query()->where('key', 'corporate-starter')->value('is_active'));
    }
}
