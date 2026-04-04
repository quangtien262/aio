<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\Admin;
use App\Models\AdminRoleScope;
use App\Models\CmsPage;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
                'active_modules' => [
                    ['key', 'name', 'description', 'status', 'icon', 'color', 'route', 'website_types', 'installed_version', 'latest_version', 'menus'],
                ],
            ]);

        $this->getJson('/admin/api/modules')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['key', 'name', 'version', 'latest_version', 'installed_version', 'description', 'website_types', 'dependencies', 'permissions', 'menus', 'changelog', 'lifecycle', 'status', 'is_installed', 'is_enabled', 'dependents', 'blockers', 'available_actions'],
                ],
            ]);

        $this->assertTrue(Schema::hasTable('cms_pages'));
        $this->assertTrue(File::exists(config_path('cms.php')));
        $this->assertTrue(File::exists(public_path('modules/cms/cms-module.json')));
        $this->assertTrue((bool) data_get(SiteProfile::query()->first(), 'branding.cms.hooks.installed'));

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

        $this->postJson('/admin/api/modules/catalog/install')
            ->assertOk();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'catalog',
            'status' => 'installed',
        ]);

        $this->assertDatabaseHas('permissions', [
            'key' => 'catalog.view',
            'module_key' => 'catalog',
        ]);

        $this->assertTrue(Schema::hasTable('catalog_products'));
        $this->assertTrue(File::exists(config_path('catalog.php')));
        $this->assertTrue(File::exists(public_path('modules/catalog/catalog-module.json')));
        $this->assertSame('VND', data_get(SiteProfile::query()->first(), 'branding.catalog.currency'));

        $this->postJson('/admin/api/modules/catalog/enable')
            ->assertOk();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'catalog',
            'status' => 'enabled',
        ]);
        $this->assertTrue((bool) data_get(SiteProfile::query()->first(), 'branding.catalog.enabled'));

        $this->postJson('/admin/api/modules/cms/disable')
            ->assertStatus(422);

        $this->postJson('/admin/api/modules/catalog/disable')
            ->assertOk();
        $this->assertFalse((bool) data_get(SiteProfile::query()->first(), 'branding.catalog.enabled'));

        ModuleInstallation::query()->where('key', 'catalog')->update([
            'version' => '0.1.0',
        ]);

        $this->postJson('/admin/api/modules/catalog/upgrade')
            ->assertOk();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'catalog',
            'version' => '0.2.0',
        ]);
        $this->assertSame('0.2.0', data_get(SiteProfile::query()->first(), 'branding.catalog.version'));

        $this->deleteJson('/admin/api/modules/catalog')
            ->assertOk();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'catalog',
            'status' => 'available',
        ]);

        $this->assertDatabaseMissing('permissions', [
            'key' => 'catalog.view',
        ]);
        $this->assertFalse(Schema::hasTable('catalog_products'));
        $this->assertFalse(File::exists(config_path('catalog.php')));
        $this->assertFalse(File::exists(public_path('modules/catalog/catalog-module.json')));
        $this->assertNull(data_get(SiteProfile::query()->first(), 'branding.catalog'));

        $this->postJson('/admin/api/modules/cms/disable')
            ->assertOk();
        $this->assertFalse((bool) data_get(SiteProfile::query()->first(), 'branding.cms.hooks.enabled'));

        $this->postJson('/admin/api/modules/cms/enable')
            ->assertOk();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'cms',
            'status' => 'enabled',
        ]);
        $this->assertTrue((bool) data_get(SiteProfile::query()->first(), 'branding.cms.hooks.enabled'));

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
        $this->assertNull(Permission::query()->where('key', 'catalog.view')->first());
        $this->assertTrue((bool) ThemeInstallation::query()->where('key', 'corporate-starter')->value('is_active'));
    }

    public function test_admin_can_manage_roles_permissions_and_admin_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();
        $operatorAdmin = Admin::factory()->create([
            'name' => 'RBAC Operator',
            'email' => 'operator@aio.local',
        ]);

        $this->actingAs($admin, 'admin');

        $accessPayload = $this->getJson('/admin/api/access')
            ->assertOk()
            ->json('data');

        $permissionIds = collect($accessPayload['permissions'])
            ->whereIn('key', ['store.module.view', 'theme.view'])
            ->pluck('id')
            ->values()
            ->all();

        $this->postJson('/admin/api/roles', [
            'name' => 'Content Operator',
            'key' => 'content-operator',
            'description' => 'Role van hanh noi dung va module.',
            'permission_ids' => $permissionIds,
        ])->assertCreated();

        $role = Role::query()->where('key', 'content-operator')->firstOrFail();

        $this->assertCount(2, $role->permissions);

        $this->putJson("/admin/api/admins/{$operatorAdmin->id}/roles", [
            'role_ids' => [$role->id],
        ])->assertOk();

        $operatorAdmin->refresh();
        $this->assertSame([$role->id], $operatorAdmin->roles()->pluck('roles.id')->all());

        $updatedPermissionIds = collect($accessPayload['permissions'])
            ->whereIn('key', ['store.module.view', 'theme.view', 'setup.view'])
            ->pluck('id')
            ->values()
            ->all();

        $this->putJson("/admin/api/roles/{$role->id}", [
            'name' => 'Content Operator Updated',
            'key' => 'content-operator',
            'description' => 'Cap nhat quyen.',
            'permission_ids' => $updatedPermissionIds,
        ])->assertOk();

        $this->assertSame(3, $role->fresh()->permissions()->count());

        $this->deleteJson("/admin/api/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_admin_can_manage_admin_accounts_and_scopes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $accessPayload = $this->getJson('/admin/api/access')
            ->assertOk()
            ->json('data');

        $permissionIds = collect($accessPayload['permissions'])
            ->whereIn('key', ['theme.view', 'setup.view'])
            ->pluck('id')
            ->values()
            ->all();

        $this->postJson('/admin/api/roles', [
            'name' => 'Scoped Viewer',
            'key' => 'scoped-viewer',
            'description' => 'Viewer theo scope du lieu.',
            'permission_ids' => $permissionIds,
        ])->assertCreated();

        $role = Role::query()->where('key', 'scoped-viewer')->firstOrFail();

        $this->postJson('/admin/api/admins', [
            'name' => 'Scope Admin',
            'email' => 'scope-admin@aio.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'role_ids' => [$role->id],
            'scopes' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'module',
                    'scope_value' => 'cms',
                ],
                [
                    'role_id' => $role->id,
                    'scope_type' => 'tenant',
                    'scope_value' => 'tenant-a',
                ],
            ],
        ])->assertCreated();

        $scopedAdmin = Admin::query()->where('email', 'scope-admin@aio.local')->firstOrFail();

        $this->assertDatabaseHas('admins', [
            'email' => 'scope-admin@aio.local',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('admin_role', [
            'admin_id' => $scopedAdmin->id,
            'role_id' => $role->id,
        ]);

        $this->assertSame(2, AdminRoleScope::query()->where('admin_id', $scopedAdmin->id)->count());

        $this->getJson('/admin/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@aio.local')
            ->assertJsonPath('data.module_navigation.0.key', 'cms-pages')
            ->assertJsonPath('data.module_navigation.0.route', '/admin/cms/pages');

        $this->get('/admin/cms')
            ->assertOk()
            ->assertSee('admin-root', false);

        $this->getJson('/admin/api/admins')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'admins',
                    'roles',
                    'scope_types',
                ],
            ]);

        $this->putJson("/admin/api/admins/{$scopedAdmin->id}", [
            'name' => 'Scope Admin Updated',
            'email' => 'scope-admin@aio.local',
            'is_active' => true,
            'role_ids' => [$role->id],
            'scopes' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'website',
                    'scope_value' => 'corporate-main',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('admin_role_scopes', [
            'admin_id' => $scopedAdmin->id,
            'scope_type' => 'website',
            'scope_value' => 'corporate-main',
        ]);

        $this->putJson("/admin/api/admins/{$scopedAdmin->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->postJson("/admin/api/admins/{$scopedAdmin->id}/lock", [
            'reason' => 'Tam khoa de kiem tra.',
        ])->assertOk();

        $this->assertDatabaseHas('admins', [
            'id' => $scopedAdmin->id,
            'is_active' => false,
        ]);

        $this->postJson("/admin/api/admins/{$scopedAdmin->id}/unlock")
            ->assertOk();

        $this->assertDatabaseHas('admins', [
            'id' => $scopedAdmin->id,
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('new-password123', $scopedAdmin->fresh()->password));
    }

    public function test_admin_account_validation_rejects_scopes_for_unassigned_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $accessPayload = $this->getJson('/admin/api/access')
            ->assertOk()
            ->json('data');

        $permissionIds = collect($accessPayload['permissions'])
            ->whereIn('key', ['theme.view', 'setup.view'])
            ->pluck('id')
            ->values()
            ->all();

        $this->postJson('/admin/api/roles', [
            'name' => 'Scoped Validation Role',
            'key' => 'scoped-validation-role',
            'description' => 'Role de test validation admin scope.',
            'permission_ids' => $permissionIds,
        ])->assertCreated();

        $role = Role::query()->where('key', 'scoped-validation-role')->firstOrFail();

        $this->postJson('/admin/api/admins', [
            'name' => 'Broken Scope Admin',
            'email' => 'broken-scope-admin@aio.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'role_ids' => [],
            'scopes' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'module',
                    'scope_value' => 'cms',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scopes']);

        $this->assertDatabaseMissing('admins', [
            'email' => 'broken-scope-admin@aio.local',
        ]);
    }

    public function test_admin_account_validation_rejects_invalid_password_reset_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();
        $targetAdmin = Admin::factory()->create([
            'email' => 'reset-target@aio.local',
        ]);

        $this->actingAs($admin, 'admin');

        $this->putJson("/admin/api/admins/{$targetAdmin->id}/password", [
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertFalse(Hash::check('short', $targetAdmin->fresh()->password));
    }

    public function test_admin_cannot_lock_the_current_authenticated_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $this->postJson("/admin/api/admins/{$admin->id}/lock", [
            'reason' => 'Should fail for self lock.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không thể khóa tài khoản admin đang sử dụng.');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'is_active' => true,
            'locked_reason' => null,
        ]);
    }

    public function test_cms_and_catalog_queries_are_filtered_by_tenant_owner_and_website_scopes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();
        $this->actingAs($admin, 'admin');

        $this->postJson('/admin/api/modules/catalog/install')->assertOk();
        $this->postJson('/admin/api/modules/catalog/enable')->assertOk();

        CmsPage::query()->create([
            'title' => 'Scoped CMS Page',
            'slug' => 'scoped-cms-page',
            'status' => 'published',
            'body' => 'Visible page',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ]);

        CmsPage::query()->create([
            'title' => 'Hidden CMS Page',
            'slug' => 'hidden-cms-page',
            'status' => 'draft',
            'body' => 'Hidden page',
            'website_key' => 'website-other',
            'owner_key' => 'owner-other',
            'tenant_key' => 'tenant-b',
        ]);

        CatalogProduct::query()->create([
            'name' => 'Scoped Product',
            'sku' => 'SCOPED-001',
            'price' => 120000,
            'stock' => 15,
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ]);

        CatalogProduct::query()->create([
            'name' => 'Hidden Product',
            'sku' => 'HIDDEN-001',
            'price' => 150000,
            'stock' => 5,
            'website_key' => 'website-other',
            'owner_key' => 'owner-other',
            'tenant_key' => 'tenant-b',
        ]);

        $accessPayload = $this->getJson('/admin/api/access')
            ->assertOk()
            ->json('data');

        $permissionIds = collect($accessPayload['permissions'])
            ->whereIn('key', ['cms.view', 'cms.create', 'cms.update', 'cms.delete', 'catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete'])
            ->pluck('id')
            ->values()
            ->all();

        $this->postJson('/admin/api/roles', [
            'name' => 'Scoped Module Reader',
            'key' => 'scoped-module-reader',
            'description' => 'Doc du lieu CMS/Catalog theo data scope.',
            'permission_ids' => $permissionIds,
        ])->assertCreated();

        $role = Role::query()->where('key', 'scoped-module-reader')->firstOrFail();

        $this->postJson('/admin/api/admins', [
            'name' => 'Scoped Data Admin',
            'email' => 'scoped-data-admin@aio.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'role_ids' => [$role->id],
            'scopes' => [
                ['role_id' => $role->id, 'scope_type' => 'tenant', 'scope_value' => 'tenant-a'],
                ['role_id' => $role->id, 'scope_type' => 'owner', 'scope_value' => 'owner-system'],
                ['role_id' => $role->id, 'scope_type' => 'website', 'scope_value' => 'website-main'],
            ],
        ])->assertCreated();

        $scopedAdmin = Admin::query()->where('email', 'scoped-data-admin@aio.local')->firstOrFail();
        $this->actingAs($scopedAdmin, 'admin');

        $this->getJson('/admin/api/cms/pages')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonMissing(['slug' => 'hidden-cms-page']);

        $createdCmsPageId = $this->postJson('/admin/api/cms/pages', [
            'title' => 'Scoped CMS Draft',
            'slug' => 'scoped-cms-draft',
            'status' => 'draft',
            'body' => 'Scoped create',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/admin/api/cms/pages', [
            'title' => 'Out of Scope CMS Draft',
            'slug' => 'out-of-scope-cms-draft',
            'status' => 'draft',
            'body' => 'Hidden create',
            'website_key' => 'website-other',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])->assertStatus(422);

        $this->putJson("/admin/api/cms/pages/{$createdCmsPageId}", [
            'title' => 'Scoped CMS Draft Updated',
            'slug' => 'scoped-cms-draft-updated',
            'status' => 'published',
            'body' => 'Scoped update',
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])->assertOk();

        $hiddenCmsPageId = CmsPage::query()->where('slug', 'hidden-cms-page')->value('id');

        $this->putJson("/admin/api/cms/pages/{$hiddenCmsPageId}", [
            'title' => 'Hidden CMS Updated',
            'slug' => 'hidden-cms-updated',
            'status' => 'draft',
            'body' => 'Should fail',
            'website_key' => 'website-other',
            'owner_key' => 'owner-other',
            'tenant_key' => 'tenant-b',
        ])->assertNotFound();

        $this->getJson('/admin/api/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonMissing(['sku' => 'HIDDEN-001']);

        $createdProductId = $this->postJson('/admin/api/catalog/products', [
            'name' => 'Scoped Product New',
            'sku' => 'SCOPED-NEW-001',
            'price' => 99000,
            'stock' => 9,
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/admin/api/catalog/products', [
            'name' => 'Out of Scope Product',
            'sku' => 'OUT-SCOPE-001',
            'price' => 110000,
            'stock' => 2,
            'website_key' => 'website-main',
            'owner_key' => 'owner-other',
            'tenant_key' => 'tenant-a',
        ])->assertStatus(422);

        $this->putJson("/admin/api/catalog/products/{$createdProductId}", [
            'name' => 'Scoped Product Updated',
            'sku' => 'SCOPED-NEW-001',
            'price' => 129000,
            'stock' => 11,
            'website_key' => 'website-main',
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ])->assertOk();

        $hiddenProductId = CatalogProduct::query()->where('sku', 'HIDDEN-001')->value('id');

        $this->deleteJson("/admin/api/catalog/products/{$hiddenProductId}")
            ->assertNotFound();

        $this->deleteJson("/admin/api/cms/pages/{$createdCmsPageId}")
            ->assertOk();

        $this->deleteJson("/admin/api/catalog/products/{$createdProductId}")
            ->assertOk();

        $this->assertDatabaseMissing('cms_pages', [
            'id' => $createdCmsPageId,
        ]);

        $this->assertDatabaseMissing('catalog_products', [
            'id' => $createdProductId,
        ]);
    }
}
