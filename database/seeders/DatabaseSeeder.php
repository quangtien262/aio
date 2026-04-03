<?php

namespace Database\Seeders;

use App\Core\Modules\ModuleRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = Admin::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@aio.local',
        ]);

        Customer::factory()->create([
            'name' => 'Customer Demo',
            'email' => 'customer@aio.local',
        ]);

        $moduleRegistry = app(ModuleRegistry::class);
        $themeRegistry = app(ThemeRegistry::class);

        $permissionKeys = collect(config('aio.core_permissions', []))
            ->merge($moduleRegistry->permissions())
            ->unique()
            ->values();

        $permissions = $permissionKeys->map(function (string $permissionKey): Permission {
            return Permission::query()->firstOrCreate(
                ['key' => $permissionKey],
                [
                    'name' => str($permissionKey)->replace('.', ' ')->title()->toString(),
                    'module_key' => Str::contains($permissionKey, '.') ? Arr::first(explode('.', $permissionKey)) : null,
                ],
            );
        });

        $superAdminRole = Role::query()->firstOrCreate(
            ['key' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Toan quyen quan tri nen tang AIO'],
        );

        $superAdminRole->permissions()->sync($permissions->pluck('id')->all());
        $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $moduleRegistry->all()->each(function (array $module): void {
            ModuleInstallation::query()->firstOrCreate(
                ['key' => $module['key']],
                [
                    'name' => $module['name'],
                    'version' => $module['version'],
                    'status' => $module['key'] === 'cms' ? 'enabled' : 'installed',
                    'website_types' => $module['website_types'],
                    'dependencies' => $module['dependencies'],
                    'installed_at' => now(),
                    'enabled_at' => $module['key'] === 'cms' ? now() : null,
                ],
            );
        });

        $themeRegistry->all()->each(function (array $theme): void {
            ThemeInstallation::query()->firstOrCreate(
                ['key' => $theme['key']],
                [
                    'name' => $theme['name'],
                    'version' => $theme['version'],
                    'website_type' => $theme['website_type'],
                    'status' => 'installed',
                    'is_active' => $theme['key'] === 'corporate-starter',
                    'blocks' => $theme['blocks'],
                    'installed_at' => now(),
                    'activated_at' => $theme['key'] === 'corporate-starter' ? now() : null,
                ],
            );
        });

        SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            [
                'website_type' => 'corporate',
                'active_theme_key' => 'corporate-starter',
                'is_setup_completed' => false,
                'completed_steps' => ['website_type', 'theme', 'branding'],
                'branding' => [
                    'company_name' => 'HT Viet Nam',
                    'primary_color' => '#0f766e',
                ],
            ],
        );
    }
}
