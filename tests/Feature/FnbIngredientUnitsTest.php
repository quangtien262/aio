<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Services\FnbUnitService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Tests\TestCase;

class FnbIngredientUnitsTest extends TestCase
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

    public function test_ingredient_codes_are_optional_unique_and_preserved_on_edit(): void
    {
        foreach ([[], ['code' => null], ['code' => ''], ['code' => '  ']] as $i => $code) {
            $key = (string) Str::uuid();
            $payload = $code + ['name' => 'Cà phê hạt', 'base_unit' => 'g'];
            $expected = $i === 0 ? 'ca-phe-hat' : 'ca-phe-hat-'.($i + 1);
            $created = $this->command('ingredients', $payload, $key)->assertOk()->assertJsonPath('data.code', $expected)->json('data');
            $this->command('ingredients', $payload, $key)->assertOk()->assertJsonPath('meta.replayed', true)->assertJsonPath('data.id', $created['id']);
        }
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/ingredients/'.$created['id'], [
            'name' => 'Cà phê mới', 'base_unit' => 'g', 'code' => '', 'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.code', $expected);
        $this->command('ingredients', ['name' => 'Không hợp lệ', 'base_unit' => 'unknown'])->assertUnprocessable();
        $this->command('ingredients', ['name' => 'Trùng mã', 'base_unit' => 'g', 'code' => $expected])->assertUnprocessable();
    }

    public function test_unit_crud_is_versioned_scoped_and_cannot_remove_used_or_system_units(): void
    {
        $payload = ['code' => 'hop', 'name' => 'Hộp', 'dimension' => 'count', 'factor' => '1'];
        $key = (string) Str::uuid();
        $unit = $this->command('units', $payload, $key)->assertOk()->json('data');
        $this->command('units', $payload, $key)->assertOk()->assertJsonPath('meta.replayed', true)->assertJsonPath('data.id', $unit['id']);
        $this->command('units', $payload)->assertUnprocessable();
        $update = $payload + ['expected_version' => 1];
        $update['name'] = 'Hộp giấy';
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/units/'.$unit['id'], $update)->assertOk()->assertJsonPath('data.version', 2);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/units/'.$unit['id'], $update)->assertConflict();
        $this->command('ingredients', ['name' => 'Hộp bánh', 'base_unit' => 'hop'])->assertOk();
        $this->command('units/'.$unit['id'].'/archive', ['expected_version' => 2])->assertConflict();
        $update['expected_version'] = 2;
        $update['factor'] = '2';
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/units/'.$unit['id'], $update)->assertConflict();
        $update['factor'] = '1';
        $update['status'] = 'inactive';
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/units/'.$unit['id'], $update)->assertOk();
        $this->command('ingredients', ['name' => 'Hộp mới', 'base_unit' => 'hop'])->assertUnprocessable();
        $system = DB::table('fnb_units')->where('code', 'g')->first();
        $this->command('units/'.$system->id.'/archive', ['expected_version' => 1])->assertConflict();
        $unused = $this->command('units', array_replace($payload, ['code' => 'khay']))->assertOk()->json('data');
        $archiveKey = (string) Str::uuid();
        $this->command('units/'.$unused['id'].'/archive', ['expected_version' => 1], $archiveKey)->assertOk();
        $this->command('units/'.$unused['id'].'/archive', ['expected_version' => 1], $archiveKey)->assertOk()->assertJsonPath('meta.replayed', true);
        $read = $this->getJson('/admin/api/fnb/outlets/'.DB::table('fnb_outlets')->value('id').'/recipes')->assertOk();
        $this->assertNotContains('khay', array_column($read->json('data.units'), 'code'));
        FnbUnitService::seedDefaults('other-website');
        $foreign = DB::table('fnb_units')->where('website_key', 'other-website')->first();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/units/'.$foreign->id, $update)->assertNotFound();
    }

    public function test_recipe_publication_uses_custom_unit_conversion_and_locks_its_definition(): void
    {
        $payload = ['code' => 'chai', 'name' => 'Chai 500 ml', 'dimension' => 'volume', 'factor' => '500'];
        $unit = $this->command('units', $payload)->assertOk()->json('data');
        $item = $this->command('menu/items', ['code' => 'LATTE', 'name' => 'Latte', 'status' => 'active',
            'variant' => ['code' => 'M', 'name' => 'Vừa', 'base_price_minor' => 35000], 'station_id' => DB::table('fnb_prep_stations')->value('id'),
        ])->assertOk()->json('data');
        $ingredient = $this->command('ingredients', ['name' => 'Sữa', 'base_unit' => 'ml'])->assertOk()->json('data');
        $recipe = ['code' => 'LATTE', 'name' => 'Latte', 'variant_id' => $item['variants'][0]['id'], 'expected_recipe_version' => 0, 'yield_quantity' => '1', 'yield_unit' => 'ly',
            'lines' => [['ingredient_id' => $ingredient['id'], 'quantity' => '0.25', 'unit' => 'chai']],
        ];
        $created = $this->command('recipes/publish', $recipe)->assertOk()->json('data');
        $this->assertEquals('125.000000', $created['lines'][0]['base_quantity']);
        $this->command('units/'.$unit['id'].'/archive', ['expected_version' => 1])->assertConflict();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/units/'.$unit['id'], array_replace($payload, ['expected_version' => 1, 'factor' => '1000']))->assertConflict();
        $recipe['expected_recipe_version'] = 1;
        $recipe['lines'][0]['unit'] = 'kg';
        $this->command('recipes/publish', $recipe)->assertConflict();
        $this->assertDatabaseCount('fnb_recipes', 1);
        $this->assertDatabaseHas('fnb_recipe_lines', ['recipe_id' => $created['id'], 'base_quantity' => 125]);
    }

    public function test_conversion_is_decimal_precise_and_rejects_overflow_and_invalid_factors(): void
    {
        $service = app(FnbUnitService::class);
        $this->assertSame('0.001000', $service->convertQuantity('website-main', '1', 'g', 'kg'));
        $this->assertSame('150.000000', $service->convertQuantity('website-main', '0.15', 'l', 'ml'));
        foreach (['0', '-1', '1000001', '0.0000001', '1e3'] as $factor) {
            $this->command('units', ['code' => 'invalid', 'name' => 'Sai', 'dimension' => 'volume', 'factor' => $factor])->assertUnprocessable();
        }
        $this->command('units', ['code' => 'huge', 'name' => 'Lớn', 'dimension' => 'volume', 'factor' => '1000000'])->assertOk();
        $this->command('units', ['code' => 'tiny', 'name' => 'Nhỏ', 'dimension' => 'volume', 'factor' => '0.000001'])->assertOk();
        $this->expectException(FnbConflictException::class);
        $service->convertQuantity('website-main', '1000000', 'huge', 'tiny');
    }

    public function test_branch_staff_cannot_manage_units(): void
    {
        $manager = Admin::factory()->create(['must_change_password' => false]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(Admin::findOrFail(1), 'website-main', (int) DB::table('fnb_outlets')->value('id'), $manager, 'fnb-manager'));
        $manager = $manager->fresh();
        $this->actingAs($manager, 'admin')->withSession(['admin_auth_version' => $manager->auth_version]);
        $this->command('units', ['code' => 'hop', 'name' => 'Hộp', 'dimension' => 'count', 'factor' => '1'])->assertForbidden();
        $this->command('units/'.DB::table('fnb_units')->value('id').'/archive', ['expected_version' => 1])->assertForbidden();
    }

    public function test_upgrade_adds_units_and_preserves_existing_ingredients(): void
    {
        $ingredient = $this->command('ingredients', ['name' => 'Sữa', 'base_unit' => 'ml'])->assertOk()->json('data');
        Schema::drop('fnb_units');
        DB::table('migrations')->where('migration', '000012_create_fnb_units')->delete();
        ModuleInstallation::where('key', 'fnb-pos')->update(['version' => '0.2.0-dev.1']);
        app(ModuleManager::class)->upgrade('fnb-pos');
        $this->assertDatabaseCount('fnb_units', 5);
        $this->assertDatabaseHas('fnb_ingredients', ['id' => $ingredient['id'], 'base_unit' => 'ml', 'code' => 'sua']);
        $this->assertSame('0.2.0-dev.3', ModuleInstallation::where('key', 'fnb-pos')->value('version'));
    }

    private function command(string $path, array $payload, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/fnb/'.$path, $payload);
    }
}
