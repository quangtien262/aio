<?php

namespace Tests\Feature;

use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogPermissionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_legacy_catalog_installation_recovers_permissions_and_full_access_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        ModuleInstallation::query()->updateOrCreate(
            ['key' => 'catalog'],
            [
                'name' => 'Catalog',
                'version' => '0.2.0',
                'status' => 'enabled',
                'website_types' => ['ecommerce'],
                'dependencies' => ['cms'],
            ],
        );

        $permissionKeys = ['catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete'];
        $stalePermissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $stalePermissionIds)->delete();
        Permission::query()->whereIn('key', $permissionKeys)->delete();

        $migration = require database_path('migrations/2026_08_14_000003_sync_catalog_module_permissions.php');
        $migration->up();

        $permissions = Permission::query()->whereIn('key', $permissionKeys)->get();
        $this->assertCount(4, $permissions);
        $this->assertTrue($permissions->every(
            fn (Permission $permission): bool => $permission->module_key === 'catalog' && $permission->is_active,
        ));

        foreach (Role::query()->whereIn('key', ['super-admin', 'platform-owner'])->get() as $role) {
            $this->assertEqualsCanonicalizing(
                $permissionKeys,
                $role->permissions()->whereIn('key', $permissionKeys)->pluck('key')->all(),
            );
        }
    }
}
