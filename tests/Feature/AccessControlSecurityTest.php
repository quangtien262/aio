<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Support\Totp;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccessControlSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_owner_and_super_admin_role_are_immutable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->where('is_system_owner', true)->firstOrFail();
        $role = Role::query()->where('key', 'super-admin')->firstOrFail();
        $this->actingAs($owner, 'admin');

        $this->assertTrue($owner->isSuperAdmin());
        $this->assertTrue($owner->hasPermission('permission.that.may.be.added.later'));

        $this->putJson("/admin/api/roles/{$role->id}", [
            'name' => 'Changed',
            'key' => 'changed',
            'permission_ids' => [],
        ])->assertStatus(422);

        $this->deleteJson("/admin/api/roles/{$role->id}")->assertStatus(422);
        $this->postJson("/admin/api/admins/{$owner->id}/lock")->assertStatus(422);
        $this->putJson("/admin/api/admins/{$owner->id}", [
            'name' => 'Changed Owner',
            'username' => 'changed-owner',
            'email' => 'changed@example.test',
            'status' => 'suspended',
            'assignments' => [],
        ])->assertStatus(422);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'key' => 'super-admin', 'is_system' => true]);
        $this->assertDatabaseHas('admins', ['id' => $owner->id, 'is_system_owner' => true, 'status' => 'active']);
    }

    public function test_system_owner_is_never_returned_in_admin_account_list(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $visibleAdmin = Admin::factory()->create([
            'name' => 'Content Manager',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->actingAs($owner, 'admin');

        $response = $this->getJson('/admin/api/admins?search=admin')->assertOk();
        $adminIds = collect($response->json('data.admins'))->pluck('id')->map(fn ($id): int => (int) $id);

        $this->assertFalse($adminIds->contains(Admin::SYSTEM_OWNER_ID));
        $this->assertTrue($adminIds->contains($visibleAdmin->id));
    }

    public function test_platform_owner_is_assignable_without_unlocking_super_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $platformOwnerRole = Role::query()->where('key', 'platform-owner')->firstOrFail();
        $superAdminRole = Role::query()->where('key', 'super-admin')->firstOrFail();
        $customerAdmin = Admin::factory()->create([
            'name' => 'Customer Admin',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'admin');

        $accountsPayload = $this->getJson('/admin/api/admins')
            ->assertOk()
            ->json('data');

        $assignableRoleKeys = collect($accountsPayload['roles'])->pluck('key');

        $this->assertTrue($assignableRoleKeys->contains('platform-owner'));
        $this->assertFalse($assignableRoleKeys->contains('super-admin'));
        $this->assertFalse($platformOwnerRole->is_system);
        $this->assertTrue($platformOwnerRole->is_assignable);

        $this->putJson("/admin/api/admins/{$customerAdmin->id}/roles", [
            'role_ids' => [$platformOwnerRole->id],
        ])->assertOk();

        $this->putJson("/admin/api/admins/{$customerAdmin->id}/roles", [
            'role_ids' => [$superAdminRole->id],
        ])->assertStatus(422);

        $customerAdmin->refresh();

        $this->assertFalse($customerAdmin->isSuperAdmin());
        $this->assertTrue($customerAdmin->hasPermission('rbac.role.manage'));
        $this->assertTrue($customerAdmin->hasPermission('admin.account.manage'));
    }

    public function test_role_assignment_is_bound_to_its_website_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        Site::query()->create(['name' => 'Website A', 'website_key' => 'website-a', 'domain' => 'a.test', 'theme_key' => 'XD0302', 'status' => 'active']);
        Site::query()->create(['name' => 'Website B', 'website_key' => 'website-b', 'domain' => 'b.test', 'theme_key' => 'XD0302', 'status' => 'active']);

        $permission = Permission::query()->where('key', 'cms.view')->firstOrFail();
        $role = Role::query()->create(['name' => 'Website A Editor', 'key' => 'website-a-editor']);
        $role->permissions()->sync([$permission->id]);
        $admin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $admin->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'website-a',
        ]);

        $this->actingAs($admin, 'admin');
        $this->withHeader('X-Website-Key', 'website-a')->getJson('/admin/api/cms/pages')->assertOk();
        $this->withHeader('X-Website-Key', 'website-b')->getJson('/admin/api/cms/pages')->assertForbidden();

        $this->assertTrue($admin->hasPermission('cms.view', 'website-a'));
        $this->assertFalse($admin->hasPermission('cms.view', 'website-b'));
    }

    public function test_website_scoped_admin_cannot_read_or_mutate_global_security_and_site_mapping_state(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $otherSite = Site::query()->create([
            'name' => 'Other Website',
            'website_key' => 'website-other',
            'domain' => 'other.test',
            'theme_key' => 'DN302',
            'status' => 'active',
        ]);

        $permissionIds = Permission::query()
            ->whereIn('key', [
                'admin.account.view',
                'admin.account.manage',
                'admin.account.reset_password',
                'admin.account.lock',
                'admin.audit.view',
                'rbac.role.view',
                'rbac.role.manage',
                'rbac.permission.assign',
                'theme.view',
                'theme.customize',
            ])
            ->pluck('id');
        $role = Role::query()->create([
            'name' => 'Website Security Operator',
            'key' => 'website-security-operator',
            'status' => 'active',
            'is_system' => false,
            'is_assignable' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $scopedAdmin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $targetAdmin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $scopedAdmin->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'website-main',
            'assigned_by' => $owner->id,
        ]);
        AuditLog::query()->create([
            'actor_admin_id' => $owner->id,
            'action' => 'security.global.secret',
            'website_key' => 'website-other',
        ]);

        $this->actingAs($scopedAdmin, 'admin')->withHeader('X-Website-Key', 'website-main');

        $this->getJson('/admin/api/admins')->assertForbidden();
        $this->getJson('/admin/api/access')->assertForbidden();
        $this->getJson('/admin/api/audit-logs')->assertForbidden();
        $this->postJson('/admin/api/roles', [
            'name' => 'Escalated Role',
            'key' => 'escalated-role',
            'permission_ids' => [],
        ])->assertForbidden();
        $this->putJson("/admin/api/admins/{$targetAdmin->id}/roles", [
            'role_ids' => [$role->id],
        ])->assertForbidden();
        $this->getJson('/admin/api/site-mappings')->assertForbidden();
        $this->patchJson("/admin/api/site-mappings/{$otherSite->id}/checklist", [
            'tested' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['key' => 'escalated-role']);
        $this->assertDatabaseMissing('admin_role_assignments', ['admin_id' => $targetAdmin->id]);
        $this->assertFalse((bool) data_get($otherSite->fresh()->settings, 'checklist.tested', false));
    }

    public function test_global_rbac_operator_cannot_delegate_or_create_permissions_beyond_its_own_ceiling(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $platformOwnerRole = Role::query()->where('key', Role::PLATFORM_OWNER_KEY)->firstOrFail();
        $operatorRole = Role::query()->create([
            'name' => 'Limited Global RBAC Operator',
            'key' => 'limited-global-rbac-operator',
            'status' => 'active',
            'is_system' => false,
            'is_assignable' => true,
        ]);
        $operatorRole->permissions()->sync(Permission::query()
            ->whereIn('key', ['admin.account.manage', 'rbac.permission.assign', 'rbac.role.manage'])
            ->pluck('id'));
        $operator = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $operator->id,
            'role_id' => $operatorRole->id,
            'scope_type' => 'global',
            'scope_value' => null,
            'assigned_by' => $owner->id,
        ]);
        $target = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $extraPermission = Permission::query()->where('key', 'theme.customize')->firstOrFail();

        $this->actingAs($operator, 'admin');

        $this->postJson('/admin/api/admins', [
            'name' => 'Escalated Admin',
            'username' => 'escalated-admin',
            'email' => 'escalated-admin@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'status' => 'active',
            'assignments' => [[
                'role_id' => $platformOwnerRole->id,
                'scope_type' => 'global',
                'scope_value' => null,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['assignments']);

        $this->putJson("/admin/api/admins/{$target->id}/roles", [
            'role_ids' => [$platformOwnerRole->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['assignments']);

        $this->postJson('/admin/api/roles', [
            'name' => 'Broader Than Actor',
            'key' => 'broader-than-actor',
            'permission_ids' => [$extraPermission->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permission_ids']);

        $this->assertDatabaseMissing('admins', ['username' => 'escalated-admin']);
        $this->assertDatabaseMissing('admin_role_assignments', ['admin_id' => $target->id]);
        $this->assertDatabaseMissing('roles', ['key' => 'broader-than-actor']);
    }

    public function test_legacy_role_endpoint_refuses_to_convert_website_assignments_to_global(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $role = Role::query()->create([
            'name' => 'Scoped Legacy Role',
            'key' => 'scoped-legacy-role',
            'status' => 'active',
            'is_system' => false,
            'is_assignable' => true,
        ]);
        $target = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'website-main',
            'assigned_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'admin')
            ->putJson("/admin/api/admins/{$target->id}/roles", ['role_ids' => [$role->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_ids']);

        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'website-main',
        ]);
        $this->assertDatabaseMissing('admin_role_assignments', [
            'admin_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => 'global',
        ]);
    }

    public function test_platform_owner_role_and_scoped_super_admin_invariants_are_enforced(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $platformOwnerRole = Role::query()->where('key', Role::PLATFORM_OWNER_KEY)->firstOrFail();
        $superAdminRole = Role::query()->where('key', Role::SUPER_ADMIN_KEY)->firstOrFail();
        $permissionCount = Permission::query()->where('is_active', true)->count();

        $this->actingAs($owner, 'admin');
        $this->putJson("/admin/api/roles/{$platformOwnerRole->id}", [
            'name' => 'Reduced Administrator',
            'key' => Role::PLATFORM_OWNER_KEY,
            'permission_ids' => [],
        ])->assertUnprocessable();
        $this->deleteJson("/admin/api/roles/{$platformOwnerRole->id}")->assertUnprocessable();

        $this->assertSame($permissionCount, $platformOwnerRole->fresh()->permissions()->where('is_active', true)->count());

        $scopedAdmin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $scopedAdmin->id,
            'role_id' => $superAdminRole->id,
            'scope_type' => 'website',
            'scope_value' => 'website-main',
            'assigned_by' => $owner->id,
        ]);

        $this->assertFalse($scopedAdmin->isSuperAdmin());
        $this->flushSession();
        $this->actingAs($scopedAdmin, 'admin')
            ->withHeader('X-Website-Key', 'website-main')
            ->getJson('/admin/api/access')
            ->assertForbidden();
    }

    public function test_auth_version_revokes_an_existing_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Admin::query()->firstOrFail();
        $this->actingAs($admin, 'admin')->withSession(['admin_auth_version' => 1]);
        $admin->update(['auth_version' => 2]);

        $this->getJson('/admin/api/me')->assertUnauthorized();
        $this->assertGuest('admin');
    }

    public function test_admin_must_confirm_current_password_when_changing_own_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Admin::query()->firstOrFail();
        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->putJson('/admin/api/me/password', [
            'current_password' => 'password',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecurePassword123!', $admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password.changed', 'target_id' => (string) $admin->id]);
    }

    public function test_admin_password_policy_returns_readable_vietnamese_messages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Admin::query()->findOrFail(1);
        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/me/password', [
            'current_password' => 'password',
            'password' => 'alllowercase1!',
            'password_confirmation' => 'alllowercase1!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Mật khẩu phải có ít nhất một chữ hoa và một chữ thường.')
            ->assertJsonPath('errors.password.0', 'Mật khẩu phải có ít nhất một chữ hoa và một chữ thường.');
    }

    public function test_legacy_tenant_and_owner_access_columns_are_removed(): void
    {
        $this->assertFalse(Schema::hasTable('admin_role'));
        $this->assertFalse(Schema::hasTable('admin_role_scopes'));
        $this->assertFalse(Schema::hasColumn('cms_pages', 'tenant_key'));
        $this->assertFalse(Schema::hasColumn('cms_pages', 'owner_key'));
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_two_factor_authentication_is_required_after_it_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Admin::query()->findOrFail(1);
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $admin->forceFill([
            'password' => 'CurrentPassword123!',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->postJson(route('customer.auth.store', ['locale' => 'vi']), [
            'login' => $admin->username,
            'password' => 'CurrentPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['two_factor_code']);

        $method = new \ReflectionMethod(Totp::class, 'codeAt');
        $code = $method->invoke($totp, $secret, (int) floor(time() / 30));

        $this->postJson(route('customer.auth.store', ['locale' => 'vi']), [
            'login' => $admin->username,
            'password' => 'CurrentPassword123!',
            'two_factor_code' => $code,
        ])->assertOk()->assertJsonPath('data.guard', 'admin');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.admin.two_factor_failed', 'target_id' => (string) $admin->id]);
    }

    public function test_unknown_website_header_cannot_create_a_ghost_context(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);

        $this->actingAs($owner, 'admin')
            ->withHeader('X-Website-Key', 'ghost-website')
            ->getJson('/admin/api/cms/pages')
            ->assertNotFound();
    }

    public function test_me_endpoint_recovers_to_first_accessible_active_website(): void
    {
        $this->seed(DatabaseSeeder::class);
        Site::query()->create([
            'name' => 'Website A',
            'website_key' => 'website-a',
            'domain' => 'website-a.test',
            'status' => 'active',
        ]);

        $permission = Permission::query()->where('key', 'cms.view')->firstOrFail();
        $role = Role::query()->create(['name' => 'Website A Editor', 'key' => 'website-a-bootstrap-editor']);
        $role->permissions()->sync([$permission->id]);
        $admin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $admin->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => 'website-a',
        ]);

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Website-Key', 'website-main')
            ->getJson('/admin/api/me')
            ->assertOk()
            ->assertJsonPath('data.current_website.website_key', 'website-a')
            ->assertJsonPath('data.site_options.0.website_key', 'website-a');
    }

    public function test_password_change_is_enforced_by_backend_before_other_admin_apis(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $admin->update(['must_change_password' => true]);
        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/me')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
        $this->getJson('/admin/api/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');

        $this->putJson('/admin/api/me/password', [
            'current_password' => 'password',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertOk();

        $this->getJson('/admin/api/dashboard')->assertOk();
    }
}
