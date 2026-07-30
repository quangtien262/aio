<?php

namespace Database\Seeders;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteProfile;
use App\Support\PermissionLabel;
use Database\Seeders\FeaturedCategorySeeder;
use Database\Seeders\HeroSideBannerSeeder;
use Database\Seeders\SidePromoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCoreAccess();
        $this->seedDefaultAdmin();
        $this->seedSiteProfile();
        $this->enableDefaultCmsModule();
        $this->call(HeroSideBannerSeeder::class);
        $this->call(FeaturedCategorySeeder::class);
        $this->call(SidePromoSeeder::class);
    }

    private function seedCoreAccess(): void
    {
        foreach (config('aio.core_permissions', []) as $permissionKey) {
            Permission::query()->updateOrCreate(
                ['key' => $permissionKey],
                [
                    'name' => PermissionLabel::make($permissionKey),
                    'module_key' => str($permissionKey)->before('.')->toString(),
                ],
            );
        }

        $role = Role::query()->updateOrCreate(
            ['key' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Toan quyen quan tri he thong.',
                'is_system' => true,
                'is_assignable' => false,
                'status' => 'active',
            ],
        );

        $role->permissions()->sync(Permission::query()->pluck('id')->all());
    }

    private function seedDefaultAdmin(): void
    {
        $configuredPassword = env('AIO_SYSTEM_OWNER_PASSWORD');
        $isTesting = app()->environment('testing');

        if (blank($configuredPassword) && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('AIO_SYSTEM_OWNER_PASSWORD must be configured before seeding a non-local environment.');
        }

        $admin = Admin::query()->firstOrCreate(
            ['id' => 1],
            [
                'name' => 'System Admin',
                'username' => 'admin',
                'email' => $isTesting ? 'admin@aio.local' : 'info@htvietnam.vn',
                'password' => Hash::make(
                    $configuredPassword ?: ($isTesting ? 'password' : 'abcd@1234'),
                ),
                'is_active' => true,
                'status' => 'active',
                'is_system_owner' => true,
                'must_change_password' => true,
                'locked_at' => null,
                'locked_reason' => null,
            ],
        );

        $admin->forceFill([
            'is_active' => true,
            'status' => 'active',
            'is_system_owner' => true,
            'locked_at' => null,
            'locked_reason' => null,
        ])->save();

        $roleId = Role::query()->where('key', 'super-admin')->value('id');

        if ($roleId !== null) {
            $admin->roleAssignments()->firstOrCreate([
                'role_id' => $roleId,
                'scope_type' => 'global',
                'scope_value' => null,
            ]);
        }
    }

    private function seedSiteProfile(): void
    {
        $siteProfile = SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            [
                'website_type' => 'ecommerce',
                'active_theme_key' => null,
                'is_setup_completed' => false,
                'completed_steps' => [],
                'branding' => ['website_key' => 'website-main'],
            ],
        );

        $branding = $siteProfile->branding ?? [];
        $storedLocations = data_get($branding, 'cms.menu_locations', []);
        $defaultLocations = config('cms.menu_locations', []);

        if (is_array($defaultLocations) && $defaultLocations !== []) {
            $mergedLocations = collect(is_array($storedLocations) ? $storedLocations : [])
                ->concat($defaultLocations)
                ->filter(fn (mixed $location): bool => is_array($location)
                    && filled($location['label'] ?? null)
                    && filled($location['value'] ?? null))
                ->unique('value')
                ->values()
                ->all();

            data_set($branding, 'cms.menu_locations', $mergedLocations);

            $siteProfile->forceFill(['branding' => $branding])->save();
        }
    }

    private function enableDefaultCmsModule(): void
    {
        /** @var ModuleManager $moduleManager */
        $moduleManager = app(ModuleManager::class);
        $status = ModuleInstallation::query()->where('key', 'cms')->value('status');

        if ($status === null || $status === 'available') {
            $moduleManager->install('cms');
            $status = ModuleInstallation::query()->where('key', 'cms')->value('status');
        }

        if (in_array($status, ['installed', 'disabled'], true)) {
            $moduleManager->enable('cms');
        }
    }
}
