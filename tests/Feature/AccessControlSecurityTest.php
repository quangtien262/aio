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
