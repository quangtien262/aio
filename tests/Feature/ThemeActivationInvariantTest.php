<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeActivationInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_website_rejects_a_theme_for_another_website_type(): void
    {
        $this->authenticateSystemOwner();
        $this->createWebsite('website-main', 'ecommerce', true);

        $this->postJson('/admin/api/themes/corporate-starter/activate')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme');

        $this->assertDatabaseHas('site_profiles', [
            'website_key' => 'website-main',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
        ]);
    }

    public function test_activation_is_scoped_per_website_and_refreshes_manifest_metadata(): void
    {
        $this->authenticateSystemOwner();
        $this->createWebsite('website-main', 'ecommerce');
        $this->createWebsite('website-b', 'corporate');
        ThemeInstallation::query()->create([
            'key' => 'corporate-starter',
            'name' => 'Stale name',
            'version' => '0.0.1',
            'website_type' => 'legacy',
            'status' => 'installed',
            'is_active' => false,
            'blocks' => [],
        ]);

        $this->postJson('/admin/api/themes/SHOP601/activate')->assertOk();
        $this->withHeader('X-Website-Key', 'website-b')
            ->postJson('/admin/api/themes/corporate-starter/activate')
            ->assertOk();

        $this->assertDatabaseHas('site_profiles', [
            'website_key' => 'website-main',
            'active_theme_key' => 'SHOP601',
        ]);
        $this->assertDatabaseHas('site_profiles', [
            'website_key' => 'website-b',
            'active_theme_key' => 'corporate-starter',
        ]);
        $this->assertDatabaseHas('sites', [
            'website_key' => 'website-b',
            'theme_key' => 'corporate-starter',
        ]);
        $this->assertDatabaseHas('theme_installations', [
            'key' => 'SHOP601',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('theme_installations', [
            'key' => 'corporate-starter',
            'name' => 'Corporate Starter',
            'version' => '0.1.0',
            'website_type' => 'corporate',
            'is_active' => true,
        ]);
    }

    public function test_theme_with_module_dependency_is_blocked_until_module_is_enabled(): void
    {
        $this->authenticateSystemOwner();
        $this->createWebsite('website-main', 'real_estate');

        $this->postJson('/admin/api/themes/BDS701/activate')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme');

        $this->assertDatabaseMissing('theme_installations', [
            'key' => 'BDS701',
            'status' => 'active',
        ]);
    }

    private function authenticateSystemOwner(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]);
        $this->actingAs($admin, 'admin');
    }

    private function createWebsite(string $websiteKey, string $websiteType, bool $completed = false): void
    {
        Site::query()->updateOrCreate(
            ['website_key' => $websiteKey],
            [
                'name' => $websiteKey,
                'domain' => $websiteKey.'.test',
                'theme_key' => 'SHOP601',
                'status' => 'active',
            ],
        );
        SiteProfile::query()->withoutGlobalScopes()->updateOrCreate(
            ['website_key' => $websiteKey],
            [
                'site_name' => $websiteKey,
                'website_type' => $websiteType,
                'active_theme_key' => 'SHOP601',
                'is_setup_completed' => $completed,
            ],
        );
    }
}
