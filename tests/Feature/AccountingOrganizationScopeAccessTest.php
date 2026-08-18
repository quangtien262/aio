<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\AcctDocument;
use App\Models\AcctEinvoiceTransmission;
use App\Models\AcctOrganization;
use App\Models\AcctParty;
use App\Models\AcctProviderConnection;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingOrganizationScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_scoped_accounting_role_cannot_read_or_mutate_another_organization(): void
    {
        $owner = $this->bootAccounting();
        [$organizationA, $organizationB] = $this->organizations();
        $partyA = AcctParty::query()->create([
            'organization_id' => $organizationA->id,
            'type' => 'customer',
            'name' => 'Customer A',
        ]);
        $partyB = AcctParty::query()->create([
            'organization_id' => $organizationB->id,
            'type' => 'customer',
            'name' => 'Customer B',
        ]);
        $documentA = AcctDocument::query()->create([
            'organization_id' => $organizationA->id,
            'direction' => 'outbound',
            'document_type' => 'internal_invoice',
            'document_date' => '2026-08-17',
        ]);
        $documentB = AcctDocument::query()->create([
            'organization_id' => $organizationB->id,
            'direction' => 'outbound',
            'document_type' => 'internal_invoice',
            'document_date' => '2026-08-17',
        ]);

        $role = Role::query()->create([
            'name' => 'Organization A accountant',
            'key' => 'organization-a-accountant',
            'status' => 'active',
            'is_assignable' => true,
        ]);
        // Include a legacy invalid permission to prove UI permission union is
        // filtered even when old data bypassed today's assignment validation.
        $role->permissions()->sync(Permission::query()
            ->whereIn('key', ['accounting.view', 'accounting.party.manage', 'cms.view'])
            ->pluck('id'));
        $accountant = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $accountant->id,
            'role_id' => $role->id,
            'scope_type' => 'organization',
            'scope_value' => (string) $organizationA->id,
            'assigned_by' => $owner->id,
        ]);

        $this->actingAs($accountant, 'admin');

        $this->getJson("/admin/api/accounting-tax/parties?organization_id={$organizationA->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $partyA->id)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.per_page', 30);
        $this->getJson("/admin/api/accounting-tax/parties?organization_id={$organizationB->id}")
            ->assertForbidden();
        $this->getJson('/admin/api/accounting-tax/parties')->assertForbidden();
        $this->putJson("/admin/api/accounting-tax/parties/{$partyB->id}", [
            'name' => 'Cross organization overwrite',
        ])->assertForbidden();
        $this->getJson("/admin/api/accounting-tax/documents/{$documentA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $documentA->id);
        $this->getJson("/admin/api/accounting-tax/documents/{$documentB->id}")
            ->assertForbidden();

        $organizationResponse = $this->getJson('/admin/api/accounting-tax/organizations')->assertOk();
        $this->assertSame(
            [$organizationA->id],
            collect($organizationResponse->json('data.items'))->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $profile = $this->getJson('/admin/api/me')->assertOk();
        $this->assertContains('accounting.view', $profile->json('data.permissions'));
        $this->assertNotContains('cms.view', $profile->json('data.permissions'));
        $this->assertSame(
            [$organizationA->id],
            collect($profile->json('data.organization_options'))->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
        $this->assertNotEmpty(collect($profile->json('data.module_navigation'))->where('module_key', 'accounting-tax'));
        $this->assertEmpty(collect($profile->json('data.module_navigation'))->where('module_key', 'cms'));

        $this->assertSame('Customer B', $partyB->fresh()->name);

        $this->actingAs($owner, 'admin')
            ->getJson("/admin/api/accounting-tax/parties?organization_id={$organizationB->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $partyB->id);
    }

    public function test_account_assignment_accepts_accounting_org_role_and_rejects_mixed_module_org_role(): void
    {
        $owner = $this->bootAccounting();
        [$organization] = $this->organizations();
        $accountingRole = $this->roleWithPermissions('accounting-org-clerk', [
            'accounting.view',
            'accounting.document.create',
        ]);
        $mixedRole = $this->roleWithPermissions('mixed-org-role', [
            'accounting.view',
            'cms.view',
        ]);

        $this->actingAs($owner, 'admin');

        $created = $this->postJson('/admin/api/admins', [
            'name' => 'Organization Clerk',
            'username' => 'organization-clerk',
            'email' => 'organization-clerk@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'status' => 'active',
            'assignments' => [[
                'role_id' => $accountingRole->id,
                'scope_type' => 'organization',
                'scope_value' => (string) $organization->id,
            ]],
        ])->assertCreated();

        $createdAdmin = Admin::query()->where('username', 'organization-clerk')->firstOrFail();
        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => $createdAdmin->id,
            'role_id' => $accountingRole->id,
            'scope_type' => 'organization',
            'scope_value' => (string) $organization->id,
        ]);
        $this->assertSame('Tạo tài khoản quản trị thành công.', $created->json('message'));

        $this->putJson("/admin/api/roles/{$accountingRole->id}", [
            'name' => $accountingRole->name,
            'key' => $accountingRole->key,
            'permission_ids' => Permission::query()
                ->whereIn('key', ['accounting.view', 'cms.view'])
                ->pluck('id')
                ->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['permission_ids']);

        $this->postJson('/admin/api/admins', [
            'name' => 'Mixed Organization User',
            'username' => 'mixed-organization-user',
            'email' => 'mixed-organization-user@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'status' => 'active',
            'assignments' => [[
                'role_id' => $mixedRole->id,
                'scope_type' => 'organization',
                'scope_value' => (string) $organization->id,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['assignments']);

        $this->postJson('/admin/api/admins', [
            'name' => 'Missing Organization User',
            'username' => 'missing-organization-user',
            'email' => 'missing-organization-user@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'status' => 'active',
            'assignments' => [[
                'role_id' => $accountingRole->id,
                'scope_type' => 'organization',
                'scope_value' => '999999',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['assignments']);
    }

    public function test_minvoice_connections_inbound_queries_and_nested_transmission_routes_are_org_scoped(): void
    {
        $owner = $this->bootAccounting();
        $manager = app(ModuleManager::class);
        $manager->install('minvoice-connector');
        $manager->enable('minvoice-connector');
        [$organizationA, $organizationB] = $this->organizations();
        $connectionA = $this->inboundConnection($organizationA, 'Inbound A');
        $connectionB = $this->inboundConnection($organizationB, 'Inbound B');
        $documentB = AcctDocument::query()->create([
            'organization_id' => $organizationB->id,
            'direction' => 'outbound',
            'document_type' => 'internal_invoice',
            'document_date' => '2026-08-17',
        ]);
        $transmissionB = AcctEinvoiceTransmission::query()->create([
            'document_id' => $documentB->id,
            'connection_id' => $connectionB->id,
            'provider' => 'minvoice',
            'operation' => 'download_pdf',
            'operation_key' => 'org-b-transmission-artifact',
        ]);
        $role = $this->roleWithPermissions('minvoice-org-viewer', [
            'minvoice.view',
            'minvoice.connection.manage',
            'minvoice.artifact.download',
        ]);
        $operator = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $operator->id,
            'role_id' => $role->id,
            'scope_type' => 'organization',
            'scope_value' => (string) $organizationA->id,
            'assigned_by' => $owner->id,
        ]);

        $this->actingAs($operator, 'admin');

        $this->getJson("/admin/api/accounting-tax/minvoice/connections?organization_id={$organizationA->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $connectionA->id);
        $this->getJson("/admin/api/accounting-tax/minvoice/connections?organization_id={$organizationB->id}")
            ->assertForbidden();
        $this->getJson("/admin/api/accounting-tax/minvoice/inbound?connection_id={$connectionA->id}")
            ->assertOk();
        $this->getJson("/admin/api/accounting-tax/minvoice/inbound?connection_id={$connectionB->id}")
            ->assertForbidden();
        $this->putJson("/admin/api/accounting-tax/minvoice/connections/{$connectionB->id}", [])
            ->assertForbidden();
        $this->getJson("/admin/api/accounting-tax/minvoice/transmissions/{$transmissionB->id}/artifacts/pdf")
            ->assertForbidden();
    }

    private function bootAccounting(): Admin
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');

        return Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
    }

    /** @return array{AcctOrganization, AcctOrganization} */
    private function organizations(): array
    {
        $organizationA = AcctOrganization::query()->create([
            'name' => 'Legal Entity A',
            'legal_name' => 'Legal Entity A',
            'tax_code' => '0101111111',
            'is_default' => true,
            'status' => 'active',
        ]);
        $organizationB = AcctOrganization::query()->create([
            'name' => 'Legal Entity B',
            'legal_name' => 'Legal Entity B',
            'tax_code' => '0102222222',
            'status' => 'active',
        ]);

        return [$organizationA, $organizationB];
    }

    /** @param list<string> $permissionKeys */
    private function roleWithPermissions(string $key, array $permissionKeys): Role
    {
        $role = Role::query()->create([
            'name' => $key,
            'key' => $key,
            'status' => 'active',
            'is_assignable' => true,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissionKeys)->pluck('id'));

        return $role;
    }

    private function inboundConnection(AcctOrganization $organization, string $name): AcctProviderConnection
    {
        return AcctProviderConnection::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'provider' => 'minvoice',
            'channel' => 'inbound',
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.example.test',
            'credentials' => ['api_token' => 'test-token'],
            'allowed_hosts' => ['sandbox.example.test'],
            'is_enabled' => true,
        ]);
    }
}
