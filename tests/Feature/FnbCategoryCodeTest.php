<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Tests\TestCase;

class FnbCategoryCodeTest extends TestCase
{
    use RefreshDatabase;

    private int $menuId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(1);
        $owner->forceFill(['password' => 'FnbPassword123!', 'must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
        $payload = [
            'outlet' => ['code' => 'CAFE', 'name' => 'Quán Cafe', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS-01', 'name' => 'Quầy 1', 'type' => 'pos'],
            'station' => ['code' => 'BAR', 'name' => 'Bar'],
            'payment_methods' => [['code' => 'CASH', 'name' => 'Tiền mặt', 'kind' => 'cash']],
        ];
        $key = (string) Str::uuid();
        $challenge = $this->withHeader('Idempotency-Key', $key)->postJson('/admin/api/fnb/onboarding/presets/cafe', $payload)->assertStatus(423);
        $proof = $this->postJson('/admin/api/fnb/reauth/proofs', [
            'action' => 'onboard', 'subject' => 'new', 'payload_hash' => $challenge->json('details.payload_hash'), 'password' => 'FnbPassword123!',
        ])->assertOk()->json('data.reauth_proof');
        $this->withHeader('X-FNB-Reauth-Proof', $proof)->withHeader('Idempotency-Key', $key)
            ->postJson('/admin/api/fnb/onboarding/presets/cafe', $payload)->assertOk();
        $this->withHeader('X-FNB-Reauth-Proof', '')
            ->withHeader('X-FNB-Outlet', (string) DB::table('fnb_outlets')->value('id'))
            ->withHeader('X-FNB-Terminal', (string) DB::table('fnb_terminals')->value('id'));
        $this->menuId = (int) DB::table('fnb_menus')->value('id');
    }

    public function test_blank_category_codes_are_generated_from_vietnamese_names_and_replay_the_same_result(): void
    {
        foreach ([[], ['code' => null], ['code' => ''], ['code' => " \t "]] as $index => $code) {
            $key = (string) Str::uuid();
            $payload = $code + ['menu_id' => $this->menuId, 'name' => 'Đồ uống'];
            $expectedCode = $index === 0 ? 'do-uong' : 'do-uong-'.($index + 1);
            $created = $this->createCategory($payload, $key)->assertOk()->assertJsonPath('data.code', $expectedCode)->json('data');
            $this->createCategory($payload, $key)->assertOk()->assertJsonPath('meta.replayed', true)
                ->assertJsonPath('data.id', $created['id'])->assertJsonPath('data.code', $expectedCode);
        }
        $this->assertDatabaseCount('fnb_menu_categories', 4);
    }

    public function test_generated_codes_are_bounded_case_safe_and_unique_within_each_menu(): void
    {
        $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Mã thủ công', 'code' => 'DO-UONG'])->assertOk();
        $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Đồ uống'])->assertOk()->assertJsonPath('data.code', 'do-uong-2');
        $longName = str_repeat('Đồ uống ', 25);
        $first = $this->createCategory(['menu_id' => $this->menuId, 'name' => $longName])->assertOk()->json('data.code');
        $second = $this->createCategory(['menu_id' => $this->menuId, 'name' => $longName])->assertOk()->json('data.code');
        $this->assertLessThanOrEqual(40, strlen($first));
        $this->assertLessThanOrEqual(40, strlen($second));
        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^[a-z0-9_-]+$/', $second);
        $this->createCategory(['menu_id' => $this->menuId, 'name' => '☕ !!!'])->assertOk()->assertJsonPath('data.code', 'nhom-mon');
        $otherMenu = DB::table('fnb_menus')->insertGetId([
            'website_key' => 'website-main', 'code' => 'SECOND', 'name' => 'Menu phụ', 'status' => 'draft', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createCategory(['menu_id' => $otherMenu, 'name' => 'Đồ uống'])->assertOk()->assertJsonPath('data.code', 'do-uong');
    }

    public function test_explicit_codes_are_preserved_and_invalid_or_duplicate_codes_return_validation_errors(): void
    {
        $payload = ['menu_id' => $this->menuId, 'name' => 'Đồ uống', 'code' => 'MENU_Custom-01'];
        $this->createCategory($payload)->assertOk()->assertJsonPath('data.code', 'MENU_Custom-01');
        $this->createCategory($payload)->assertUnprocessable()->assertJsonValidationErrors('code', 'details.errors');
        foreach (['Đồ uống!', str_repeat('A', 41), ['invalid']] as $code) {
            $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Đồ uống', 'code' => $code])
                ->assertUnprocessable()->assertJsonValidationErrors('code', 'details.errors');
        }
        $this->assertDatabaseCount('fnb_menu_categories', 1);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/admin/api/fnb/outlets/'.DB::table('fnb_outlets')->value('id').'/areas', ['name' => 'Khu sân vườn'])
            ->assertUnprocessable()->assertJsonValidationErrors('code', 'details.errors');
    }

    public function test_editing_with_blank_code_preserves_existing_code_and_replay_does_not_allocate_another_code(): void
    {
        $created = $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Đồ uống', 'code' => 'DRINKS'])->assertOk()->json('data');
        $payload = ['menu_id' => $this->menuId, 'name' => 'Đồ uống mới', 'code' => ' ', 'expected_version' => $created['version']];
        $key = (string) Str::uuid();
        $path = '/admin/api/fnb/menu/categories/'.$created['id'];
        $this->withHeader('Idempotency-Key', $key)->putJson($path, $payload)->assertOk()
            ->assertJsonPath('data.code', 'DRINKS')->assertJsonPath('data.version', 2);
        $this->withHeader('Idempotency-Key', $key)->putJson($path, $payload)->assertOk()
            ->assertJsonPath('meta.replayed', true)->assertJsonPath('data.code', 'DRINKS')->assertJsonPath('data.version', 2);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson($path, $payload)->assertConflict();
        $this->assertDatabaseCount('fnb_menu_categories', 1);
    }

    public function test_category_order_is_atomic_persistent_and_idempotent_including_inactive_groups(): void
    {
        $rows = [];
        foreach (['Cà phê', 'Trà', 'Bánh'] as $index => $name) {
            $rows[] = $this->createCategory(['menu_id' => $this->menuId, 'name' => $name, 'status' => $index === 1 ? 'inactive' : 'active'])
                ->assertOk()->assertJsonPath('data.sort_order', $index)->json('data');
        }
        $items = array_map(fn ($row) => ['id' => $row['id'], 'expected_version' => $row['version']], array_reverse($rows));
        $payload = ['menu_id' => $this->menuId, 'items' => $items];
        $key = (string) Str::uuid();
        $response = $this->withHeader('Idempotency-Key', $key)->postJson('/admin/api/fnb/menu/categories/reorder', $payload)->assertOk();
        $this->assertSame(array_column($items, 'id'), array_column($response->json('data.items'), 'id'));
        $this->withHeader('Idempotency-Key', $key)->postJson('/admin/api/fnb/menu/categories/reorder', $payload)
            ->assertOk()->assertJsonPath('meta.replayed', true)->assertJsonPath('data.items', $response->json('data.items'));
        $this->getJson('/admin/api/fnb/outlets/'.DB::table('fnb_outlets')->value('id').'/menu?include_inactive=1&draft=1')->assertOk()
            ->assertJsonPath('data.categories.0.id', $rows[2]['id'])->assertJsonPath('data.categories.2.id', $rows[0]['id']);
        $edited = $response->json('data.items.0');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/menu/categories/'.$edited['id'], [
            'menu_id' => $this->menuId, 'name' => 'Bánh mới', 'expected_version' => $edited['version'],
        ])->assertOk()->assertJsonPath('data.sort_order', 0);
        $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Sinh tố'])->assertOk()->assertJsonPath('data.sort_order', 3);
    }

    public function test_category_reorder_rejects_stale_partial_duplicate_or_foreign_lists_without_partial_updates(): void
    {
        $first = $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Cà phê'])->assertOk()->json('data');
        $second = $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Trà'])->assertOk()->json('data');
        $items = [['id' => $second['id'], 'expected_version' => 1], ['id' => $first['id'], 'expected_version' => 1]];
        $path = '/admin/api/fnb/menu/categories/reorder';
        foreach ([array_slice($items, 0, 1), [$items[0], ['id' => $first['id'], 'expected_version' => 2]], [$items[0], ['id' => 999999, 'expected_version' => 1]]] as $invalid) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, ['menu_id' => $this->menuId, 'items' => $invalid])->assertConflict();
        }
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, ['menu_id' => $this->menuId, 'items' => [$items[0], $items[0]]])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, ['menu_id' => 999999, 'items' => $items])->assertNotFound();
        $this->assertDatabaseHas('fnb_menu_categories', ['id' => $first['id'], 'sort_order' => 0, 'version' => 1]);
        $this->assertDatabaseHas('fnb_menu_categories', ['id' => $second['id'], 'sort_order' => 1, 'version' => 1]);
    }

    public function test_category_order_cannot_move_groups_between_menus_or_be_changed_by_branch_staff(): void
    {
        $first = $this->createCategory(['menu_id' => $this->menuId, 'name' => 'Cà phê'])->assertOk()->json('data');
        $otherMenu = DB::table('fnb_menus')->insertGetId([
            'website_key' => 'website-main', 'code' => 'SECOND', 'name' => 'Menu phụ', 'status' => 'draft', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = $this->createCategory(['menu_id' => $otherMenu, 'name' => 'Bánh'])->assertOk()->json('data');
        $path = '/admin/api/fnb/menu/categories/reorder';
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, [
            'menu_id' => $this->menuId, 'items' => [['id' => $other['id'], 'expected_version' => 1]],
        ])->assertConflict();
        $manager = Admin::factory()->create(['must_change_password' => false]);
        $outlet = (int) DB::table('fnb_outlets')->value('id');
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(Admin::findOrFail(1), 'website-main', $outlet, $manager, 'fnb-manager'));
        $this->actingAs($manager->fresh(), 'admin')->withSession(['admin_auth_version' => $manager->fresh()->auth_version])
            ->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, [
                'menu_id' => $this->menuId, 'items' => [['id' => $first['id'], 'expected_version' => 1]],
            ])->assertForbidden();
        $this->assertDatabaseHas('fnb_menu_categories', ['id' => $first['id'], 'sort_order' => 0, 'version' => 1]);
        $this->assertDatabaseHas('fnb_menu_categories', ['id' => $other['id'], 'menu_id' => $otherMenu, 'sort_order' => 0, 'version' => 1]);
    }

    private function createCategory(array $payload, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/fnb/menu/categories', $payload);
    }
}
