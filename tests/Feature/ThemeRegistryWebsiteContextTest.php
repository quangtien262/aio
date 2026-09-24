<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeRegistryWebsiteContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_theme_follows_selected_website_instead_of_global_installation_flags(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@aio.local')->firstOrFail(), 'admin');
        foreach (['website-main' => 'BDS701', 'xd0320-demo' => 'XD0320'] as $website => $theme) {
            Site::updateOrCreate(['website_key' => $website], [
                'domain' => $website.'.test', 'theme_key' => $theme, 'status' => 'active',
            ]);
            SiteProfile::withoutGlobalScopes()->updateOrCreate(['website_key' => $website], [
                'site_name' => $website, 'website_type' => 'corporate', 'active_theme_key' => 'BDS701',
            ]);
            ThemeInstallation::updateOrCreate(['key' => $theme], [
                'name' => $theme, 'version' => '1.0.0', 'website_type' => 'corporate',
                'status' => 'active', 'is_active' => true,
            ]);
        }
        foreach ([['website-main', 'BDS701'], ['xd0320-demo', 'XD0320'], ['website-main', 'BDS701']] as [$website, $theme]) {
            $response = $this->withHeader('X-Website-Key', $website)->getJson('/admin/api/themes')
                ->assertOk()->assertJsonPath('meta.website_key', $website)->assertJsonPath('meta.active_theme_key', $theme);
            $this->assertSame([$theme], collect($response->json('data'))->where('is_active', true)->pluck('key')->values()->all());
        }
        Site::where('website_key', 'xd0320-demo')->update(['theme_key' => null]);
        $response = $this->withHeader('X-Website-Key', 'xd0320-demo')->getJson('/admin/api/themes')->assertOk();
        $this->assertSame(['BDS701'], collect($response->json('data'))->where('is_active', true)->pluck('key')->values()->all());
        SiteProfile::withoutGlobalScopes()->where('website_key', 'xd0320-demo')->update(['active_theme_key' => null]);
        $response = $this->getJson('/admin/api/themes')->assertOk();
        $this->assertCount(0, collect($response->json('data'))->where('is_active', true));
        $this->assertSame(2, ThemeInstallation::whereIn('key', ['BDS701', 'XD0320'])->where('is_active', true)->count());
    }
}
