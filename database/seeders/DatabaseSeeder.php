<?php

namespace Database\Seeders;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCoreAccess();
        $this->seedDefaultAdmin();
        $this->seedSiteProfile();
        $this->enableDefaultCmsModule();
    }

    private function seedCoreAccess(): void
    {
        foreach (config('aio.core_permissions', []) as $permissionKey) {
            Permission::query()->updateOrCreate(
                ['key' => $permissionKey],
                [
                    'name' => str($permissionKey)->replace('.', ' ')->title()->toString(),
                    'module_key' => str($permissionKey)->before('.')->toString(),
                ],
            );
        }

        $role = Role::query()->updateOrCreate(
            ['key' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Toan quyen quan tri he thong.',
            ],
        );

        $role->permissions()->sync(Permission::query()->pluck('id')->all());
    }

    private function seedDefaultAdmin(): void
    {
        $admin = Admin::query()->updateOrCreate(
            ['email' => 'admin@aio.local'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'locked_at' => null,
                'locked_reason' => null,
            ],
        );

        $roleId = Role::query()->where('key', 'super-admin')->value('id');

        if ($roleId !== null) {
            $admin->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    private function seedSiteProfile(): void
    {
        SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            [
                'website_type' => 'ecommerce',
                'active_theme_key' => null,
                'is_setup_completed' => false,
                'completed_steps' => [],
                'branding' => ['website_key' => 'website-main'],
            ],
        );
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
