<?php
namespace Tests\Feature;
use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\FnbPos\Services\FnbMenuChoiceService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Tests\TestCase;
class FnbMenuChoiceDefinitionsTest extends TestCase
{
    use RefreshDatabase;
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
    }


    public function test_names_and_codes_are_generated_scoped_and_unique(): void
    {
        foreach ([[], ['code' => null], ['code' => ''], ['code' => '  ']] as $i => $code) {
            $payload = $code + ['name' => 'Size lớn '.$i];
            $key = (string) Str::uuid();
            $created = $this->command('menu/size-definitions', $payload, $key)->assertOk()->json('data');
            $this->assertSame('SIZE_LON_'.$i, $created['code']);
            $this->command('menu/size-definitions', $payload, $key)->assertOk()->assertJsonPath('meta.replayed', true)->assertJsonPath('data.id', $created['id']);
        }
        $this->command('menu/size-definitions', ['name' => ' SIZE   LỚN 0 '])->assertUnprocessable();
        $this->command('menu/size-definitions', ['name' => 'Khác', 'code' => 'size_lon_0'])->assertUnprocessable();
        $this->command('menu/topping-definitions', ['name' => 'Size lớn 0'])->assertOk();
        $foreign = FnbMenuChoiceService::import('other-website', 'size', 'Riêng');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/menu/size-definitions/'.$foreign['id'], ['name' => 'Sửa', 'expected_version' => 1])->assertNotFound();
        $catalog = $this->getJson('/admin/api/fnb/outlets/'.DB::table('fnb_outlets')->value('id').'/menu?draft=1')->assertOk();
        $this->assertNotContains('Riêng', array_column($catalog->json('data.size_definitions'), 'name'));
    }

    public function test_crud_versions_and_archive_preserve_assigned_variants(): void
    {
        $size = $this->command('menu/size-definitions', ['name' => 'Lớn', 'code' => 'L'])->assertOk()->json('data');
        $item = $this->command('menu/items', ['code' => 'LATTE', 'name' => 'Latte', 'status' => 'active',
            'variant' => ['code' => 'L', 'name' => 'Lớn', 'base_price_minor' => 35000], 'station_id' => DB::table('fnb_prep_stations')->value('id'),
        ])->assertOk()->json('data');
        $url = '/admin/api/fnb/menu/size-definitions/'.$size['id'];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson($url, ['name' => 'Rất lớn'])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson($url, ['name' => 'Rất lớn', 'code' => '', 'expected_version' => 1])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.code', 'L');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson($url, ['name' => 'Cũ', 'expected_version' => 1])->assertConflict();
        $this->command('menu/size-definitions/'.$size['id'].'/archive', ['expected_version' => 1])->assertConflict();
        $key = (string) Str::uuid();
        $this->command('menu/size-definitions/'.$size['id'].'/archive', ['expected_version' => 2], $key)->assertOk();
        $this->command('menu/size-definitions/'.$size['id'].'/archive', ['expected_version' => 2], $key)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertDatabaseHas('fnb_item_variants', ['id' => $item['variants'][0]['id'], 'code' => 'L', 'name' => 'Lớn', 'base_price_minor' => 35000]);
        $this->assertNotContains($size['id'], array_column(app(FnbMenuChoiceService::class)->catalog('website-main', 'size'), 'id'));
    }

    public function test_upgrade_imports_existing_names_without_changing_item_prices(): void
    {
        $item = $this->command('menu/items', ['code' => 'LATTE', 'name' => 'Latte', 'status' => 'active',
            'variant' => ['code' => 'M', 'name' => 'Vừa', 'base_price_minor' => 35000], 'station_id' => DB::table('fnb_prep_stations')->value('id'),
        ])->assertOk()->json('data');
        $group = $this->command('menu/modifier-groups', ['name' => 'Thêm', 'min_select' => 0, 'max_select' => 2])->assertOk()->json('data');
        $option = $this->command('menu/modifier-options', ['group_id' => $group['id'], 'code' => 'PEARL', 'name' => 'Trân châu', 'base_price_delta_minor' => 5000])->assertOk()->json('data');
        Schema::drop('fnb_menu_choice_definitions');
        (require base_path('modules/FnbPos/database/migrations/000013_create_fnb_menu_choice_definitions.php'))->up();
        $this->assertDatabaseHas('fnb_menu_choice_definitions', ['kind' => 'size', 'code' => 'M', 'name' => 'Vừa']);
        $this->assertDatabaseHas('fnb_menu_choice_definitions', ['kind' => 'topping', 'code' => 'PEARL', 'name' => 'Trân châu']);
        $this->assertDatabaseHas('fnb_modifier_options', ['id' => $option['id'], 'name' => 'Trân châu', 'base_price_delta_minor' => 5000]);

        $this->assertDatabaseHas('fnb_item_variants', ['id' => $item['variants'][0]['id'], 'code' => 'M', 'name' => 'Vừa', 'base_price_minor' => 35000]);
    }

    public function test_branch_manager_cannot_change_shared_definitions(): void
    {
        $manager = Admin::factory()->create(['must_change_password' => false]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(Admin::findOrFail(1), 'website-main', (int) DB::table('fnb_outlets')->value('id'), $manager, 'fnb-manager'));
        $manager = $manager->fresh();
        $this->actingAs($manager, 'admin')->withSession(['admin_auth_version' => $manager->auth_version]);
        $this->command('menu/size-definitions', ['name' => 'Lớn'])->assertForbidden();
        $this->command('menu/topping-definitions', ['name' => 'Kem'])->assertForbidden();
    }

    private function command(string $path, array $payload, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/fnb/'.$path, $payload);
    }
}
