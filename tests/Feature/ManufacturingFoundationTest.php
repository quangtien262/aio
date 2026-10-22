<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Totp;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Commands\ManufacturingCommandService;
use Modules\Manufacturing\Services\Commands\ManufacturingOutboxService;
use Modules\Manufacturing\Services\Documents\ManufacturingDocumentService;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Manufacturing\Services\Read\ManufacturingReadService;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;
use Modules\Manufacturing\Services\Setup\ManufacturingSetupService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManufacturingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private object $company;

    private object $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = Admin::factory()->create(['must_change_password' => false]);
        foreach (['merchandise', 'inventory', 'manufacturing'] as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
        $this->owner = $this->owner->fresh();
        $this->company = DB::table('mfg_companies')->find(DB::table('mfg_companies')->insertGetId(['public_id' => (string) Str::uuid(), 'code' => 'TEST', 'name' => 'Test Enterprise', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]));
        $this->factory = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'A', 'name' => 'Factory A', 'status' => 'active']);
    }

    public function test_native_grant_and_global_permission_are_both_required(): void
    {
        $actor = $this->operator('global');
        DB::table('sites')->update(['status' => 'inactive']);
        $access = app(ManufacturingAccessService::class);
        $this->assertSame($this->factory->id, $access->factory($actor, $this->factory->public_id, 'manufacturing.execute')->id);
        $other = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'B', 'name' => 'Factory B', 'status' => 'active']);
        $this->assertDenied(fn () => $access->factory($actor, $other->public_id), 403);
        $websiteActor = $this->operator('website');
        $this->assertDenied(fn () => $access->factory($websiteActor, $this->factory->public_id, 'manufacturing.execute'), 403);
        $this->assertFalse($actor->canAccess('cms.post.update', 'global'));
        $this->assertFalse($actor->canAccess('accounting.view', 'organization', '1'));
    }

    public function test_bootstrap_has_no_website_dependency_and_masks_other_factory(): void
    {
        $actor = $this->operator('global');
        DB::table('sites')->update(['status' => 'inactive']);
        $bootstrap = app(ManufacturingAccessService::class)->bootstrap($actor, $this->factory->public_id);
        $this->assertSame(0, DB::table('sites')->where('status', 'active')->count());
        $this->assertNull($bootstrap['current_website']);
        $this->assertCount(1, $bootstrap['factories']);
        $this->assertSame($this->factory->public_id, $bootstrap['factories'][0]['id']);
        $this->assertContains('manufacturing.execute', $bootstrap['permissions']);
        $this->assertNotContains('manufacturing.settings', $bootstrap['permissions']);
    }

    public function test_readiness_guidance_only_exposes_app_store_to_global_store_viewers(): void
    {
        $this->actingAs($this->owner, 'admin')->getJson('/admin/api/manufacturing/bootstrap')
            ->assertOk()->assertJsonPath('data.readiness.can_open_app_store', true)->assertJsonPath('data.timezones', timezone_identifiers_list())
            ->assertJsonFragment(['code' => 'purchasing', 'message' => 'Cần cài và bật Mua hàng và nhà cung cấp để vận hành mua nguyên liệu FOB.']);

        $actor = $this->operator('global');
        DB::table('sites')->update(['status' => 'inactive']);
        $this->actingAs($actor, 'admin')->getJson('/admin/api/manufacturing/bootstrap?factory_id='.$this->factory->public_id)
            ->assertOk()->assertJsonPath('data.readiness.can_open_app_store', false)
            ->assertJsonPath('data.current_website', null);
    }

    public function test_settings_journal_returns_creator_name_without_expanding_actor_or_factory_scope(): void
    {
        $this->actingAs($this->owner, 'admin')->postJson('/admin/api/manufacturing/commands/workshop.create',
            ['factory_id' => $this->factory->public_id, 'data' => ['name' => 'Workshop with creator']],
            ['Idempotency-Key' => 'creator-workshop-test'])->assertOk();
        $entry = (array) DB::table('mfg_commands')->first();
        unset($entry['id']);
        $otherActor = Admin::factory()->create(['name' => 'Other creator', 'must_change_password' => false]);
        $otherFactory = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'OTHER', 'name' => 'Other factory', 'status' => 'active']);
        DB::table('mfg_commands')->insert(array_replace($entry, ['public_id' => (string) Str::uuid(), 'actor_id' => $otherActor->id, 'command_key' => 'other-creator-test']));
        DB::table('mfg_commands')->insert(array_replace($entry, ['public_id' => (string) Str::uuid(), 'factory_id' => $otherFactory->id, 'command_key' => 'other-factory-test']));

        $result = app(ManufacturingReadService::class)->read($this->owner, 'settings', $this->factory->public_id);
        $this->assertCount(1, $result['operations']['commands']);
        $this->assertSame($this->owner->id, $result['operations']['commands'][0]['actor_id']);
        $this->assertSame($this->owner->name, $result['operations']['commands'][0]['actor_name']);
        $this->assertArrayNotHasKey('email', $result['operations']['commands'][0]);
    }

    public function test_command_is_atomic_replays_and_rechecks_membership(): void
    {
        $actor = $this->operator('global');
        $commands = app(ManufacturingCommandService::class);
        $authorize = fn (Admin $live) => ['factory' => app(ManufacturingAccessService::class)->factory($live, $this->factory->public_id, 'manufacturing.execute'), 'permission' => 'manufacturing.execute'];
        $mutate = function () use ($commands): array {
            $row = D::insert('mfg_styles', ['company_id' => $this->company->id, 'code' => 'ONCE', 'name' => 'Private technical name']);
            $commands->emit(['factory' => $this->factory], 'style.created', 'style', $row->public_id, 1);

            return ['public_id' => $row->public_id, 'status' => $row->status, 'version' => 1, 'name' => $row->name];
        };
        $first = $commands->run($actor, 'test.create', ['quantity' => '1'], 'production-create-0001', $authorize, $mutate);
        $again = $commands->run($actor, 'test.create', ['quantity' => '1'], 'production-create-0001', $authorize, $mutate);
        $this->assertSame($first['public_id'], $again['public_id']);
        $this->assertTrue($again['replayed']);
        $this->assertSame(1, DB::table('mfg_styles')->count());
        $this->assertSame(1, DB::table('mfg_outbox')->count());
        $this->assertStringNotContainsString('Private technical name', DB::table('mfg_commands')->first()->result_ref);
        $this->assertDenied(fn () => $commands->run($actor, 'test.create', ['quantity' => '2'], 'production-create-0001', $authorize, $mutate), 409);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['status' => 'inactive']);
        $this->assertDenied(fn () => $commands->run($actor, 'test.create', ['quantity' => '1'], 'production-create-0001', $authorize, $mutate), 403);
        $this->assertDenied(fn () => $commands->status($actor, 'production-create-0001'), 403);
    }

    public function test_failed_command_rolls_back_business_outbox_and_journal(): void
    {
        $commands = app(ManufacturingCommandService::class);
        try {
            $commands->run($this->owner, 'test.failure', [], 'rollback-command-0001', fn () => ['factory' => $this->factory], function () use ($commands): array {
                D::insert('mfg_styles', ['company_id' => $this->company->id, 'code' => 'FAIL', 'name' => 'Will roll back']);
                $commands->emit(['factory' => $this->factory], 'test.failure', 'factory', $this->factory->public_id, 1);
                throw new \RuntimeException('Injected failure');
            });
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected failure', $e->getMessage());
        }
        $this->assertSame(0, DB::table('mfg_styles')->count());
        $this->assertSame(0, DB::table('mfg_outbox')->count());
        $this->assertSame(0, DB::table('mfg_commands')->count());
    }

    public function test_pause_allows_native_reads_blocks_writes_and_resume_works(): void
    {
        app(ManufacturingRuntimeState::class)->setPaused($this->owner, true, 'Maintenance');
        $this->assertTrue(app(ManufacturingAccessService::class)->bootstrap($this->owner)['runtime']['paused']);
        $this->assertDenied(fn () => DB::transaction(fn () => app(ManufacturingRuntimeState::class)->guard(true)), 423);
        app(ManufacturingRuntimeState::class)->setPaused($this->owner, false);
        DB::transaction(fn () => app(ManufacturingRuntimeState::class)->guard(true));
        $this->assertFalse(app(ManufacturingRuntimeState::class)->state()['paused']);
    }

    public function test_lifecycle_preserves_open_wip_and_disabled_gate(): void
    {
        $order = D::insert('mfg_orders', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'OPEN', 'status' => 'released']);
        D::insert('mfg_bundles', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'code' => 'WIP', 'quantity' => '10', 'status' => 'in_progress']);
        app(ModuleManager::class)->disable('manufacturing');
        $this->actingAs($this->owner, 'admin')->putJson('/admin/api/manufacturing/me/password', [])->assertNotFound();
        $this->assertDatabaseHas('mfg_bundles', ['code' => 'WIP', 'quantity' => '10']);
        app(ModuleManager::class)->enable('manufacturing');
        $this->assertSame('active', app(ManufacturingRuntimeState::class)->state()['status']);
        $this->assertDatabaseHas('mfg_orders', ['code' => 'OPEN', 'status' => 'released']);
    }

    public function test_native_password_alias_enforces_current_password_and_session_revocation(): void
    {
        $actor = $this->operator('global');
        $actor->forceFill(['must_change_password' => true, 'password' => 'OldPassword123!'])->save();
        $this->actingAs($actor, 'admin')->withSession(['admin_auth_version' => $actor->auth_version]);
        $this->putJson('/admin/api/manufacturing/me/password', ['current_password' => 'wrong', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertUnprocessable();
        $this->putJson('/admin/api/manufacturing/me/password', ['current_password' => 'OldPassword123!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
        $this->assertFalse($actor->fresh()->must_change_password);
        $oldVersion = $actor->fresh()->auth_version;
        $actor->fresh()->increment('auth_version');
        $this->actingAs($actor->fresh(), 'admin')->withSession(['admin_auth_version' => $oldVersion]);
        $this->putJson('/admin/api/manufacturing/me/password', [])->assertUnauthorized();
    }

    public function test_setup_creates_native_company_catalog_factory_and_production_warehouse(): void
    {
        $setup = app(ManufacturingSetupService::class);
        DB::transaction(function () use ($setup): void {
            $company = $setup->dispatch($this->owner, 'company.create', [], ['code' => 'FOB', 'name' => 'FOB Company']);
            $row = DB::table('mfg_companies')->where('public_id', $company['public_id'])->first();
            $factory = $setup->dispatch($this->owner, 'factory.create', ['company' => $row], ['code' => 'FACTORY', 'name' => 'FOB Factory']);
            $this->assertDatabaseHas('inv_warehouses', ['id' => $factory['warehouse_id'], 'production_managed' => true, 'legal_entity_ref' => 'mfg-company:'.$row->public_id]);
            $this->assertDatabaseHas('mfg_memberships', ['company_id' => $row->id, 'admin_id' => $this->owner->id, 'role' => 'manager']);
        });
    }

    public function test_company_update_preserves_links_replays_and_rejects_stale_or_invalid_data(): void
    {
        $setup = app(ManufacturingSetupService::class);
        [$company, $factory] = DB::transaction(function () use ($setup): array {
            $company = $setup->dispatch($this->owner, 'company.create', [], ['code' => 'EDIT', 'name' => 'Original company']);
            $row = DB::table('mfg_companies')->where('public_id', $company['id'])->first();

            return [$company, $setup->dispatch($this->owner, 'factory.create', ['company' => $row], ['code' => 'EDIT-F', 'name' => 'Factory'])];
        });
        $catalogId = $company['settings']['catalog_id'];
        $catalogBefore = (array) DB::table('merch_catalogs')->find($catalogId);
        $warehouseBefore = (array) DB::table('inv_warehouses')->find($factory['warehouse_id']);
        $this->actingAs($this->owner, 'admin');
        $payload = ['factory_id' => $factory['id'], 'resource' => $company['id'], 'data' => [
            'code' => 'EDIT-NEW', 'name' => 'Updated company', 'timezone' => 'Asia/Bangkok', 'expected_version' => 1,
            'settings' => ['catalog_id' => 999999], 'status' => 'inactive',
        ]];
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'update-company-once'])
            ->assertOk()->assertJsonPath('data.name', 'Updated company')->assertJsonPath('data.code', 'EDIT-NEW')->assertJsonPath('data.version', 2);
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'update-company-once'])
            ->assertOk()->assertJsonPath('data.replayed', true)->assertJsonPath('data.version', 2);
        $row = DB::table('mfg_companies')->where('public_id', $company['id'])->first();
        $this->assertSame($company['settings'], D::json($row->settings));
        $this->assertSame('active', $row->status);
        $this->assertSame('Asia/Bangkok', $row->timezone);
        $this->assertSame($catalogBefore, (array) DB::table('merch_catalogs')->find($catalogId));
        $this->assertSame($warehouseBefore, (array) DB::table('inv_warehouses')->find($factory['warehouse_id']));
        $this->assertDatabaseHas('mfg_factories', ['public_id' => $factory['id'], 'company_id' => $row->id, 'timezone' => 'Asia/Ho_Chi_Minh']);
        $this->assertSame(1, DB::table('mfg_outbox')->where('event_type', 'manufacturing.company.update')->count());
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'update-company-stale'])->assertConflict();
        $payload['data']['expected_version'] = 2;
        $payload['data']['code'] = $this->company->code;
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'update-company-duplicate'])->assertUnprocessable();
        $payload['data']['code'] = 'EDIT-NEW';
        $payload['data']['timezone'] = 'invalid-timezone';
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'update-company-invalid'])->assertUnprocessable();
        $this->assertDatabaseHas('mfg_companies', ['id' => $row->id, 'version' => 2]);
    }

    public function test_company_update_requires_company_wide_grant_and_rechecks_it_on_replay(): void
    {
        $actor = $this->operator('global');
        $role = Role::query()->where('key', 'manufacturing.manager')->firstOrFail();
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['role' => 'manager']);
        $this->actingAs($actor->fresh(), 'admin');
        $payload = ['resource' => $this->company->public_id, 'data' => ['code' => $this->company->code, 'name' => 'Company manager edit', 'expected_version' => 1]];
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.companies.0.can_manage', false);
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'factory-manager-denied'])->assertForbidden();
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['factory_id' => null]);
        $this->actingAs($actor->fresh(), 'admin');
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.companies.0.can_manage', true);
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'company-manager-allowed'])->assertOk()->assertJsonPath('data.version', 2);
        $other = DB::table('mfg_companies')->insertGetId(['public_id' => (string) Str::uuid(), 'code' => 'OTHER-EDIT', 'name' => 'Other', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $foreign = $payload;
        $foreign['resource'] = DB::table('mfg_companies')->where('id', $other)->value('public_id');
        $foreign['data']['company_id'] = $this->company->public_id;
        $this->postJson('/admin/api/manufacturing/commands/company.update', $foreign, ['Idempotency-Key' => 'company-manager-foreign'])->assertForbidden();
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['status' => 'inactive']);
        $this->postJson('/admin/api/manufacturing/commands/company.update', $payload, ['Idempotency-Key' => 'company-manager-allowed'])->assertForbidden();
    }

    public function test_factory_commands_generate_optional_codes_and_replay_without_extra_factories_or_warehouses(): void
    {
        $setup = app(ManufacturingSetupService::class);
        $company = DB::transaction(fn () => $setup->dispatch($this->owner, 'company.create', [], ['code' => 'AUTO', 'name' => 'Automatic factory codes']));
        $companyId = DB::table('mfg_companies')->where('public_id', $company['id'])->value('id');
        $this->actingAs($this->owner, 'admin');
        $create = fn (array $data, string $key) => $this->postJson('/admin/api/manufacturing/commands/factory.create',
            ['data' => ['company_id' => $company['id'], 'name' => 'Factory '.$key] + $data], ['Idempotency-Key' => $key]);

        $create(['code' => 'NM000002'], 'manual-factory-0001')->assertOk()->assertJsonPath('data.code', 'NM000002');
        $codes = [];
        foreach ([[], ['code' => null], ['code' => ''], ['code' => '   ']] as $index => $input) {
            $key = 'automatic-factory-000'.$index;
            $response = $create($input, $key)->assertOk();
            $code = $response->json('data.code');
            $this->assertMatchesRegularExpression('/^NM[0-9]{6,}$/', $code);
            $this->assertNotSame('NM000002', $code);
            $codes[] = $code;
            $create($input, $key)->assertOk()->assertJsonPath('data.code', $code)->assertJsonPath('data.replayed', true);
            $factory = DB::table('mfg_factories')->where('public_id', $response->json('data.id'))->first();
            $this->assertDatabaseHas('inv_warehouses', ['id' => $factory->warehouse_id, 'production_managed' => true, 'code' => 'MFG-'.$companyId.'-'.$code]);
        }
        $this->assertCount(4, array_unique($codes));
        $this->assertSame(5, DB::table('mfg_factories')->where('company_id', $companyId)->count());
        $this->assertSame(5, DB::table('inv_warehouses')->where('legal_entity_ref', 'mfg-company:'.$company['id'])->count());
        $create(['code' => 'NM000002'], 'duplicate-factory-0001')->assertUnprocessable();
        $create(['code' => 'invalid code!'], 'invalid-factory-0001')->assertUnprocessable();
        $create(['code' => []], 'invalid-factory-0002')->assertUnprocessable();
        $this->assertSame(5, DB::table('mfg_factories')->where('company_id', $companyId)->count());
        $create(['code' => '0'], 'manual-factory-zero')->assertOk()->assertJsonPath('data.code', '0');
    }

    public function test_workshop_codes_are_optional_unique_per_factory_and_stable_on_replay(): void
    {
        $this->actingAs($this->owner, 'admin');
        $create = fn (array $data, string $key, ?string $factory = null) => $this->postJson('/admin/api/manufacturing/commands/workshop.create',
            ['factory_id' => $factory ?? $this->factory->public_id, 'data' => ['name' => 'Workshop '.$key] + $data], ['Idempotency-Key' => $key]);
        $create(['code' => 'PX000002'], 'manual-workshop-0001')->assertOk()->assertJsonPath('data.code', 'PX000002');
        $codes = [];
        foreach ([[], ['code' => null], ['code' => ''], ['code' => '   ']] as $index => $input) {
            $key = 'automatic-workshop-000'.$index;
            $response = $create($input, $key)->assertOk();
            $code = $response->json('data.code');
            $this->assertMatchesRegularExpression('/^PX[0-9]{6,}$/', $code);
            $this->assertNotSame('PX000002', $code);
            $codes[] = $code;
            $create($input, $key)->assertOk()->assertJsonPath('data.code', $code)->assertJsonPath('data.replayed', true);
        }
        $this->assertCount(4, array_unique($codes));
        $this->assertSame(5, DB::table('mfg_workshops')->where('factory_id', $this->factory->id)->count());
        $create(['code' => 'PX000002'], 'duplicate-workshop-0001')->assertUnprocessable();
        $create(['code' => 'invalid code!'], 'invalid-workshop-0001')->assertUnprocessable();
        $create(['code' => []], 'invalid-workshop-0002')->assertUnprocessable();
        $create(['code' => '0'], 'manual-workshop-zero')->assertOk()->assertJsonPath('data.code', '0');
        $other = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'PX-OTHER', 'name' => 'Other factory', 'status' => 'active']);
        $create([], 'other-factory-workshop', $other->public_id)->assertOk()->assertJsonPath('data.code', 'PX000001');
        $actor = $this->operator('global');
        $this->actingAs($actor, 'admin');
        $create([], 'operator-workshop-denied')->assertForbidden();
        $this->assertSame(7, DB::table('mfg_workshops')->count());
    }

    public function test_line_codes_are_optional_unique_per_factory_and_stable_on_replay(): void
    {
        $this->actingAs($this->owner, 'admin');
        $create = fn (array $data, string $key, ?string $factory = null) => $this->postJson('/admin/api/manufacturing/commands/line.create',
            ['factory_id' => $factory ?? $this->factory->public_id, 'data' => ['name' => 'Line '.$key] + $data], ['Idempotency-Key' => $key]);
        $create(['code' => 'CM000002'], 'manual-line-0001')->assertOk()->assertJsonPath('data.code', 'CM000002');
        $codes = [];
        foreach ([[], ['code' => null], ['code' => ''], ['code' => '   ']] as $index => $input) {
            $key = 'automatic-line-000'.$index;
            $response = $create($input, $key)->assertOk();
            $code = $response->json('data.code');
            $this->assertMatchesRegularExpression('/^CM[0-9]{6,}$/', $code);
            $this->assertNotSame('CM000002', $code);
            $codes[] = $code;
            $create($input, $key)->assertOk()->assertJsonPath('data.code', $code)->assertJsonPath('data.replayed', true);
        }
        $this->assertCount(4, array_unique($codes));
        $this->assertSame(5, DB::table('mfg_lines')->where('factory_id', $this->factory->id)->count());
        $create(['code' => 'CM000002'], 'duplicate-line-0001')->assertUnprocessable();
        $create(['code' => 'invalid code!'], 'invalid-line-0001')->assertUnprocessable();
        $create(['code' => []], 'invalid-line-0002')->assertUnprocessable();
        $create(['code' => '0'], 'manual-line-zero')->assertOk()->assertJsonPath('data.code', '0');
        $other = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'CM-OTHER', 'name' => 'Other factory', 'status' => 'active']);
        $create([], 'other-factory-line', $other->public_id)->assertOk()->assertJsonPath('data.code', 'CM000001');
        $actor = $this->operator('global');
        $this->actingAs($actor, 'admin');
        $create([], 'operator-line-denied')->assertForbidden();
        $this->assertSame(7, DB::table('mfg_lines')->count());
    }

    public function test_paginated_reads_scope_parent_and_serialize_uuid_links(): void
    {
        $actor = $this->operator('global');
        $other = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'B', 'name' => 'Other', 'status' => 'active']);
        $demand = D::insert('mfg_demands', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'OWN']);
        D::insert('mfg_demand_lines', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'demand_id' => $demand->id, 'quantity' => '2.000000']);
        D::insert('mfg_demands', ['company_id' => $this->company->id, 'factory_id' => $other->id, 'code' => 'FOREIGN']);
        $read = app(ManufacturingReadService::class)->read($actor, 'demands', $this->factory->public_id, ['per_page' => 1]);
        $this->assertSame(1, $read['total']);
        $this->assertSame($demand->public_id, $read['items'][0]['id']);
        $this->assertSame($demand->public_id, $read['items'][0]['lines'][0]['demand_id']);
        $this->assertSame('2.000000', $read['items'][0]['quantity']);
        $this->assertStringNotContainsString('FOREIGN', json_encode($read));
    }

    public function test_read_masks_nested_costs_and_guards_costing(): void
    {
        $actor = $this->operator('global');
        $order = D::insert('mfg_orders', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'COST']);
        D::insert('mfg_bundles', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'code' => 'PRIVATECOST', 'quantity' => '1', 'metadata' => D::encode(['cost_snapshot' => ['unit_cost' => '123.456789'], 'financials' => ['payable_minor' => 123], 'notes' => 'visible'])]);
        $service = app(ManufacturingReadService::class);
        $read = $service->read($actor, 'bundles', $this->factory->public_id);
        $this->assertArrayNotHasKey('cost_snapshot', $read['items'][0]['metadata']);
        $this->assertArrayNotHasKey('financials', $read['items'][0]['metadata']);
        $this->assertSame('visible', $read['items'][0]['notes']);
        $this->assertDenied(fn () => $service->read($actor, 'costing', $this->factory->public_id), 403);
    }

    public function test_http_native_bootstrap_preserves_password_gate_without_website_context(): void
    {
        DB::table('sites')->update(['status' => 'inactive']);
        $actor = $this->operator('global');
        $actor->forceFill(['must_change_password' => true])->save();
        $this->actingAs($actor->fresh(), 'admin')->withSession(['admin_auth_version' => $actor->fresh()->auth_version]);
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.must_change_password', true)->assertJsonPath('data.factories', []);
        $this->getJson('/admin/api/manufacturing/orders?factory_id='.$this->factory->public_id)->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $actor->forceFill(['must_change_password' => false])->save();
        $this->actingAs($actor->fresh(), 'admin')->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.current_website', null);
        $this->getJson('/admin/api/admins')->assertNotFound();
    }

    public function test_native_shell_requires_global_and_native_view_without_website_access(): void
    {
        $this->withoutVite();
        $actor = $this->operator('global');
        $this->actingAs($actor, 'admin')->withSession(['admin_auth_version' => $actor->auth_version]);
        // Core global assignments retain their existing website-context access;
        // they do not grant any CMS data permissions to a production operator.
        $this->getJson('/admin/api/me')->assertOk();
        $this->getJson('/admin/api/cms/posts')->assertForbidden();
        DB::table('sites')->update(['status' => 'inactive']);
        $this->get('/admin/modules/manufacturing/materials')->assertOk()->assertViewIs('admin')->assertViewHas('adminDocumentTitle', 'AIO Sản xuất Admin');
        $this->getJson('/admin/api/me')->assertForbidden();
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.workspace_kind', 'enterprise');
        app(ManufacturingRuntimeState::class)->setPaused($this->owner, true);
        $this->get('/admin/modules/manufacturing/overview')->assertOk();
        app(ManufacturingRuntimeState::class)->setPaused($this->owner, false);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['status' => 'inactive']);
        $this->get('/admin/modules/manufacturing')->assertForbidden();
        $actor->forceFill(['must_change_password' => true])->save();
        $this->actingAs($actor->fresh(), 'admin');
        $this->get('/admin/modules/manufacturing/overview')->assertOk()->assertViewHas('adminDocumentTitle', 'AIO Sản xuất Admin');
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.must_change_password', true)->assertJsonPath('data.navigation_permissions', [])->assertJsonPath('data.module_navigation', []);
        $this->getJson('/admin/api/manufacturing/orders?factory_id='.$this->factory->public_id)->assertForbidden();
        app(ModuleManager::class)->disable('manufacturing');
        $this->get('/admin/modules/manufacturing/overview')->assertNotFound();
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertNotFound();
    }

    public function test_native_account_aliases_keep_password_two_factor_and_session_guards(): void
    {
        $actor = $this->operator('global');
        $actor->forceFill(['password' => 'CurrentPassword123!'])->save();
        $this->actingAs($actor->fresh(), 'admin')->withSession(['admin_auth_version' => $actor->fresh()->auth_version]);
        $this->postJson('/admin/api/manufacturing/me/two-factor/setup', ['current_password' => 'wrong'])->assertUnprocessable();
        $secret = $this->postJson('/admin/api/manufacturing/me/two-factor/setup', ['current_password' => 'CurrentPassword123!'])->assertOk()->json('data.secret');
        $code = (new \ReflectionMethod(Totp::class, 'codeAt'))->invoke(app(Totp::class), $secret, (int) floor(time() / 30));
        $before = $actor->fresh()->auth_version;
        $recovery = $this->postJson('/admin/api/manufacturing/me/two-factor/confirm', ['code' => $code])->assertOk()->json('data.recovery_codes.0');
        $this->assertSame($before + 1, $actor->fresh()->auth_version);
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.two_factor_enabled', true);
        $this->deleteJson('/admin/api/manufacturing/me/two-factor', ['current_password' => 'CurrentPassword123!', 'code' => $recovery])->assertOk();
        $this->assertNull($actor->fresh()->two_factor_confirmed_at);
        $actor->forceFill(['must_change_password' => true])->save();
        $this->actingAs($actor->fresh(), 'admin')->withSession(['admin_auth_version' => $actor->fresh()->auth_version]);
        $this->postJson('/admin/api/manufacturing/me/two-factor/setup', ['current_password' => 'CurrentPassword123!'])->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->post('/admin/api/manufacturing/logout')->assertRedirect();
        $this->assertGuest('admin');
    }

    public function test_native_login_works_without_sites_and_expired_sessions_return_to_native_entry(): void
    {
        $this->withoutVite();
        DB::table('sites')->update(['status' => 'inactive']);
        $actor = $this->operator('global');
        $actor->forceFill(['password' => 'NativePassword123!', 'must_change_password' => true])->save();
        $this->get('/admin/modules/manufacturing/materials')->assertRedirect('/admin/manufacturing/login');
        $this->get('/admin/manufacturing/login')->assertOk()->assertSee('name="login"', false)->assertSee('name="password"', false)->assertSee('name="two_factor_code"', false)->assertSee('name="_token"', false);
        $this->withSession(['url.intended' => 'https://outside.example/'])->post('/admin/manufacturing/login', ['login' => $actor->username, 'password' => 'NativePassword123!'])
            ->assertRedirect('/admin/modules/manufacturing');
        $this->assertAuthenticatedAs($actor, 'admin');
        $this->assertSame($actor->fresh()->auth_version, session('admin_auth_version'));
        $this->get('/admin/modules/manufacturing/overview')->assertOk();
        $this->getJson('/admin/api/manufacturing/bootstrap')->assertOk()->assertJsonPath('data.must_change_password', true);
        $version = $actor->fresh()->auth_version;
        $actor->fresh()->increment('auth_version');
        $this->actingAs($actor->fresh(), 'admin')->withSession(['admin_auth_version' => $version]);
        $this->get('/admin/modules/manufacturing/overview')->assertRedirect('/admin/manufacturing/login');
        $this->assertGuest('admin');
    }

    public function test_native_login_rejects_invalid_locked_customer_and_non_native_accounts(): void
    {
        DB::table('sites')->update(['status' => 'inactive']);
        $actor = $this->operator('global');
        $actor->forceFill(['password' => 'NativePassword123!'])->save();
        $this->postJson('/admin/manufacturing/login', ['login' => $actor->username, 'password' => 'wrong'])->assertUnprocessable();
        $this->assertGuest('admin');
        $actor->forceFill(['locked_at' => now()])->save();
        $this->postJson('/admin/manufacturing/login', ['login' => $actor->username, 'password' => 'NativePassword123!'])->assertUnprocessable();
        $this->assertGuest('admin');
        $nonNative = Admin::factory()->create(['must_change_password' => false, 'password' => 'NativePassword123!']);
        $this->postJson('/admin/manufacturing/login', ['login' => $nonNative->username, 'password' => 'NativePassword123!'])->assertUnprocessable();
        $this->assertGuest('admin');
        $customer = Customer::factory()->create(['password' => 'CustomerPassword123!']);
        $this->postJson('/admin/manufacturing/login', ['login' => $customer->email, 'password' => 'CustomerPassword123!'])->assertUnprocessable();
        $this->assertGuest('customer');
        $this->assertGuest('admin');
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'auth.manufacturing.rejected')->count());
        app(ModuleManager::class)->disable('manufacturing');
        $this->get('/admin/manufacturing/login')->assertNotFound();
        $this->postJson('/admin/manufacturing/login', ['login' => $actor->username, 'password' => 'NativePassword123!'])->assertNotFound();
    }

    public function test_native_login_reuses_core_two_factor_and_consumes_recovery_code_once(): void
    {
        DB::table('sites')->update(['status' => 'inactive']);
        $actor = $this->operator('global');
        $secret = app(Totp::class)->generateSecret();
        $actor->forceFill(['password' => 'NativePassword123!', 'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('native-recovery-code')]])->save();
        $credentials = ['login' => $actor->username, 'password' => 'NativePassword123!'];
        $this->postJson('/admin/manufacturing/login', $credentials)->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
        $this->postJson('/admin/manufacturing/login', $credentials + ['two_factor_code' => 'invalid-code'])->assertUnprocessable();
        $this->assertGuest('admin');
        $this->postJson('/admin/manufacturing/login', $credentials + ['two_factor_code' => 'native-recovery-code'])->assertOk()->assertJsonPath('data.guard', 'admin')->assertJsonPath('data.redirect_to', url('/admin/modules/manufacturing'));
        $this->assertSame([], $actor->fresh()->two_factor_recovery_codes);
        $this->post('/admin/api/manufacturing/logout')->assertRedirect();
        $this->postJson('/admin/manufacturing/login', $credentials + ['two_factor_code' => 'native-recovery-code'])->assertUnprocessable();
        $this->assertGuest('admin');
        $code = (new \ReflectionMethod(Totp::class, 'codeAt'))->invoke(app(Totp::class), $secret, (int) floor(time() / 30));
        $this->postJson('/admin/manufacturing/login', $credentials + ['two_factor_code' => $code])->assertOk();
        $this->assertAuthenticatedAs($actor, 'admin');
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'auth.admin.two_factor_failed')->count());
    }

    public function test_navigation_unions_native_grants_without_changing_selected_factory_permissions(): void
    {
        $actor = $this->operator('global');
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => Role::query()->where('key', 'manufacturing.manager')->value('id'), 'scope_type' => 'global', 'scope_value' => null]);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['permissions' => D::encode(['manufacturing.view'])]);
        $second = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'NAV-B', 'name' => 'Factory B', 'status' => 'active']);
        D::insert('mfg_memberships', ['company_id' => $this->company->id, 'factory_id' => $second->id, 'admin_id' => $actor->id, 'role' => 'manager', 'permissions' => D::encode(['manufacturing.view', 'manufacturing.cost.view']), 'status' => 'active']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = app(ManufacturingAccessService::class)->bootstrap($actor->fresh(), $this->factory->public_id);
        $smallQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertNotContains('manufacturing.cost.view', $first['permissions']);
        $this->assertContains('manufacturing.cost.view', $first['navigation_permissions']);
        $this->assertStringContainsString('costing', json_encode($first['module_navigation']));
        for ($i = 0; $i < 8; $i++) {
            $factory = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'NAV-'.$i, 'name' => 'Factory NAV '.$i, 'status' => 'active']);
            D::insert('mfg_memberships', ['company_id' => $this->company->id, 'factory_id' => $factory->id, 'admin_id' => $actor->id, 'role' => 'reader', 'status' => 'active']);
        }
        DB::flushQueryLog();
        $many = app(ManufacturingAccessService::class)->bootstrap($actor->fresh(), $this->factory->public_id);
        $largeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertContains('manufacturing.cost.view', $many['navigation_permissions']);
        $this->assertLessThanOrEqual($smallQueries + 2, $largeQueries, 'Navigation added per-factory authorization queries.');
        $selected = app(ManufacturingAccessService::class)->bootstrap($actor->fresh(), $second->public_id);
        $this->assertContains('manufacturing.cost.view', $selected['permissions']);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->where('factory_id', $second->id)->update(['status' => 'inactive']);
        $revoked = app(ManufacturingAccessService::class)->bootstrap($actor->fresh(), $this->factory->public_id);
        $this->assertNotContains('manufacturing.cost.view', $revoked['navigation_permissions']);
    }

    public function test_private_document_http_replay_authorization_and_checksum(): void
    {
        Storage::fake('manufacturing_private');
        $style = D::insert('mfg_styles', ['company_id' => $this->company->id, 'code' => 'PRIVATE-DOC', 'name' => 'Private']);
        $payload = ['factory_id' => $this->factory->public_id, 'resource_type' => 'style', 'resource_id' => $style->public_id,
            'file' => UploadedFile::fake()->createWithContent('tech.txt', 'Confidential sewing measurements')];
        $this->actingAs($this->owner, 'admin');
        $response = $this->post('/admin/api/manufacturing/documents', $payload, ['Idempotency-Key' => 'document-upload-0001', 'Accept' => 'application/json'])->assertOk();
        $id = $response->json('data.public_id');
        $this->assertArrayNotHasKey('path', $response->json('data'));
        $this->post('/admin/api/manufacturing/documents', $payload, ['Idempotency-Key' => 'document-upload-0001', 'Accept' => 'application/json'])->assertOk()->assertJsonPath('data.replayed', true)->assertJsonPath('data.public_id', $id);
        $this->assertSame(1, DB::table('mfg_documents')->count());
        $this->assertCount(1, Storage::disk('manufacturing_private')->allFiles());
        $listing = $this->getJson('/admin/api/manufacturing/documents?factory_id='.$this->factory->public_id.'&resource_type=style&resource_id='.$style->public_id)
            ->assertOk()->assertJsonPath('data.items.0.id', $id)->assertJsonPath('data.items.0.original_name', 'tech.txt');
        $this->assertArrayNotHasKey('path', $listing->json('data.items.0'));
        $this->get('/admin/api/manufacturing/documents/'.$id)->assertOk()->assertDownload('tech.txt')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/octet-stream');
        $other = D::insert('mfg_factories', ['company_id' => $this->company->id, 'code' => 'OTHER-DOC', 'name' => 'Other', 'status' => 'active']);
        $actor = $this->operator('global');
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['factory_id' => $other->id]);
        $this->actingAs($actor, 'admin')->getJson('/admin/api/manufacturing/documents/'.$id)->assertForbidden();
        $this->getJson('/admin/api/manufacturing/documents?factory_id='.$this->factory->public_id.'&resource_type=style&resource_id='.$style->public_id)->assertForbidden();
        $document = DB::table('mfg_documents')->where('public_id', $id)->first();
        Storage::disk('manufacturing_private')->put($document->path, 'tampered');
        $this->actingAs($this->owner, 'admin')->getJson('/admin/api/manufacturing/documents/'.$id)->assertConflict();
        $this->assertStringNotContainsString(public_path(), config('filesystems.disks.manufacturing_private.root'));
    }

    public function test_document_file_is_removed_on_database_rollback_and_html_is_rejected(): void
    {
        Storage::fake('manufacturing_private');
        $style = D::insert('mfg_styles', ['company_id' => $this->company->id, 'code' => 'ROLLBACK-DOC', 'name' => 'Private']);
        DB::beginTransaction();
        $document = app(ManufacturingDocumentService::class)->upload($this->owner, $this->factory->public_id, 'style', $style->public_id,
            UploadedFile::fake()->createWithContent('tech.txt', 'rollback me'), 'rollback-document-0001');
        $this->assertCount(1, Storage::disk('manufacturing_private')->allFiles());
        DB::rollBack();
        $this->assertSame(0, DB::table('mfg_documents')->where('public_id', $document['public_id'])->count());
        $this->assertCount(0, Storage::disk('manufacturing_private')->allFiles());
        $this->actingAs($this->owner, 'admin')->post('/admin/api/manufacturing/documents', ['factory_id' => $this->factory->public_id, 'resource_type' => 'style', 'resource_id' => $style->public_id,
            'file' => UploadedFile::fake()->createWithContent('evil.html', '<script>alert(1)</script>')], ['Idempotency-Key' => 'html-document-0001', 'Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_outbox_delivers_audit_once_blocks_unsupported_and_obeys_pause_disable(): void
    {
        $commands = app(ManufacturingCommandService::class);
        $event = DB::transaction(fn () => $commands->emit(['factory' => $this->factory], 'order.released', 'order', (string) Str::uuid(), 1));
        DB::transaction(fn () => $commands->emit(['factory' => $this->factory], 'accounting.pending', 'order', (string) Str::uuid(), 1, [], 'accounting-tax'));
        $worker = app(ManufacturingOutboxService::class);
        $this->assertSame(['sent' => 1, 'failed' => 0, 'blocked' => 1], $worker->dispatch());
        $this->assertDatabaseHas('mfg_inbox', ['consumer' => 'manufacturing.audit', 'event_id' => $event]);
        $this->assertDatabaseHas('mfg_outbox', ['destination' => 'accounting-tax', 'status' => 'blocked', 'attempts' => 0]);
        DB::table('mfg_outbox')->where('event_id', $event)->update(['status' => 'pending']);
        $this->assertSame(1, $worker->dispatch()['sent']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'manufacturing.event.delivered')->count());
        $pending = DB::transaction(fn () => $commands->emit(['factory' => $this->factory], 'order.changed', 'order', (string) Str::uuid(), 2));
        app(ManufacturingRuntimeState::class)->setPaused($this->owner, true);
        $this->assertSame(0, $worker->dispatch()['sent']);
        $this->assertDatabaseHas('mfg_outbox', ['event_id' => $pending, 'status' => 'pending', 'attempts' => 0]);
        app(ManufacturingRuntimeState::class)->setPaused($this->owner, false);
        app(ModuleManager::class)->disable('manufacturing');
        $this->assertSame(0, $worker->dispatch()['sent']);
        app(ModuleManager::class)->enable('manufacturing');
        $this->artisan('mfg:dispatch-events', ['--limit' => 1])->assertSuccessful();
        $this->assertDatabaseHas('mfg_outbox', ['event_id' => $pending, 'status' => 'delivered']);
    }

    public function test_enable_fails_closed_when_required_variant_capability_is_not_installed(): void
    {
        app(ModuleManager::class)->disable('manufacturing');
        DB::table('module_installations')->where('key', 'merchandise')->update(['version' => '0.1.0']);
        try {
            app(ModuleManager::class)->enable('manufacturing');
            $this->fail('Expected missing versioned capability rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('merchandise.variants.', json_encode($exception->errors()));
        }
        $this->assertDatabaseHas('module_installations', ['key' => 'manufacturing', 'status' => 'disabled']);
    }

    public function test_batch_reads_do_not_add_one_query_per_parent_and_wip_excludes_finished_scrap(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $demand = D::insert('mfg_demands', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'PAGE-'.$i]);
            D::insert('mfg_demand_lines', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'demand_id' => $demand->id, 'quantity' => '1']);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(ManufacturingReadService::class)->read($this->owner, 'demands', $this->factory->public_id, ['per_page' => 2]);
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $large = app(ManufacturingReadService::class)->read($this->owner, 'demands', $this->factory->public_id, ['per_page' => 25]);
        $largeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(25, $large['items']);
        $this->assertLessThanOrEqual($small + 3, $largeQueries, 'Nested read query count grew with parent count.');
        $order = D::insert('mfg_orders', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'WIP-REPORT']);
        $bundle = D::insert('mfg_bundles', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id,
            'code' => 'WIP-REPORT-B', 'quantity' => '50', 'status' => 'active', 'metadata' => D::encode(['finished_quantity' => '30', 'scrap_quantity' => '2'])]);
        $report = app(ManufacturingReadService::class)->read($this->owner, 'reports', $this->factory->public_id);
        $this->assertSame('18.000000', $report['summary']['wip_quantity']);
        $scan = app(ManufacturingReadService::class)->read($this->owner, 'bundles', $this->factory->public_id, ['q' => 'bundle:'.$bundle->public_id]);
        $this->assertSame($bundle->public_id, $scan['items'][0]['id']);
    }

    public function test_calendar_capacity_plan_commit_and_frozen_reschedule_are_audited(): void
    {
        [$order, $line] = $this->planningFixture();
        $date = now($this->factory->timezone)->addDay()->toDateString();
        $this->planningCommand('plan.calendar.save', ['scope_type' => 'line', 'line_id' => $line->public_id, 'work_date' => $date, 'is_working' => true, 'available_minutes' => '40'])->assertOk();
        $this->getJson('/admin/api/manufacturing/plan-calendar?factory_id='.$this->factory->public_id)->assertOk()
            ->assertJsonPath('data.items.0.line_id', $line->public_id)->assertJsonPath('data.items.0.scope_type', 'line')->assertJsonPath('data.items.0.line_code', $line->code);
        $capacity = $this->getJson('/admin/api/manufacturing/plan-capacity?factory_id='.$this->factory->public_id.'&order_id='.$order->public_id)->assertOk();
        $this->assertSame('50.000000', $capacity->json('data.items.0.planned_minutes'));
        $this->assertTrue($capacity->json('data.items.0.overloaded'));
        $payload = ['planned_start' => $date, 'planned_end' => $date, 'reason' => 'Planner confirms priority', 'freeze_until' => now($this->factory->timezone)->addDays(7)->toDateString(), 'expected_version' => 1];
        $this->planningCommand('plan.commit', $payload, $order->public_id)->assertConflict();
        $commit = $this->planningCommand('plan.commit', $payload + ['acknowledge_warnings' => true], $order->public_id)->assertOk()->json('data');
        $this->assertSame('committed', $commit['metadata']['schedule']['status']);
        $this->assertSame(1, DB::table('mfg_plan_changes')->count());
        $reschedule = ['planned_start' => $date, 'planned_end' => $date, 'reason' => 'Move inside frozen window', 'expected_version' => $commit['version'], 'acknowledge_warnings' => true];
        $this->planningCommand('plan.reschedule', $reschedule, $order->public_id)->assertConflict();
        $actor = $this->operator('global');
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => Role::query()->where('key', 'manufacturing.manager')->value('id'), 'scope_type' => 'global', 'scope_value' => null]);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['permissions' => D::encode(['manufacturing.view', 'manufacturing.plan'])]);
        $this->actingAs($actor->fresh(), 'admin')->withSession(['admin_auth_version' => $actor->fresh()->auth_version]);
        $this->planningCommand('plan.reschedule', $reschedule + ['override_frozen' => true], $order->public_id, false)->assertForbidden();
        $this->planningCommand('plan.reschedule', $reschedule + ['override_frozen' => true], $order->public_id)->assertOk();
        $this->assertSame(2, DB::table('mfg_plan_changes')->count());
        $this->assertSame($order->released_snapshot, DB::table('mfg_orders')->where('id', $order->id)->value('released_snapshot'));
        $last = DB::table('mfg_plan_changes')->latest('id')->first();
        $this->assertSame('plan.reschedule', $last->action);
        $this->assertStringContainsString('Move inside frozen window', $last->reason);
    }

    public function test_bundle_projections_backfill_and_mutations_preserve_exact_wip_invariant(): void
    {
        $order = D::insert('mfg_orders', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'PROJECTION']);
        $values = ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'quantity' => '50', 'status' => 'active',
            'metadata' => D::encode(['finished_quantity' => '30', 'scrap_quantity' => '2'])];
        $bundle = D::insert('mfg_bundles', $values + ['code' => 'PROJECTION-A']);
        $terminal = D::insert('mfg_bundles', array_replace($values, ['code' => 'PROJECTION-B', 'status' => 'scrapped']));
        $this->assertSame('18.000000', (string) BigDecimal::of((string) $bundle->wip_quantity)->toScale(6));
        $migration = require base_path('modules/Manufacturing/database/migrations/2026_10_21_700000_add_manufacturing_bundle_projections.php');
        $migration->down();
        $migration->up();
        $bundle = DB::table('mfg_bundles')->find($bundle->id);
        $this->assertSame('18.000000', (string) BigDecimal::of((string) $bundle->wip_quantity)->toScale(6));
        $this->assertSame(0, (int) DB::table('mfg_bundles')->where('id', $terminal->id)->value('wip_quantity'));
        $bundle = D::update('mfg_bundles', $bundle, ['metadata' => D::encode(['finished_quantity' => '45', 'scrap_quantity' => '3'])], $bundle->version);
        $this->assertSame('2.000000', (string) BigDecimal::of((string) $bundle->wip_quantity)->toScale(6));
        $bundle = D::update('mfg_bundles', $bundle, ['status' => 'completed'], $bundle->version);
        $this->assertSame(0, (int) $bundle->wip_quantity);
        $decimal = D::bundleProjection(['quantity' => '9000000000000.123456', 'metadata' => ['finished_quantity' => '9000000000000.100001', 'scrap_quantity' => '0.000002'], 'status' => 'active']);
        $this->assertSame('0.023453', $decimal['wip_quantity']);
    }

    public function test_reports_keep_latest_full_cost_separate_from_adjustment_deltas(): void
    {
        foreach ([['COST-A', '0.1'], ['COST-B', '0.2']] as [$code, $amount]) {
            $order = D::insert('mfg_orders', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => $code]);
            D::insert('mfg_cost_runs', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'code' => $code.'-BASE', 'status' => 'final', 'total_cost' => $amount]);
            D::insert('mfg_cost_runs', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'code' => $code.'-ADJ', 'status' => 'adjusted', 'total_cost' => $amount]);
        }
        $summary = app(ManufacturingReadService::class)->read($this->owner, 'reports', $this->factory->public_id)['summary'];
        $this->assertSame('0.300000', $summary['cost_amount']);
        $this->assertSame('0.300000', $summary['cost_adjustment_amount']);
    }

    public function test_material_pages_batch_load_balances_and_costs_and_group_report_by_unit(): void
    {
        $setup = app(ManufacturingSetupService::class);
        $company = $setup->dispatch($this->owner, 'company.create', [], ['code' => 'BATCH', 'name' => 'Batch Company']);
        $company = DB::table('mfg_companies')->where('public_id', $company['id'])->first();
        $factory = $setup->dispatch($this->owner, 'factory.create', ['company' => $company], ['code' => 'BATCH', 'name' => 'Batch Factory']);
        $factory = DB::table('mfg_factories')->where('public_id', $factory['id'])->first();
        $sku = $setup->dispatch($this->owner, 'sku.save', ['company' => $company], ['sku' => 'BATCH-FABRIC', 'name' => 'Batch fabric', 'base_unit' => 'm']);
        $skuId = DB::table('merch_skus')->where('public_id', $sku['id'])->value('id');
        $inventory = app(InventoryProductionService::class);
        for ($i = 0; $i < 12; $i++) {
            $inventory->createRoll(['warehouse_id' => $factory->warehouse_id, 'sku_id' => $skuId, 'code' => 'BATCH-ROLL-'.$i, 'quantity' => '1.25', 'unit_cost' => '0.1', 'disposition' => 'saleable'], 'batch-read-roll-'.$i, $this->owner->id);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(ManufacturingReadService::class)->read($this->owner, 'materials', $factory->public_id, ['per_page' => 2]);
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $large = app(ManufacturingReadService::class)->read($this->owner, 'materials', $factory->public_id, ['per_page' => 12]);
        $largeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(12, $large['items']);
        $this->assertLessThanOrEqual($small + 2, $largeQueries, 'Material read added per-roll queries.');
        $this->assertSame('0.125000', $large['items'][0]['base_total_cost']);
        $skuKg = $setup->dispatch($this->owner, 'sku.save', ['company' => $company], ['sku' => 'BATCH-KG', 'name' => 'Batch kilograms', 'base_unit' => 'kg']);
        $inventory->createRoll(['warehouse_id' => $factory->warehouse_id, 'sku_id' => DB::table('merch_skus')->where('public_id', $skuKg['id'])->value('id'),
            'code' => 'BATCH-ROLL-KG', 'quantity' => '2', 'unit_cost' => '0.2', 'disposition' => 'saleable'], 'batch-read-roll-kg', $this->owner->id);
        $report = app(ManufacturingReadService::class)->read($this->owner, 'reports', $factory->public_id);
        $this->assertSame(['kg' => '2.000000', 'm' => '15.000000'], $report['material_totals']);
        $this->assertNull($report['summary']['material_quantity']);
        $order = D::insert('mfg_orders', ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'FRACTIONAL-MRP']);
        D::insert('mfg_material_requirements', ['company_id' => $company->id, 'factory_id' => $factory->id, 'order_id' => $order->id, 'sku_id' => $skuId, 'base_unit' => 'm', 'required_quantity' => '2.75', 'consumed_quantity' => '0.125']);
        $mrp = app(ManufacturingReadService::class)->read($this->owner, 'order-mrp', $factory->public_id, ['order_id' => $order->public_id]);
        $this->assertSame('2.625000', $mrp['items'][0]['required_quantity']);
        $firstRoll = DB::table('inv_trace_units')->where('code', 'BATCH-ROLL-0')->first();
        $hold = $inventory->reserve(['warehouse_id' => $factory->warehouse_id, 'item_id' => $firstRoll->item_id, 'roll_id' => $firstRoll->public_id, 'quantity' => '0.25', 'order_ref' => $order->public_id], 'batch-read-hold', $this->owner->id);
        $selected = app(ManufacturingReadService::class)->read($this->owner, 'materials', $factory->public_id, ['allocation_type' => 'reservations', 'selected_id' => $hold['public_id']]);
        $this->assertSame($hold['public_id'], $selected['items'][0]['id']);
        $this->assertSame('BATCH-FABRIC', $selected['items'][0]['material_code']);
        $this->assertSame($order->public_id, $selected['items'][0]['order_ref']);
        $this->assertSame(1, $selected['total']);
        $take = $inventory->stocktake(['warehouse_id' => $factory->warehouse_id], 'batch-read-stocktake', $this->owner->id);
        $this->assertTrue(Str::isUuid($take['public_id']));
        $snapshot = app(ManufacturingReadService::class)->read($this->owner, 'materials', $factory->public_id);
        $this->assertSame($take['public_id'], $snapshot['stocktakes'][0]['id']);
        $this->assertArrayNotHasKey('unit_cost', $snapshot['stocktakes'][0]['lines'][0]);
        $countPage = app(ManufacturingReadService::class)->read($this->owner, 'materials', $factory->public_id, ['allocation_type' => 'stocktake-lines', 'stocktake_id' => $take['public_id'], 'q' => 'BATCH-ROLL-0']);
        $this->assertSame('1.250000', $countPage['items'][0]['expected_quantity']);
        $this->assertSame($firstRoll->public_id, $countPage['items'][0]['roll_id']);
        $this->assertIsInt($countPage['items'][0]['balance_id']);
        $this->assertDenied(fn () => app(ManufacturingReadService::class)->read($this->owner, 'materials', $this->factory->public_id,
            ['allocation_type' => 'stocktake-lines', 'stocktake_id' => $take['public_id']]), 404);
    }

    public function test_engineering_impact_is_read_only_and_requires_company_engineering_grant(): void
    {
        [$order] = $this->planningFixture();
        $old = DB::table('mfg_style_revisions')->where('id', $order->style_revision_id)->first();
        $target = D::insert('mfg_style_revisions', ['company_id' => $this->company->id, 'style_id' => $old->style_id, 'revision' => 2, 'bom' => '[]', 'routing' => D::encode([['code' => 'SEW', 'smv' => '7']])]);
        D::insert('mfg_bundles', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'code' => 'IMPACT', 'quantity' => '10', 'status' => 'active']);
        $style = DB::table('mfg_styles')->find($old->style_id);
        $path = '/admin/api/manufacturing/engineering-impact?factory_id='.$this->factory->public_id.'&style_id='.$style->public_id.'&revision_id='.$target->public_id;
        $this->actingAs($this->owner, 'admin')->getJson($path)->assertOk()->assertJsonPath('data.mutation_performed', false)->assertJsonPath('data.orders.0.uses_target_revision', false)->assertJsonPath('data.wip.0.quantity', '10.000000');
        $this->assertSame($order->released_snapshot, DB::table('mfg_orders')->where('id', $order->id)->value('released_snapshot'));
        $this->assertSame(0, DB::table('mfg_plan_changes')->count());
        $actor = $this->operator('global');
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => Role::query()->where('key', 'manufacturing.manager')->value('id'), 'scope_type' => 'global', 'scope_value' => null]);
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['role' => 'manager']);
        $this->actingAs($actor->fresh(), 'admin')->getJson($path)->assertForbidden();
    }

    public function test_capacity_preserves_fractional_database_quantities_and_minutes(): void
    {
        [$order, $line] = $this->planningFixture();
        DB::table('mfg_allocations')->where('order_id', $order->id)->update(['quantity' => '1.5']);
        DB::table('mfg_lines')->where('id', $line->id)->update(['capacity_minutes' => '7.4']);
        $this->actingAs($this->owner, 'admin')->getJson('/admin/api/manufacturing/plan-capacity?factory_id='.$this->factory->public_id.'&order_id='.$order->public_id)
            ->assertOk()->assertJsonPath('data.items.0.planned_minutes', '7.500000')->assertJsonPath('data.items.0.available_minutes', '7.400000')->assertJsonPath('data.items.0.overloaded', true);
        $this->planningCommand('plan.calendar.save', ['scope_type' => 'line', 'line_id' => $line->public_id, 'work_date' => $order->due_date, 'is_working' => true, 'available_minutes' => '7.6'])->assertOk();
        $this->getJson('/admin/api/manufacturing/plan-capacity?factory_id='.$this->factory->public_id.'&order_id='.$order->public_id)
            ->assertOk()->assertJsonPath('data.items.0.available_minutes', '7.600000')->assertJsonPath('data.items.0.overloaded', false);
    }

    public function test_procurement_read_exposes_scoped_receipt_uuids_and_masks_costs(): void
    {
        app(ModuleManager::class)->install('purchasing');
        app(ModuleManager::class)->enable('purchasing');
        $setup = app(ManufacturingSetupService::class);
        $company = $setup->dispatch($this->owner, 'company.create', [], ['code' => 'RECEIPTS', 'name' => 'Receipt Company']);
        $company = DB::table('mfg_companies')->where('public_id', $company['id'])->first();
        $factory = $setup->dispatch($this->owner, 'factory.create', ['company' => $company], ['code' => 'RECEIPTS', 'name' => 'Receipt Factory']);
        $factory = DB::table('mfg_factories')->where('public_id', $factory['id'])->first();
        $sku = $setup->dispatch($this->owner, 'sku.save', ['company' => $company], ['sku' => 'RECEIPT-FABRIC', 'name' => 'Receipt fabric', 'base_unit' => 'm']);
        $skuId = DB::table('merch_skus')->where('public_id', $sku['id'])->value('id');
        $itemId = app(InventoryV2Service::class)->ensureItem($skuId);
        $supplierId = DB::table('purchase_suppliers')->insertGetId(['public_id' => (string) Str::uuid(), 'code' => 'SUPPLIER-R', 'name' => 'Receipt supplier', 'phone' => 'private phone']);
        $orderUuid = (string) Str::uuid();
        $orderId = DB::table('purchase_orders')->insertGetId(['public_id' => $orderUuid, 'code' => 'RECEIPT-PO', 'supplier_id' => $supplierId, 'warehouse_id' => $factory->warehouse_id,
            'catalog_id' => D::json($company->settings)['catalog_id'], 'legal_entity_ref' => 'mfg-company:'.$company->public_id, 'status' => 'partially_received', 'created_by_admin_id' => $this->owner->id, 'version' => 3]);
        $lineUuid = (string) Str::uuid();
        $lineId = DB::table('purchase_order_lines')->insertGetId(['public_id' => $lineUuid, 'order_id' => $orderId, 'sku_id' => $skuId, 'inventory_item_id' => $itemId,
            'sku_snapshot' => 'RECEIPT-FABRIC', 'name_snapshot' => 'Receipt fabric', 'unit_snapshot' => 'm', 'quantity_ordered' => '3.5', 'quantity_received' => '2.25', 'quantity_returned' => '0.25', 'unit_cost' => '0.123456']);
        $receiptId = DB::table('purchase_receipts')->insertGetId(['public_id' => (string) Str::uuid(), 'order_id' => $orderId, 'actor_id' => $this->owner->id]);
        $roll = app(InventoryProductionService::class)->createRoll(['warehouse_id' => $factory->warehouse_id, 'sku_id' => $skuId,
            'code' => 'RECEIPT-ROLL', 'quantity' => '2.25', 'unit_cost' => '0.123456', 'source_type' => 'purchasing.receipt', 'source_id' => (string) $receiptId], 'receipt-read-roll', $this->owner->id);
        $receiptLineUuid = (string) Str::uuid();
        DB::table('purchase_receipt_lines')->insert(['public_id' => $receiptLineUuid, 'receipt_id' => $receiptId, 'order_line_id' => $lineId, 'quantity' => '2.25', 'quantity_returned' => '0.25',
            'unit_cost' => '0.123456', 'amount_minor' => 0, 'stock_operation_id' => $roll['receipt']['public_id'], 'batch_id' => $roll['batch_id'], 'trace_unit_id' => $roll['id'], 'qc_status' => 'quarantine']);
        $actor = $this->operator('global');
        DB::table('mfg_memberships')->where('admin_id', $actor->id)->update(['company_id' => $company->id, 'factory_id' => $factory->id]);
        $role = Role::query()->create(['key' => 'mfg-receipt-reader', 'name' => 'Receipt reader', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->where('key', 'purchasing.view')->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        $read = app(ManufacturingReadService::class)->read($actor->fresh(), 'materials', $factory->public_id);
        $receipt = $read['purchase_orders'][0]['receipt_lines'][0];
        $this->assertSame('Receipt supplier', $read['purchase_orders'][0]['supplier_name']);
        $this->assertSame($receiptLineUuid, $receipt['id']);
        $this->assertSame($lineUuid, $receipt['order_line_id']);
        $this->assertSame($roll['public_id'], $receipt['roll_id']);
        $this->assertSame('2.000000', $receipt['returnable_quantity']);
        $this->assertSame(3, $receipt['order_version']);
        $this->assertArrayNotHasKey('unit_cost', $read['purchase_orders'][0]['lines'][0]);
        $this->assertArrayNotHasKey('phone', $read['suppliers'][0]);
        $selected = app(ManufacturingReadService::class)->read($actor->fresh(), 'materials', $factory->public_id, ['allocation_type' => 'suppliers', 'selected_id' => $read['suppliers'][0]['public_id']]);
        $this->assertSame($supplierId, $selected['items'][0]['id']);
        $foreign = app(ManufacturingReadService::class)->read($this->owner, 'materials', $this->factory->public_id);
        $this->assertSame([], $foreign['purchase_orders']);
        DB::table('module_installations')->where('key', 'purchasing')->update(['version' => '0.2.0']);
        $old = app(ManufacturingReadService::class)->read($actor->fresh(), 'materials', $factory->public_id);
        $this->assertSame([], $old['purchase_orders'][0]['receipt_lines']);
    }

    private function planningFixture(): array
    {
        $workshop = D::insert('mfg_workshops', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'PLAN-W', 'name' => 'Planning Workshop', 'status' => 'active']);
        $line = D::insert('mfg_lines', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'workshop_id' => $workshop->id, 'code' => 'PLAN-L', 'name' => 'Planning Line', 'capacity_minutes' => '100', 'status' => 'active']);
        $style = D::insert('mfg_styles', ['company_id' => $this->company->id, 'code' => 'PLAN-S', 'name' => 'Planning Style']);
        $revision = D::insert('mfg_style_revisions', ['company_id' => $this->company->id, 'style_id' => $style->id, 'revision' => 1, 'routing' => D::encode([['code' => 'SEW', 'smv' => '5']]), 'bom' => '[]', 'status' => 'approved']);
        $date = now($this->factory->timezone)->addDay()->toDateString();
        $order = D::insert('mfg_orders', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'code' => 'PLAN-ORDER', 'style_revision_id' => $revision->id,
            'planned_start_date' => $date, 'due_date' => $date, 'released_snapshot' => D::encode(['routing' => [['code' => 'SEW', 'smv' => '5']]])]);
        D::insert('mfg_allocations', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'order_id' => $order->id, 'line_id' => $line->id,
            'workshop_id' => $workshop->id, 'quantity' => '10', 'starts_at' => $date, 'ends_at' => $date]);

        return [$order, $line];
    }

    private function planningCommand(string $operation, array $data, ?string $resource = null, bool $owner = true): TestResponse
    {
        if ($owner) {
            $this->actingAs($this->owner, 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        }

        return $this->postJson('/admin/api/manufacturing/commands/'.$operation, ['factory_id' => $this->factory->public_id, 'resource' => $resource, 'data' => $data], ['Idempotency-Key' => (string) Str::uuid()]);
    }

    private function operator(string $scope): Admin
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->where('key', 'manufacturing.operator')->firstOrFail();
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => $scope, 'scope_value' => $scope === 'website' ? 'website-main' : null]);
        D::insert('mfg_memberships', ['company_id' => $this->company->id, 'factory_id' => $this->factory->id, 'admin_id' => $actor->id, 'role' => 'operator', 'status' => 'active']);

        return $actor->fresh();
    }

    private function assertDenied(callable $callback, int $status): void
    {
        try {
            $callback();
            $this->fail('Expected authorization/conflict exception');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }
}
