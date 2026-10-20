<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleRegistry;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleNavigationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $modulePath = base_path('modules/MenuPermissionsFixture');
        $manifestPath = $modulePath.DIRECTORY_SEPARATOR.'module.json';
        $manifest = [
            'key' => 'menu-permissions-fixture',
            'name' => 'Customer menu fixture',
            'version' => '1.0.0',
            'menus' => [
                ['key' => 'public', 'label' => 'Public'],
                ['key' => 'reader', 'label' => 'Reader', 'permission' => 'customers.view'],
                ['key' => 'data', 'label' => 'Nhập & xuất', 'route' => '/admin/modules/customers/data', 'permissions_any' => ['customers.import', 'customers.export', 'customers.import', '', null]],
                ['key' => 'data-with-reader', 'label' => 'Read and transfer', 'permission' => 'customers.view', 'permissions_any' => ['customers.import', 'customers.export']],
                ['key' => 'empty', 'label' => 'Empty requirement', 'permissions_any' => []],
                ['key' => 'invalid', 'label' => 'Invalid requirement', 'permissions_any' => 'customers.export'],
            ],
        ];

        // Exercise the real manifest normalization and both HTTP consumers
        // without changing any application manifest or menu permissions.
        File::partialMock()->shouldReceive('directories')->with(base_path('modules'))->andReturn([$modulePath]);
        File::shouldReceive('exists')->with($manifestPath)->andReturn(true);
        File::shouldReceive('get')->with($manifestPath)->andReturn(json_encode($manifest, JSON_THROW_ON_ERROR));

        ModuleInstallation::query()->create([
            'key' => $manifest['key'], 'name' => $manifest['name'],
            'version' => $manifest['version'], 'status' => 'enabled',
        ]);
    }

    public function test_import_or_export_permission_exposes_data_menu_in_navigation_and_dashboard(): void
    {
        $cases = [
            [['customers.export'], ['public', 'data']],
            [['customers.import'], ['public', 'data']],
            [['customers.view'], ['public', 'reader']],
            [['customers.view', 'customers.export'], ['public', 'reader', 'data', 'data-with-reader']],
            [[], ['public']],
        ];

        foreach ($cases as [$permissions, $expectedKeys]) {
            $this->actingAs($this->staffWithPermissions(['platform.dashboard.view', ...$permissions]), 'admin');
            $navigation = $this->getJson('/admin/api/me')->assertOk()->json('data.module_navigation');
            $dashboardMenus = $this->getJson('/admin/api/dashboard')->assertOk()->json('active_modules.0.menus');

            $this->assertSame($expectedKeys, array_column($navigation, 'key'));
            $this->assertSame($expectedKeys, array_column($dashboardMenus, 'key'));

            if (in_array('data', $expectedKeys, true)) {
                $this->assertSame(
                    ['customers.import', 'customers.export'],
                    collect($navigation)->firstWhere('key', 'data')['permissions_any'],
                );
            }
        }
    }

    public function test_legacy_metadata_is_preserved_and_disabled_modules_stay_hidden(): void
    {
        $registry = app(ModuleRegistry::class);
        $menus = collect($registry->find('menu-permissions-fixture')['menus'])->keyBy('key');
        $this->assertArrayNotHasKey('permissions_any', $menus['public']);
        $this->assertArrayNotHasKey('permissions_any', $menus['reader']);
        $this->assertSame([], $menus['empty']['permissions_any']);
        $this->assertSame([], $menus['invalid']['permissions_any']);
        $this->assertFalse($registry->menuMatchesPermissions($menus['empty'], ['customers.export']));
        $this->assertFalse($registry->menuMatchesPermissions($menus['invalid'], ['customers.export']));

        ModuleInstallation::query()->where('key', 'menu-permissions-fixture')->update(['status' => 'disabled']);
        $this->assertSame([], $registry->navigationForPermissions(['customers.view', 'customers.import', 'customers.export']));
        $this->actingAs($this->staffWithPermissions(['platform.dashboard.view', 'customers.export']), 'admin');
        $this->getJson('/admin/api/dashboard')->assertOk()->assertJsonPath('active_modules', []);
    }

    public function test_visible_menus_do_not_grant_destination_api_permissions(): void
    {
        $this->actingAs($this->staffWithPermissions(['customers.export']), 'admin');
        $navigation = $this->getJson('/admin/api/me')->assertOk()->json('data.module_navigation');
        $this->assertSame(['public', 'data'], array_column($navigation, 'key'));
        $this->getJson('/admin/api/dashboard')->assertForbidden();
    }

    private function staffWithPermissions(array $permissions): Admin
    {
        $admin = Admin::factory()->create();
        $role = Role::query()->create(['key' => 'menu-staff-'.$admin->id, 'name' => 'Menu staff', 'status' => 'active']);
        $ids = [];
        foreach ($permissions as $key) {
            $ids[] = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $key, 'module_key' => 'navigation-test', 'is_active' => true,
            ])->id;
        }
        $role->permissions()->sync($ids);
        $admin->roleAssignments()->create(['role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);

        return $admin->fresh();
    }
}
