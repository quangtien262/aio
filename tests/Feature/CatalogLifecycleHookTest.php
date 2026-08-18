<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\ModuleInstallation;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogLifecycleHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_updates_default_branding_when_another_website_is_current(): void
    {
        $this->seed(DatabaseSeeder::class);

        $modules = app(ModuleManager::class);
        $modules->install('catalog');

        SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', SiteContext::DEFAULT_WEBSITE_KEY)
            ->update(['site_name' => 'Renamed main website']);

        $secondarySite = Site::query()->create([
            'name' => 'Secondary website',
            'website_key' => 'website-secondary',
            'domain' => 'secondary.test',
            'theme_key' => 'EC912',
            'status' => 'active',
        ]);

        SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->create([
                'website_key' => 'website-secondary',
                'site_name' => 'Secondary website',
                'website_type' => 'ecommerce',
                'branding' => ['secondary_marker' => true],
            ]);

        app(SiteContext::class)->set($secondarySite);

        ModuleInstallation::query()
            ->where('key', 'catalog')
            ->update(['version' => '0.1.0']);

        $modules->upgrade('catalog');

        $profiles = SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->get()
            ->keyBy('website_key');

        $this->assertCount(2, $profiles);
        $this->assertSame(
            '0.2.1',
            data_get($profiles->get(SiteContext::DEFAULT_WEBSITE_KEY)?->globalBranding(), 'catalog.version'),
        );
        $this->assertTrue((bool) data_get(
            $profiles->get('website-secondary')?->globalBranding(),
            'secondary_marker',
        ));
        $this->assertNull(data_get(
            $profiles->get('website-secondary')?->globalBranding(),
            'catalog',
        ));
    }
}
