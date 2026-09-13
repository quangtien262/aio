<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\ModuleInstallation;
use App\Models\ModuleLifecycleOperation;
use App\Models\ModuleRoleDefinition;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Security\FnbVersionedSecurity;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\Security\FnbApprovalService;
use Modules\FnbPos\Services\Security\FnbOperationalStateManager;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbReauthService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class FnbSecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->owner->forceFill([
            'password' => 'FnbPassword123!',
            'must_change_password' => false,
        ])->save();
        $this->actingAs($this->owner, 'admin');
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
    }

    public function test_exact_version_catalog_presets_and_critical_registry_are_synchronized(): void
    {
        $definition = app(FnbVersionedSecurity::class)->definition('0.1.0-dev.1');

        $this->assertCount(53, $definition->permissions);
        $this->assertCount(8, $definition->roles);
        $this->assertSame(
            array_keys($definition->permissions),
            Permission::query()->where('module_key', 'fnb-pos')->orderBy('id')->pluck('key')->all(),
        );

        foreach ($definition->roles as $roleKey => $expected) {
            $role = Role::query()->where('key', $roleKey)->firstOrFail();
            $ownership = ModuleRoleDefinition::query()->where('role_id', $role->id)->firstOrFail();
            $actual = $role->permissions()->orderBy('permissions.key')->pluck('permissions.key')->all();
            $permissions = $expected['permissions'];
            sort($actual);
            sort($permissions);

            $this->assertSame($permissions, $actual, $roleKey);
            $this->assertTrue($role->is_system);
            $this->assertFalse($role->is_assignable);
            $this->assertSame('0.1.0-dev.1', $ownership->version);
            $this->assertSame('0.1.0-dev.1', $ownership->introduced_version);
            $this->assertSame('fnb_dedicated', $ownership->assignment_channel);
            $this->assertSame('deny', $ownership->custom_role_policy);
        }

        $critical = collect($definition->permissions)
            ->filter(fn (array $permission): bool => $permission['risk_level'] === 'critical')
            ->keys();
        $registered = collect($definition->criticalPolicies)
            ->flatMap(fn (array $policy): array => [...($policy['executor'] ?? []), ...($policy['approver'] ?? [])])
            ->unique();
        $this->assertSame([], $critical->diff($registered)->values()->all());
    }

    public function test_generic_role_endpoints_cannot_create_or_assign_fnb_permission_roles(): void
    {
        $permission = Permission::query()->where('key', 'fnb.order.create')->firstOrFail();

        $this->postJson('/admin/api/roles', [
            'name' => 'F&B bypass',
            'key' => 'fnb-bypass',
            'permission_ids' => [$permission->id],
        ])->assertStatus(422);

        $legacy = Role::query()->create([
            'name' => 'Legacy F&B drift',
            'key' => 'legacy-fnb-drift',
            'status' => 'active',
            'is_system' => false,
            'is_assignable' => true,
        ]);
        $legacy->permissions()->sync([$permission->id]);
        $target = Admin::factory()->create(['status' => 'active', 'is_active' => true]);

        $this->putJson("/admin/api/admins/{$target->id}/roles", [
            'role_ids' => [$legacy->id],
        ])->assertStatus(422);
        $this->postJson('/admin/api/admins', [
            'name' => 'Scoped bypass',
            'username' => 'scoped-fnb-bypass',
            'email' => 'scoped-fnb-bypass@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
            'assignments' => [[
                'role_id' => $legacy->id,
                'scope_type' => 'website',
                'scope_value' => 'website-main',
            ]],
        ])->assertStatus(422);
    }

    public function test_outlet_access_requires_exact_active_preset_binding_and_membership(): void
    {
        $outletId = $this->outlet();
        $staff = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(
            $this->owner,
            'website-main',
            $outletId,
            $staff,
            'fnb-manager',
        ));

        $access = app(FnbOutletAccessService::class);
        $access->authorize($staff->fresh(), 'website-main', $outletId, 'fnb.order.create');
        $this->assertSame([$outletId], $access->accessibleOutletIds(
            $staff->fresh(),
            'website-main',
            'fnb.order.create',
        ));

        Role::query()->where('key', 'fnb-manager')->update(['status' => 'inactive']);
        try {
            $access->authorize($staff->fresh(), 'website-main', $outletId, 'fnb.order.create');
            $this->fail('A suspended preset role must fail closed.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        Role::query()->where('key', 'fnb-manager')->update(['status' => 'active']);
        $definition = ModuleRoleDefinition::query()->where('role_key', 'fnb-manager')->firstOrFail();
        $definitionHash = $definition->definition_hash;
        $definition->forceFill(['definition_hash' => str_repeat('0', 64)])->save();
        try {
            $access->authorize($staff->fresh(), 'website-main', $outletId, 'fnb.order.create');
            $this->fail('A preset that drifts from its immutable definition must fail closed.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        try {
            DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(
                $this->owner,
                'website-main',
                $outletId,
                Admin::factory()->create(['status' => 'active', 'is_active' => true]),
                'fnb-manager',
            ));
            $this->fail('A drifted preset must not be assigned even by a core owner.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $definition->forceFill(['definition_hash' => $definitionHash])->save();
        $access->authorize($staff->fresh(), 'website-main', $outletId, 'fnb.order.create');

        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->firstOrFail();
        $installedVersion = $installation->version;
        $installation->forceFill(['version' => '9.9.9'])->save();
        try {
            $access->authorize($staff->fresh(), 'website-main', $outletId, 'fnb.order.create');
            $this->fail('An installed version without an immutable security map must fail closed.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $installation->forceFill(['version' => $installedVersion])->save();

        $globalOnly = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $managerRole = Role::query()->where('key', 'fnb-manager')->firstOrFail();
        AdminRoleAssignment::query()->create([
            'admin_id' => $globalOnly->id,
            'role_id' => $managerRole->id,
            'scope_type' => 'global',
            'scope_value' => null,
            'assigned_by' => $this->owner->id,
        ]);
        $globalSuperAdmin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $globalSuperAdmin->id,
            'role_id' => Role::query()->where('key', Role::SUPER_ADMIN_KEY)->value('id'),
            'scope_type' => 'global',
            'scope_value' => null,
            'assigned_by' => $this->owner->id,
        ]);

        foreach ([$globalOnly, $globalSuperAdmin] as $globalActor) {
            try {
                $access->authorize($globalActor, 'website-main', $outletId, 'fnb.order.create');
                $this->fail('A global role without exact F&B binding and membership must fail closed.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_terminal_restrictions_are_enforced_by_the_authoritative_access_service(): void
    {
        $outletId = $this->outlet();
        $allowedTerminal = $this->terminal($outletId, 'POS-ALLOWED');
        $otherTerminal = $this->terminal($outletId, 'POS-OTHER');
        $staff = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(
            $this->owner,
            'website-main',
            $outletId,
            $staff,
            'fnb-cashier',
            terminalIds: [$allowedTerminal],
        ));

        $access = app(FnbOutletAccessService::class);
        $access->authorizeTerminal($staff->fresh(), 'website-main', $outletId, $allowedTerminal, 'fnb.order.create');
        $this->assertSame(
            [$allowedTerminal],
            $access->accessibleTerminalIds($staff->fresh(), 'website-main', $outletId, 'fnb.order.create'),
        );

        foreach ([null, $otherTerminal] as $terminalId) {
            try {
                $access->authorizeTerminal($staff->fresh(), 'website-main', $outletId, $terminalId, 'fnb.order.create');
                $this->fail('A terminal-restricted staff member must not escape the assigned terminal.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        DB::table('fnb_terminals')->where('id', $allowedTerminal)->update(['status' => 'inactive']);
        $this->assertSame(
            [],
            $access->accessibleTerminalIds($staff->fresh(), 'website-main', $outletId, 'fnb.order.create'),
        );
    }

    public function test_staff_assignment_enforces_hierarchy_and_optimistic_versions(): void
    {
        $outletId = $this->outlet();
        $manager = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $staff = app(FnbStaffAssignmentService::class);
        DB::transaction(fn () => $staff->assign($this->owner, 'website-main', $outletId, $manager, 'fnb-manager'));

        $fnbOwner = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $ownerAssignment = DB::transaction(fn () => $staff->assign(
            $this->owner,
            'website-main',
            $outletId,
            $fnbOwner,
            'fnb-owner',
        ));
        foreach (['replace', 'revoke'] as $operation) {
            try {
                DB::transaction(fn () => $operation === 'replace'
                    ? $staff->assign(
                        $manager->fresh(),
                        'website-main',
                        $outletId,
                        $fnbOwner->fresh(),
                        'fnb-cashier',
                        $ownerAssignment['binding_version'],
                        $ownerAssignment['membership_version'],
                    )
                    : $staff->revoke(
                        $manager->fresh(),
                        'website-main',
                        $outletId,
                        $fnbOwner->fresh(),
                        $ownerAssignment['binding_version'],
                        $ownerAssignment['membership_version'],
                    ));
                $this->fail('Manager must not replace or revoke an F&B owner binding.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $target = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        try {
            DB::transaction(fn () => $staff->assign($manager->fresh(), 'website-main', $outletId, $target, 'fnb-owner'));
            $this->fail('Manager must not assign an owner preset.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $resource = DB::transaction(fn () => $staff->assign(
            $manager->fresh(),
            'website-main',
            $outletId,
            $target,
            'fnb-cashier',
        ));
        $this->assertSame('fnb-cashier', $resource['role_key']);

        try {
            DB::transaction(fn () => $staff->assign(
                $manager->fresh(),
                'website-main',
                $outletId,
                $target->fresh(),
                'fnb-waiter',
                null,
                null,
            ));
            $this->fail('Existing resources require optimistic versions.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_staff_candidate_assignment_and_revoke_api_are_one_time_scoped_and_reauth_bound(): void
    {
        $outletId = $this->outlet();
        $terminalId = $this->terminal($outletId, 'POS-STAFF');
        $target = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'username' => 'candidate-staff',
            'email' => 'candidate-staff@example.test',
        ]);
        $this->withHeader('X-FNB-Outlet', (string) $outletId);

        $candidate = $this->postJson("/admin/api/fnb/outlets/{$outletId}/staff-candidates/resolve", [
            'identifier' => $target->email,
        ])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data.candidate');
        $this->assertNotNull($candidate);
        $this->assertArrayNotHasKey('admin_id', $candidate);
        $this->assertStringNotContainsString($target->email, json_encode($candidate, JSON_THROW_ON_ERROR));

        $assignPayload = [
            'candidate_token' => $candidate['candidate_token'],
            'role_key' => 'fnb-cashier',
            'terminal_ids' => [$terminalId],
        ];
        $assignKey = (string) Str::uuid();
        $challenge = $this->withHeader('Idempotency-Key', $assignKey)
            ->postJson("/admin/api/fnb/outlets/{$outletId}/staff-assignments", $assignPayload)
            ->assertStatus(423);
        $this->assertDatabaseHas('fnb_staff_candidate_grants', ['consumed_at' => null]);

        $proof = $this->postJson('/admin/api/fnb/reauth/proofs', [
            'action' => 'staff.assign',
            'subject' => 'new',
            'payload_hash' => $challenge->json('details.payload_hash'),
            'password' => 'FnbPassword123!',
        ])->assertOk()->json('data.reauth_proof');
        $assigned = $this->withHeader('X-FNB-Reauth-Proof', $proof)
            ->withHeader('Idempotency-Key', $assignKey)
            ->postJson("/admin/api/fnb/outlets/{$outletId}/staff-assignments", $assignPayload)
            ->assertOk()
            ->assertJsonPath('data.role_key', 'fnb-cashier')
            ->json('data');
        $this->assertDatabaseMissing('fnb_staff_candidate_grants', ['consumed_at' => null]);

        $this->withHeader('X-FNB-Reauth-Proof', '')
            ->withHeader('Idempotency-Key', $assignKey)
            ->postJson("/admin/api/fnb/outlets/{$outletId}/staff-assignments", $assignPayload)
            ->assertOk()
            ->assertJsonPath('meta.replayed', true);

        $revokePayload = [
            'expected_binding_version' => $assigned['binding_version'],
            'expected_membership_version' => $assigned['membership_version'],
        ];
        $revokeKey = (string) Str::uuid();
        $revokeChallenge = $this->withHeader('Idempotency-Key', $revokeKey)
            ->deleteJson("/admin/api/fnb/outlets/{$outletId}/staff-assignments/{$assigned['membership_id']}", $revokePayload)
            ->assertStatus(423);
        $revokeProof = $this->postJson('/admin/api/fnb/reauth/proofs', [
            'action' => 'staff.revoke',
            'subject' => (string) $assigned['membership_id'],
            'payload_hash' => $revokeChallenge->json('details.payload_hash'),
            'password' => 'FnbPassword123!',
        ])->assertOk()->json('data.reauth_proof');
        $this->withHeader('X-FNB-Reauth-Proof', $revokeProof)
            ->withHeader('Idempotency-Key', $revokeKey)
            ->deleteJson("/admin/api/fnb/outlets/{$outletId}/staff-assignments/{$assigned['membership_id']}", $revokePayload)
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.binding_status', 'revoked');

        $this->assertDatabaseHas('fnb_outlet_staff', [
            'id' => $assigned['membership_id'],
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('admin_role_assignments', [
            'admin_id' => $target->id,
            'scope_type' => 'website',
            'scope_value' => 'website-main',
        ]);
    }

    public function test_receipt_snapshot_requires_payment_collect_permission_and_exact_outlet_scope(): void
    {
        $outletId = $this->outlet();
        $otherOutletId = $this->outlet();
        $terminalId = $this->terminal($outletId, 'POS-RECEIPT');
        $snapshot = ['document_type' => 'sales_receipt', 'not_tax_invoice' => true];
        $jobId = (int) DB::table('fnb_print_jobs')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $outletId,
            'terminal_id' => $terminalId,
            'document_type' => 'check',
            'document_id' => '42',
            'template_key' => 'cafe-receipt',
            'template_version' => 1,
            'payload_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'payload_hash' => app(FnbCommandRunner::class)->fingerprint($snapshot),
            'status' => 'generated',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'request_count' => 0,
            'reprint_count' => 0,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $waiter = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        $cashier = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        DB::transaction(function () use ($outletId, $waiter, $cashier): void {
            $staff = app(FnbStaffAssignmentService::class);
            $staff->assign($this->owner, 'website-main', $outletId, $waiter, 'fnb-waiter');
            $staff->assign($this->owner, 'website-main', $outletId, $cashier, 'fnb-cashier');
        });

        $waiter = $waiter->fresh();
        $this->actingAs($waiter, 'admin')->withSession(['admin_auth_version' => $waiter->auth_version])
            ->withHeader('X-FNB-Outlet', (string) $outletId)
            ->getJson("/admin/api/fnb/print-jobs/{$jobId}")
            ->assertForbidden();
        $cashier = $cashier->fresh();
        $this->actingAs($cashier, 'admin')->withSession(['admin_auth_version' => $cashier->auth_version])
            ->withHeader('X-FNB-Outlet', (string) $outletId)
            ->getJson("/admin/api/fnb/print-jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.snapshot.not_tax_invoice', true);
        $owner = $this->owner->fresh();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version])
            ->withHeader('X-FNB-Outlet', (string) $otherOutletId)
            ->getJson("/admin/api/fnb/print-jobs/{$jobId}")
            ->assertNotFound();
    }

    public function test_approval_token_is_claimed_by_requester_session_rotated_and_consumed_atomically(): void
    {
        $outletId = $this->outlet();
        $approver = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'password' => 'ApproverPassword123!',
        ]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(
            $this->owner,
            'website-main',
            $outletId,
            $approver,
            'fnb-manager',
        ));

        $payloadHash = hash('sha256', 'refund-payload');
        $approvalService = app(FnbApprovalService::class);
        $approval = $approvalService->request(
            $this->owner->fresh(),
            'requester-session',
            'website-main',
            $outletId,
            'payment.refund',
            'fnb_command',
            'payment-42',
            $payloadHash,
            'Khách yêu cầu hoàn tiền',
            'approval-refund-42',
        );
        $proof = app(FnbReauthService::class)->issue(
            $approver->fresh(),
            'approver-session',
            '127.0.0.1',
            'website-main',
            $outletId,
            'approval.approve',
            (string) $approval->public_id,
            $payloadHash,
            'ApproverPassword123!',
        );
        $approval = $approvalService->approve(
            $approval,
            $approver->fresh(),
            'approver-session',
            $proof->token,
            null,
            1,
        );

        try {
            $approvalService->claimApprovedToken($approval, $this->owner->fresh(), 'wrong-session');
            $this->fail('A different requester session must not claim the token.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $first = $approvalService->claimApprovedToken($approval, $this->owner->fresh(), 'requester-session');
        $second = $approvalService->claimApprovedToken($approval->fresh(), $this->owner->fresh(), 'requester-session');
        try {
            DB::transaction(fn () => $approvalService->consume(
                $first->token,
                $this->owner->fresh(),
                'requester-session',
                'website-main',
                $outletId,
                'payment.refund',
                'fnb_command',
                'payment-42',
                $payloadHash,
            ));
            $this->fail('Claim rotation must invalidate the prior opaque token.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        DB::transaction(fn () => $approvalService->consume(
            $second->token,
            $this->owner->fresh(),
            'requester-session',
            'website-main',
            $outletId,
            'payment.refund',
            'fnb_command',
            'payment-42',
            $payloadHash,
        ));
        $this->assertDatabaseHas('fnb_approvals', ['id' => $approval->id, 'status' => 'consumed']);

        $this->expectException(ConflictHttpException::class);
        DB::transaction(fn () => $approvalService->consume(
            $second->token,
            $this->owner->fresh(),
            'requester-session',
            'website-main',
            $outletId,
            'payment.refund',
            'fnb_command',
            'payment-42',
            $payloadHash,
        ));
    }

    public function test_reauth_proof_is_session_payload_authority_and_single_use_bound(): void
    {
        $outletId = $this->outlet();
        $payloadHash = hash('sha256', 'staff-assignment-payload');
        $reauth = app(FnbReauthService::class);
        $proof = $reauth->issue(
            $this->owner->fresh(),
            'reauth-session',
            '127.0.0.1',
            'website-main',
            $outletId,
            'staff.assign',
            'new',
            $payloadHash,
            'FnbPassword123!',
        );

        try {
            DB::transaction(fn () => $reauth->consume(
                $proof->token,
                $this->owner->fresh(),
                'other-session',
                'website-main',
                $outletId,
                'staff.assign',
                'new',
                $payloadHash,
            ));
            $this->fail('A re-auth proof must be bound to its issuing session.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        DB::transaction(fn () => $reauth->consume(
            $proof->token,
            $this->owner->fresh(),
            'reauth-session',
            'website-main',
            $outletId,
            'staff.assign',
            'new',
            $payloadHash,
        ));
        try {
            DB::transaction(fn () => $reauth->consume(
                $proof->token,
                $this->owner->fresh(),
                'reauth-session',
                'website-main',
                $outletId,
                'staff.assign',
                'new',
                $payloadHash,
            ));
            $this->fail('A consumed re-auth proof must not be reusable.');
        } catch (ConflictHttpException) {
            $this->addToAssertionCount(1);
        }

        $stale = $reauth->issue(
            $this->owner->fresh(),
            'reauth-session',
            '127.0.0.1',
            'website-main',
            $outletId,
            'staff.assign',
            'new',
            $payloadHash,
            'FnbPassword123!',
        );
        $this->owner->increment('auth_version');
        try {
            DB::transaction(fn () => $reauth->consume(
                $stale->token,
                $this->owner->fresh(),
                'reauth-session',
                'website-main',
                $outletId,
                'staff.assign',
                'new',
                $payloadHash,
            ));
            $this->fail('An auth-version change must revoke outstanding re-auth proofs.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_operational_state_is_drained_restored_and_disabled_in_lifecycle_transactions(): void
    {
        DB::table('fnb_site_settings')->insert([
            'website_key' => 'website-main',
            'is_active' => true,
            'operational_state' => 'active',
            'default_currency' => 'VND',
            'default_timezone' => 'Asia/Ho_Chi_Minh',
            'order_prefix' => 'ORD',
            'tax_mode' => 'inclusive',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coordinator = app(ModuleLifecycleCoordinator::class);
        $lease = $coordinator->begin('fnb-pos', 'disable', '0.1.0-dev.1', '0.1.0-dev.1', $this->owner->id);
        $states = app(FnbOperationalStateManager::class);
        $states->prepare($lease);
        $this->assertSame('draining', DB::table('fnb_site_settings')->value('operational_state'));
        $states->compensate($lease);
        $coordinator->fail($lease, new \RuntimeException('pre-disable failure'), false);
        $this->assertSame('active', DB::table('fnb_site_settings')->value('operational_state'));

        app(ModuleManager::class)->disable('fnb-pos');
        $this->assertSame('disabled', DB::table('fnb_site_settings')->value('operational_state'));
        $this->assertSame('disabled', DB::table('module_installations')->where('key', 'fnb-pos')->value('status'));
        $this->assertFalse(Permission::query()->where('module_key', 'fnb-pos')->where('is_active', true)->exists());

        app(ModuleManager::class)->enable('fnb-pos');
        $this->assertSame('active', DB::table('fnb_site_settings')->value('operational_state'));
        $this->assertSame(53, Permission::query()->where('module_key', 'fnb-pos')->where('is_active', true)->count());
        $this->assertFalse(ModuleLifecycleOperation::query()->where('module_key', 'fnb-pos')->where('active_slot', 'active')->exists());

        $commitWindow = $coordinator->begin('fnb-pos', 'disable', '0.1.0-dev.1', '0.1.0-dev.1', $this->owner->id);
        $states->prepare($commitWindow);
        DB::transaction(function () use ($coordinator, $states, $commitWindow): void {
            Permission::query()->where('module_key', 'fnb-pos')->update(['is_active' => false]);
            $states->commit($commitWindow);
            $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->lockForUpdate()->firstOrFail();
            $installation->forceFill(['status' => 'disabled', 'enabled_at' => null])->save();
            $coordinator->markStateCommitted($commitWindow, [
                'installation_id' => (int) $installation->id,
                'status' => 'disabled',
                'version' => (string) $installation->version,
            ]);
        });
        $coordinator->fail($commitWindow, new \RuntimeException('simulated crash after state commit'), true);
        app(ModuleManager::class)->resume($commitWindow->operationId, $this->owner->id);
        $this->assertSame('disabled', DB::table('fnb_site_settings')->value('operational_state'));
        $this->assertSame('completed', ModuleLifecycleOperation::query()
            ->where('operation_id', $commitWindow->operationId)->value('status'));

        app(ModuleManager::class)->enable('fnb-pos');
        $this->assertSame('active', DB::table('fnb_site_settings')->value('operational_state'));

        $method = new \ReflectionMethod(ModuleLifecycleCoordinator::class, 'advisoryLockName');
        $this->assertLessThanOrEqual(64, strlen($method->invoke($coordinator, 'fnb-pos')));
    }

    private function outlet(): int
    {
        DB::table('fnb_site_settings')->insertOrIgnore([
            'website_key' => 'website-main',
            'is_active' => true,
            'operational_state' => 'active',
            'default_currency' => 'VND',
            'default_timezone' => 'Asia/Ho_Chi_Minh',
            'order_prefix' => 'ORD',
            'tax_mode' => 'inclusive',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('fnb_outlets')->insertGetId([
            'website_key' => 'website-main',
            'public_id' => (string) Str::uuid(),
            'code' => 'OUTLET-'.DB::table('fnb_outlets')->count(),
            'name' => 'Security Test Outlet',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'status' => 'active',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function terminal(int $outletId, string $code): int
    {
        return (int) DB::table('fnb_terminals')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $outletId,
            'public_id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $code,
            'type' => 'pos',
            'status' => 'active',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
