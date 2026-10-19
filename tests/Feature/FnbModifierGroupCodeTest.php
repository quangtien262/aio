<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FnbModifierGroupCodeTest extends TestCase
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

    public function test_blank_group_codes_are_generated_without_collisions_and_replayed_without_duplication(): void
    {
        $path = '/admin/api/fnb/menu/modifier-groups';
        foreach ([[], ['code' => null], ['code' => ''], ['code' => " \t "]] as $index => $code) {
            $payload = $code + ['name' => 'Topping thêm', 'min_select' => 0, 'max_select' => 2];
            $key = (string) Str::uuid();
            $expected = $index === 0 ? 'topping-them' : 'topping-them-'.($index + 1);
            $result = $this->withHeader('Idempotency-Key', $key)->postJson($path, $payload)->assertOk()->assertJsonPath('data.code', $expected)->json('data');
            $this->withHeader('Idempotency-Key', $key)->postJson($path, $payload)->assertOk()->assertJsonPath('meta.replayed', true)->assertJsonPath('data.id', $result['id']);
        }
        $this->assertDatabaseCount('fnb_modifier_groups', 4);
    }

    public function test_editing_with_blank_code_preserves_code_and_explicit_duplicate_codes_fail_validation(): void
    {
        $path = '/admin/api/fnb/menu/modifier-groups';
        $payload = ['name' => 'Topping', 'code' => 'CUSTOM', 'min_select' => 0, 'max_select' => 1];
        $created = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, $payload)->assertOk()->json('data');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, $payload)->assertUnprocessable()->assertJsonValidationErrors('code', 'details.errors');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson($path.'/'.$created['id'], array_replace($payload, [
            'code' => ' ', 'name' => 'Topping mới', 'expected_version' => 1,
        ]))->assertOk()->assertJsonPath('data.code', 'CUSTOM')->assertJsonPath('data.version', 2);
        foreach (['Mã lỗi!', str_repeat('X', 41), ['invalid']] as $code) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, array_replace($payload, ['code' => $code]))
                ->assertUnprocessable()->assertJsonValidationErrors('code', 'details.errors');
        }
        $this->assertDatabaseCount('fnb_modifier_groups', 1);
    }

    public function test_generated_group_codes_are_case_safe_bounded_and_support_names_without_ascii_letters(): void
    {
        $path = '/admin/api/fnb/menu/modifier-groups';
        $payload = ['name' => 'Topping', 'min_select' => 0, 'max_select' => 1];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, $payload + ['code' => 'TOPPING'])->assertOk();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, $payload)->assertOk()->assertJsonPath('data.code', 'topping-2');
        $codes = [];
        foreach ([str_repeat('Đồ thêm ', 25), str_repeat('Đồ thêm ', 25), '☕ !!!'] as $name) {
            $code = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($path, array_replace($payload, ['name' => $name]))->assertOk()->json('data.code');
            $this->assertLessThanOrEqual(40, strlen($code));
            $this->assertNotContains($code, $codes);
            $codes[] = $code;
        }
        $this->assertSame('nhom-topping', $codes[2]);
    }
}
