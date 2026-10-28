<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ModuleTestDataTest extends TestCase
{
    use RefreshDatabase;

    private Admin $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = Admin::factory()->create(['must_change_password' => false])->fresh();
        Schema::create('fixture_roots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id');
            $table->string('website_key');
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('fixture_children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('root_id')->constrained('fixture_roots')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('fixture_soft_references', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('root_id')->nullable();
            $table->json('payload')->nullable();
        });
        $module = ['key' => 'fixture', 'name' => 'Fixture app', 'status' => 'enabled', 'dependencies' => [], 'test_data_provider' => OwnedFixtureProvider::class];
        $registry = Mockery::mock(ModuleRegistry::class)->makePartial();
        $registry->shouldReceive('all')->andReturn(collect([$module]));
        $registry->shouldReceive('find')->with('fixture')->andReturn($module);
        $this->app->instance(ModuleRegistry::class, $registry);
        DB::table('module_installations')->insert(['key' => 'fixture', 'name' => 'Fixture', 'version' => '1.0.0', 'status' => 'enabled']);
        OwnedFixtureProvider::$fail = false;
    }

    public function test_batches_append_and_replay_without_tagging_or_deleting_same_prefix_real_data(): void
    {
        $real = DB::table('fixture_roots')->insertGetId(['public_id' => (string) Str::uuid(), 'website_key' => 'website-main', 'code' => 'TEST-REAL', 'name' => 'Real data', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(ModuleTestDataService::class);
        $request = (string) Str::uuid();
        $first = $service->create('fixture', 'website-main', $this->actor, $request);
        $this->assertSame($first, $service->create('fixture', 'website-main', $this->actor, $request));
        $second = $service->create('fixture', 'website-main', $this->actor, (string) Str::uuid());
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertDatabaseCount('fixture_roots', 3);
        $this->assertSame(3, $first['record_count']);
        $this->assertSame(['batch_count' => 1, 'record_count' => 3], $service->remove('website-main', $this->actor, 'fixture', $first['id']));
        $this->assertDatabaseHas('fixture_roots', ['id' => $real, 'name' => 'Real data']);
        $this->assertCount(1, $service->batches('website-main'));
        $this->assertSame(['batch_count' => 0, 'record_count' => 0], $service->remove('website-main', $this->actor, 'fixture', $first['id']));
        $this->assertSame('removed', $service->create('fixture', 'website-main', $this->actor, $request)['status']);
        $this->assertDatabaseCount('fixture_roots', 2);
    }

    public function test_an_unowned_cascade_child_blocks_all_cleanup_and_preserves_the_ledger(): void
    {
        [$batch, $root] = $this->batch();
        DB::table('fixture_children')->insert(['root_id' => $root, 'name' => 'Manual real addition', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertBlocked(fn () => app(ModuleTestDataService::class)->remove('website-main', $this->actor));
        $this->assertDatabaseCount('fixture_roots', 1);
        $this->assertDatabaseCount('fixture_children', 3);
        $this->assertDatabaseCount('module_test_data_records', 3);
        $this->assertDatabaseHas('module_test_data_batches', ['id' => $batch['id'], 'status' => 'active']);
    }

    public function test_unowned_scalar_and_json_wildcard_links_block_removal(): void
    {
        [, $root] = $this->batch();
        $id = DB::table('fixture_soft_references')->insertGetId(['root_id' => $root]);
        $this->assertBlocked(fn () => app(ModuleTestDataService::class)->remove('website-main', $this->actor));
        DB::table('fixture_soft_references')->where('id', $id)->update(['root_id' => null, 'payload' => json_encode(['items' => [['root' => $root]]])]);
        $this->assertBlocked(fn () => app(ModuleTestDataService::class)->remove('website-main', $this->actor));
        $this->assertDatabaseCount('fixture_roots', 1);
        $this->assertDatabaseCount('module_test_data_records', 3);
    }

    public function test_old_batch_uses_current_provider_reference_contract_even_when_module_is_disabled(): void
    {
        [$batch, $root] = $this->batch();
        DB::table('module_test_data_batches')->where('id', $batch['id'])->update(['soft_references' => '[]']);
        DB::table('module_installations')->where('key', 'fixture')->update(['status' => 'disabled']);
        $reference = DB::table('fixture_soft_references')->insertGetId(['payload' => json_encode(['items' => [['root' => $root]]])]);
        $this->assertBlocked(fn () => app(ModuleTestDataService::class)->remove('website-main', $this->actor, 'fixture', $batch['id']));
        $this->assertDatabaseCount('fixture_roots', 1);
        $this->assertDatabaseCount('module_test_data_records', 3);
        DB::table('fixture_soft_references')->where('id', $reference)->delete();
        $this->assertSame(['batch_count' => 1, 'record_count' => 3], app(ModuleTestDataService::class)->remove('website-main', $this->actor, 'fixture', $batch['id']));
    }

    public function test_cleanup_is_scoped_to_the_selected_website(): void
    {
        $service = app(ModuleTestDataService::class);
        $service->create('fixture', 'website-main', $this->actor, (string) Str::uuid());
        $other = $service->create('fixture', 'other-website', $this->actor, (string) Str::uuid());
        $service->remove('website-main', $this->actor);
        $this->assertDatabaseCount('fixture_roots', 1);
        $this->assertDatabaseHas('fixture_roots', ['website_key' => 'other-website']);
        $this->assertDatabaseHas('module_test_data_batches', ['id' => $other['id'], 'status' => 'active']);
    }

    public function test_normal_content_edits_remain_test_data_but_reused_identity_is_not_deleted(): void
    {
        [, $root] = $this->batch();
        DB::table('fixture_roots')->where('id', $root)->update(['name' => 'Edited during testing']);
        $this->assertSame(3, app(ModuleTestDataService::class)->remove('website-main', $this->actor)['record_count']);
        [, $root] = $this->batch();
        DB::table('fixture_roots')->where('id', $root)->update(['public_id' => (string) Str::uuid()]);
        $this->assertBlocked(fn () => app(ModuleTestDataService::class)->remove('website-main', $this->actor));
        $this->assertDatabaseCount('fixture_roots', 1);
    }

    public function test_failed_creation_rolls_back_fixtures_and_batch_metadata(): void
    {
        OwnedFixtureProvider::$fail = true;
        try {
            app(ModuleTestDataService::class)->create('fixture', 'website-main', $this->actor, (string) Str::uuid());
            $this->fail('The provider failure must roll back all new data.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fixture failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('fixture_roots', 0);
        $this->assertDatabaseCount('module_test_data_batches', 0);
        $this->assertDatabaseCount('module_test_data_records', 0);
    }

    public function test_provider_context_cannot_own_core_permission_or_configuration_tables(): void
    {
        [$batch] = $this->batch();
        $context = new ModuleTestDataContext($batch['id'], 'fixture', 'website-main', $this->actor);
        foreach (['admin_role_assignments', 'permissions', 'module_installations', 'site_profiles'] as $table) {
            try {
                DB::transaction(fn () => $context->insert($table, []));
                $this->fail('Core permissions and configuration must never become test-owned rows.');
            } catch (RuntimeException $exception) {
                $this->assertSame('This table cannot be owned by a test-data batch.', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('module_test_data_records', 3);
        $this->assertDatabaseCount('fixture_roots', 1);
    }

    public function test_disabled_and_active_lifecycle_operations_cannot_generate_test_data(): void
    {
        DB::table('module_installations')->where('key', 'fixture')->update(['status' => 'disabled']);
        $this->assertBlocked(fn () => $this->batch());
        DB::table('module_installations')->where('key', 'fixture')->update(['status' => 'enabled']);
        app(ModuleLifecycleCoordinator::class)->begin('fixture', 'disable', '1.0.0', null, $this->actor->id);
        $this->assertBlocked(fn () => $this->batch());
        $this->assertDatabaseCount('module_test_data_batches', 0);
    }

    public function test_http_requires_management_permission_and_explicit_delete_confirmation(): void
    {
        $staff = Admin::factory()->create(['must_change_password' => false])->fresh();
        $this->actingAs($staff, 'admin')->getJson('/admin/api/modules/demo-data')->assertForbidden();
        $this->postJson('/admin/api/modules/fixture/demo-data', ['request_id' => (string) Str::uuid()])->assertForbidden();
        $this->deleteJson('/admin/api/modules/demo-data', ['confirmed' => true])->assertForbidden();
        $this->actingAs($this->actor, 'admin')->postJson('/admin/api/modules/fixture/demo-data', ['request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.record_count', 3);
        $this->deleteJson('/admin/api/modules/demo-data')->assertUnprocessable();
        $this->deleteJson('/admin/api/modules/demo-data', ['confirmed' => true])->assertOk()->assertJsonPath('data.record_count', 3);
        $this->assertDatabaseCount('fixture_roots', 0);
    }

    private function batch(): array
    {
        $batch = app(ModuleTestDataService::class)->create('fixture', 'website-main', $this->actor, (string) Str::uuid());
        $root = DB::table('fixture_roots')->latest('id')->value('id');

        return [$batch, $root];
    }

    private function assertBlocked(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The operation must preserve all existing rows.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}

final class OwnedFixtureProvider implements ModuleTestDataProvider
{
    public static bool $fail = false;

    public function preview(): array
    {
        return ['Isolated fixture with two children'];
    }

    public function references(): array
    {
        return [
            ['table' => 'fixture_soft_references', 'column' => 'root_id', 'foreign_table' => 'fixture_roots'],
            ['table' => 'fixture_soft_references', 'column' => 'payload->items->*->root', 'foreign_table' => 'fixture_roots'],
        ];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $id = $context->insert('fixture_roots', ['public_id' => (string) Str::uuid(), 'code' => $context->code('ROOT'), 'name' => '[TEST] Isolated fixture']);
        if (self::$fail) {
            throw new RuntimeException('Fixture failure');
        }
        $context->insert('fixture_children', ['root_id' => $id, 'name' => 'Test child 1']);
        $context->insert('fixture_children', ['root_id' => $id, 'name' => 'Test child 2']);
    }
}
