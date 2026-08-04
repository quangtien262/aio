<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPage;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
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

        $dashboard = $this->getJson('/admin/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'metrics' => ['admins', 'customers', 'roles', 'permissions', 'modules', 'themes'],
                'setup' => ['website_type', 'active_theme_key', 'is_setup_completed', 'completed_steps'],
                'active_modules' => [
                    ['key', 'name', 'description', 'status', 'icon', 'color', 'route', 'website_types', 'installed_version', 'latest_version', 'menus'],
                ],
            ]);
        $this->assertSame(
            'CMS - Quản trị website',
            collect($dashboard->json('active_modules'))->firstWhere('key', 'cms')['name'] ?? null,
        );

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

        $this->assertDatabaseHas('permissions', [
            'key' => 'catalog.view',
            'is_active' => false,
        ]);
        $this->assertTrue(Schema::hasTable('catalog_products'));
        $this->assertTrue(File::exists(config_path('catalog.php')));
        $this->assertTrue(File::exists(public_path('modules/catalog/catalog-module.json')));
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
        $this->assertFalse((bool) Permission::query()->where('key', 'catalog.view')->value('is_active'));
        $this->assertTrue((bool) ThemeInstallation::query()->where('key', 'corporate-starter')->value('is_active'));
    }

    public function test_admin_can_store_theme_palette_in_setup_branding(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/setup', [
            'site_name' => 'AIO Website',
            'website_type' => 'ecommerce',
            'primary_color' => '#d67a2c',
            'primary_color_deep' => '#af5f1f',
            'accent_color' => '#d98d4a',
            'accent_soft_color' => '#efaa4c',
            'background_color' => '#faf6f1',
            'surface_color' => '#ffffff',
            'surface_tint_color' => '#fff4e8',
        ])->assertOk();

        $siteProfile = SiteProfile::query()->firstOrFail();

        $this->assertSame('#d67a2c', data_get($siteProfile->branding, 'primary_color'));
        $this->assertSame('#af5f1f', data_get($siteProfile->branding, 'primary_color_deep'));
        $this->assertSame('#d98d4a', data_get($siteProfile->branding, 'accent_color'));
        $this->assertSame('#efaa4c', data_get($siteProfile->branding, 'accent_soft_color'));
        $this->assertSame('#faf6f1', data_get($siteProfile->branding, 'background_color'));
        $this->assertSame('#ffffff', data_get($siteProfile->branding, 'surface_color'));
        $this->assertSame('#fff4e8', data_get($siteProfile->branding, 'surface_tint_color'));
        $this->assertContains('branding', $siteProfile->completed_steps ?? []);

        $this->getJson('/admin/api/setup')
            ->assertOk()
            ->assertJsonPath('data.branding.primary_color', '#d67a2c')
            ->assertJsonPath('data.branding.primary_color_deep', '#af5f1f')
            ->assertJsonPath('data.branding.accent_color', '#d98d4a')
            ->assertJsonPath('data.branding.accent_soft_color', '#efaa4c');
    }

    public function test_admin_can_store_theme_palette_per_theme(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->where('email', 'admin@aio.local')->firstOrFail();

        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/themes/SHOP601/palette', [
            'primary_color' => '#123456',
            'primary_color_deep' => '#0f1e2d',
            'accent_color' => '#74b816',
            'accent_soft_color' => '#a9e34b',
            'background_color' => '#fff5f5',
            'surface_color' => '#ffffff',
            'surface_tint_color' => '#fff0f6',
        ])->assertOk();

        $siteProfile = SiteProfile::query()->firstOrFail();

        $this->assertSame('#123456', data_get($siteProfile->theme_palettes, 'SHOP601.primary_color'));
        $this->assertSame('#0f1e2d', data_get($siteProfile->theme_palettes, 'SHOP601.primary_color_deep'));
        $this->assertSame('#74b816', data_get($siteProfile->theme_palettes, 'SHOP601.accent_color'));
        $this->assertNull(data_get($siteProfile->branding, 'primary_color'));
        $this->assertContains('branding', $siteProfile->completed_steps ?? []);

        $this->getJson('/admin/api/setup')
            ->assertOk()
            ->assertJsonPath('data.theme_palettes.SHOP601.primary_color', '#123456')
            ->assertJsonPath('data.theme_palettes.SHOP601.accent_soft_color', '#a9e34b');
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

    public function test_admin_can_manage_admin_accounts_and_role_assignments(): void
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
            'username' => 'scope-admin',
            'email' => 'scope-admin@aio.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
            'assignments' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'global',
                    'scope_value' => null,
                ],
            ],
        ])->assertCreated();

        $scopedAdmin = Admin::query()->where('email', 'scope-admin@aio.local')->firstOrFail();

        $this->assertDatabaseHas('admins', [
            'username' => 'scope-admin',
            'email' => 'scope-admin@aio.local',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => $scopedAdmin->id,
            'role_id' => $role->id,
        ]);

        $this->assertSame(1, AdminRoleAssignment::query()->where('admin_id', $scopedAdmin->id)->count());

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
            'username' => 'scope-admin',
            'email' => 'scope-admin@aio.local',
            'is_active' => true,
            'assignments' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'global',
                    'scope_value' => null,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => $scopedAdmin->id,
            'scope_type' => 'global',
            'scope_value' => null,
        ]);

        Site::query()->create([
            'name' => 'Customer Website',
            'website_key' => 'customer-site',
            'domain' => 'customer.test',
            'status' => 'active',
        ]);

        $this->putJson("/admin/api/admins/{$scopedAdmin->id}", [
            'name' => 'Scope Admin Updated',
            'username' => 'scope-admin',
            'email' => 'scope-admin@aio.local',
            'is_active' => true,
            'assignments' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'website',
                    'scope_value' => 'customer-site',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => $scopedAdmin->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'customer-site',
        ]);

        $adminPayload = $this->getJson('/admin/api/admins')
            ->assertOk()
            ->json('data');

        $serializedAdmin = collect($adminPayload['admins'])->firstWhere('id', $scopedAdmin->id);

        $this->assertSame('customer-site', data_get($serializedAdmin, 'assignments.0.scope_value'));
        $this->assertContains('customer-site', collect($adminPayload['websites'])->pluck('website_key')->all());

        $this->putJson("/admin/api/admins/{$scopedAdmin->id}/password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
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

        $this->assertTrue(Hash::check('NewPassword123!', $scopedAdmin->fresh()->password));
    }

    public function test_admin_account_validation_rejects_legacy_scope_types(): void
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
            'username' => 'broken-scope-admin',
            'email' => 'broken-scope-admin@aio.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
            'assignments' => [
                [
                    'role_id' => $role->id,
                    'scope_type' => 'module',
                    'scope_value' => 'cms',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assignments.0.scope_type']);

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
            ->assertJsonPath('message', 'System Owner không thể bị khóa.');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'is_active' => true,
            'locked_reason' => null,
        ]);
    }

    public function test_cms_and_catalog_queries_are_isolated_by_current_website(): void
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
        ]);

        CmsPage::query()->create([
            'title' => 'Hidden CMS Page',
            'slug' => 'hidden-cms-page',
            'status' => 'draft',
            'body' => 'Hidden page',
            'website_key' => 'website-other',
        ]);

        CatalogProduct::query()->create([
            'name' => 'Scoped Product',
            'sku' => 'SCOPED-001',
            'price' => 120000,
            'stock' => 15,
            'website_key' => 'website-main',
        ]);

        CatalogProduct::query()->create([
            'name' => 'Hidden Product',
            'sku' => 'HIDDEN-001',
            'price' => 150000,
            'stock' => 5,
            'website_key' => 'website-other',
        ]);

        $catalogCategory = CatalogCategory::query()->create([
            'name' => 'Scoped Category',
            'slug' => 'scoped-category',
            'website_key' => 'website-main',
            'is_active' => true,
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
            'username' => 'scoped-data-admin',
            'email' => 'scoped-data-admin@aio.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'status' => 'active',
            'assignments' => [
                ['role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null],
            ],
        ])->assertCreated();

        $scopedAdmin = Admin::query()->where('email', 'scoped-data-admin@aio.local')->firstOrFail();
        $this->actingAs($scopedAdmin, 'admin');
        $this->putJson('/admin/api/me/password', [
            'current_password' => 'Password123!',
            'password' => 'ChangedPassword123!',
            'password_confirmation' => 'ChangedPassword123!',
        ])->assertOk();

        $this->getJson('/admin/api/cms/pages')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'hidden-cms-page']);

        $createdCmsPageId = $this->postJson('/admin/api/cms/pages', [
            'title' => 'Scoped CMS Draft',
            'slug' => 'scoped-cms-draft',
            'status' => 'draft',
            'body' => 'Scoped create',
            'website_key' => 'website-main',
        ])
            ->assertCreated()
            ->json('data.id');

        $forcedCmsPageId = $this->postJson('/admin/api/cms/pages', [
            'title' => 'Out of Scope CMS Draft',
            'slug' => 'out-of-scope-cms-draft',
            'status' => 'draft',
            'body' => 'Hidden create',
            'website_key' => 'website-other',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('cms_pages', ['id' => $forcedCmsPageId, 'website_key' => 'website-main']);

        $this->putJson("/admin/api/cms/pages/{$createdCmsPageId}", [
            'title' => 'Scoped CMS Draft Updated',
            'slug' => 'scoped-cms-draft-updated',
            'status' => 'published',
            'body' => 'Scoped update',
            'website_key' => 'website-main',
        ])->assertOk();

        $hiddenCmsPageId = CmsPage::withoutGlobalScopes()->where('slug', 'hidden-cms-page')->value('id');

        $this->putJson("/admin/api/cms/pages/{$hiddenCmsPageId}", [
            'title' => 'Hidden CMS Updated',
            'slug' => 'hidden-cms-updated',
            'status' => 'draft',
            'body' => 'Should fail',
            'website_key' => 'website-other',
        ])->assertNotFound();

        $this->getJson('/admin/api/catalog/products')
            ->assertOk()
            ->assertJsonMissing(['sku' => 'HIDDEN-001']);

        $createdProductId = $this->postJson('/admin/api/catalog/products', [
            'catalog_category_id' => $catalogCategory->id,
            'name' => 'Scoped Product New',
            'sku' => 'SCOPED-NEW-001',
            'price' => 99000,
            'stock' => 9,
            'website_key' => 'website-main',
        ])
            ->assertCreated()
            ->json('data.id');

        $forcedProductId = $this->postJson('/admin/api/catalog/products', [
            'catalog_category_id' => $catalogCategory->id,
            'name' => 'Out of Scope Product',
            'sku' => 'OUT-SCOPE-001',
            'price' => 110000,
            'stock' => 2,
            'website_key' => 'website-other',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('catalog_products', ['id' => $forcedProductId, 'website_key' => 'website-main']);

        $this->putJson("/admin/api/catalog/products/{$createdProductId}", [
            'catalog_category_id' => $catalogCategory->id,
            'name' => 'Scoped Product Updated',
            'sku' => 'SCOPED-NEW-001',
            'price' => 129000,
            'stock' => 11,
            'website_key' => 'website-main',
        ])->assertOk();

        $hiddenProductId = CatalogProduct::withoutGlobalScopes()->where('sku', 'HIDDEN-001')->value('id');

        $this->deleteJson("/admin/api/catalog/products/{$hiddenProductId}")
            ->assertNotFound();

        $this->deleteJson("/admin/api/cms/pages/{$createdCmsPageId}")
            ->assertOk();

        $this->deleteJson("/admin/api/catalog/products/{$createdProductId}")
            ->assertOk();

        $this->deleteJson("/admin/api/cms/pages/{$forcedCmsPageId}")->assertOk();
        $this->deleteJson("/admin/api/catalog/products/{$forcedProductId}")->assertOk();

        $this->assertDatabaseMissing('cms_pages', [
            'id' => $createdCmsPageId,
        ]);

        $this->assertDatabaseMissing('catalog_products', [
            'id' => $createdProductId,
        ]);
    }

    public function test_disabled_module_api_is_not_available_to_system_owner(): void
    {
        $this->seed(DatabaseSeeder::class);
        ModuleInstallation::query()->create([
            'key' => 'inventory',
            'name' => 'Inventory',
            'version' => '0.1.0',
            'status' => 'disabled',
        ]);

        $admin = Admin::query()->whereKey(Admin::SYSTEM_OWNER_ID)->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/api/inventory/dashboard')
            ->assertNotFound();
    }

    public function test_website_scoped_role_cannot_mutate_global_module_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        $permission = Permission::query()->where('key', 'store.module.disable')->firstOrFail();
        $role = Role::query()->create([
            'key' => 'website-module-operator',
            'name' => 'Website module operator',
            'status' => 'active',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);

        $admin = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
        ]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $admin->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'website-main',
            'assigned_by' => Admin::SYSTEM_OWNER_ID,
        ]);

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Website-Key', 'website-main')
            ->postJson('/admin/api/modules/cms/disable')
            ->assertForbidden();

        $this->assertDatabaseHas('module_installations', [
            'key' => 'cms',
            'status' => 'enabled',
        ]);
    }

    public function test_outdated_module_must_be_upgraded_before_it_can_be_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Admin::query()->whereKey(Admin::SYSTEM_OWNER_ID)->firstOrFail();
        $this->actingAs($admin, 'admin');

        $this->postJson('/admin/api/modules/catalog/install')->assertOk();
        ModuleInstallation::query()->where('key', 'catalog')->update(['version' => '0.1.0']);

        $this->postJson('/admin/api/modules/catalog/enable')->assertUnprocessable();
        $this->assertDatabaseHas('module_installations', [
            'key' => 'catalog',
            'version' => '0.1.0',
            'status' => 'installed',
        ]);

        $this->postJson('/admin/api/modules/catalog/upgrade')->assertOk();
        $this->postJson('/admin/api/modules/catalog/enable')->assertOk();
    }
}
