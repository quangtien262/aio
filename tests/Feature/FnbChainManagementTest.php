<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\VersionedModuleSecuritySynchronizer;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Security\FnbVersionedSecurity;
use Modules\FnbPos\Services\FnbChainService;
use Modules\FnbPos\Services\FnbMenuReleaseService;
use Modules\FnbPos\Services\FnbPricingService;
use Modules\FnbPos\Services\FnbRecipeService;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\FnbStaffCandidateService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbScopedStaffService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class FnbChainManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private int $outlet;

    private int $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::findOrFail(1);
        $this->owner->forceFill(['password' => 'FnbPassword123!', 'must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
        $data = app(FnbService::class)->onboard($this->hq(), ['outlet' => ['code' => 'A', 'name' => 'Quán A', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS', 'name' => 'POS'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'sample_menu' => true], 'chain-test-onboard')['resource'];
        $this->outlet = (int) $data['outlet']['id'];
        $this->terminal = (int) $data['terminal']['id'];
    }

    public function test_provisioning_second_outlet_is_idempotent_scoped_and_requires_bound_hq_reauth(): void
    {
        $input = ['code' => 'B', 'name' => 'Quán B', 'table_count' => 3];
        $key = (string) Str::uuid();
        $challenge = $this->withHeader('Idempotency-Key', $key)->postJson('/admin/api/fnb/chain/outlets', $input)->assertStatus(423)->json('details');
        $proof = $this->postJson('/admin/api/fnb/reauth/proofs', array_intersect_key($challenge, array_flip(['action', 'subject', 'payload_hash'])) + ['password' => 'FnbPassword123!'])->assertOk()->json('data.reauth_proof');
        $result = $this->withHeader('X-FNB-Reauth-Proof', $proof)->postJson('/admin/api/fnb/chain/outlets', $input)->assertOk()->json('data');
        $this->postJson('/admin/api/fnb/chain/outlets', $input)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertDatabaseCount('fnb_outlets', 2);
        $this->assertCount(1, $result['terminals']);
        $this->assertSame(3, DB::table('fnb_dining_tables')->where('outlet_id', $result['outlet']['id'])->count());
        $this->assertGreaterThan(0, DB::table('fnb_item_station_routes')->where('outlet_id', $result['outlet']['id'])->count());
        $foreignRegion = DB::table('fnb_regions')->insertGetId(['website_key' => 'website-other', 'code' => 'R', 'name' => 'Foreign', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        try {
            app(FnbChainService::class)->save($this->hq(), 'outlet', null, ['code' => 'C', 'name' => 'C', 'region_id' => $foreignRegion], 'chain-foreign-region');
            $this->fail('Cross-website region must fail.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_same_staff_can_be_manager_in_a_and_cashier_in_b_without_hq_master_privileges(): void
    {
        $second = $this->second();
        $staff = Admin::factory()->create(['must_change_password' => false]);
        $assign = app(FnbStaffAssignmentService::class);
        $a = DB::transaction(fn () => $assign->assign($this->owner, 'website-main', $this->outlet, $staff, 'fnb-manager'));
        $b = DB::transaction(fn () => $assign->assign($this->owner, 'website-main', $second, $staff, 'fnb-cashier'));
        $access = app(FnbOutletAccessService::class);
        $actor = $staff->fresh();
        $access->authorize($actor, 'website-main', $this->outlet, 'fnb.staff.assign');
        $access->authorize($actor, 'website-main', $second, 'fnb.payment.collect');
        $this->assertContains('fnb.staff.assign', $access->permissionsForOutlet($actor, 'website-main', $this->outlet));
        $this->assertNotContains('fnb.staff.assign', $access->permissionsForOutlet($actor, 'website-main', $second));
        $this->assertNotContains('fnb.menu.manage', $access->permissionsForOutlet($actor, 'website-main', $this->outlet));
        $this->actingAs($actor, 'admin')->withSession(['admin_auth_version' => $actor->auth_version]);
        $this->getJson('/admin/api/fnb/chain/bootstrap')->assertForbidden();
        $this->withHeader('X-FNB-Outlet', (string) $this->outlet)->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/staff')->assertOk()->assertJsonFragment(['role_key' => 'fnb-manager', 'name' => $actor->name]);
        DB::transaction(fn () => $assign->revoke($this->owner, 'website-main', $second, $staff->fresh(), $b['binding_version'], $b['membership_version']));
        $this->assertContains($this->outlet, $access->accessibleOutletIds($staff->fresh(), 'website-main', 'fnb.order.view'));
        $this->assertNotContains($second, $access->accessibleOutletIds($staff->fresh(), 'website-main', 'fnb.order.view'));
        $this->assertSame(1, $a['binding_version']);
    }

    public function test_outlet_audit_requires_outlet_membership_and_does_not_expose_other_outlet_logs(): void
    {
        $second = $this->second();
        $manager = Admin::factory()->create(['must_change_password' => false]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($this->owner, 'website-main', $this->outlet, $manager, 'fnb-manager'));
        $this->actingAs($manager->fresh(), 'admin')->withSession(['admin_auth_version' => $manager->fresh()->auth_version]);
        $this->getJson('/admin/api/fnb/chain/audit?outlet_id='.$this->outlet)->assertOk();
        $this->getJson('/admin/api/fnb/chain/audit?outlet_id='.$second)->assertForbidden();
        $this->getJson('/admin/api/fnb/chain/audit')->assertForbidden();
    }

    public function test_menu_release_freezes_prices_modifiers_and_catalog_until_rollout_or_rollback(): void
    {
        $menus = app(FnbMenuReleaseService::class);
        $imageItemId = (int) DB::table('fnb_menu_items')->orderBy('id')->value('id');
        $images = ['https://example.test/menu-a.png', 'https://example.test/menu-b.png'];
        DB::table('fnb_menu_items')->where('id', $imageItemId)->update(['image_url' => $images[1], 'gallery_images' => json_encode($images)]);
        $menuId = (int) DB::table('fnb_menus')->value('id');
        $publication = $menus->publish($this->hq(), ['menu_id' => $menuId, 'expected_version' => 1, 'outlet_ids' => [$this->outlet]], 'chain-menu-release-1')['resource'];
        $variant = DB::table('fnb_item_variants')->orderBy('id')->first();
        $ctx = $this->at();
        $before = app(FnbPricingService::class)->priceLine($ctx, $variant->id, '1');
        DB::table('fnb_item_variants')->where('id', $variant->id)->update(['base_price_minor' => 1000]);
        $this->assertSame($before['original_unit_price_minor'], app(FnbPricingService::class)->priceLine($ctx, $variant->id, '1')['original_unit_price_minor']);
        $published = app(FnbService::class)->catalog($ctx)['resource'];
        $this->assertSame($publication['id'], $published['publication_id']);
        $publishedImageItem = collect($published['items'])->firstWhere('id', $imageItemId);
        $this->assertSame($images, $publishedImageItem['gallery_images']);
        $this->assertSame($images[1], $publishedImageItem['image_url']);
        DB::table('fnb_menu_items')->where('id', $imageItemId)->update(['image_url' => $images[0], 'gallery_images' => json_encode([$images[0]])]);
        $frozen = collect(app(FnbService::class)->catalog($ctx)['resource']['items'])->firstWhere('id', $imageItemId);
        $this->assertSame($images, $frozen['gallery_images']);
        $this->assertSame($images[1], $frozen['image_url']);
        $menus->publish($this->hq(), ['menu_id' => $menuId, 'expected_version' => 2, 'outlet_ids' => [$this->outlet]], 'chain-menu-release-2');
        $this->assertSame(1000, app(FnbPricingService::class)->priceLine($ctx, $variant->id, '1')['original_unit_price_minor']);
        $menus->rollback($this->hq(), $publication['id'], ['outlet_ids' => [$this->outlet], 'reason' => 'Sai bảng giá'], 'chain-menu-rollback');
        $this->assertSame($before['original_unit_price_minor'], app(FnbPricingService::class)->priceLine($ctx, $variant->id, '1')['original_unit_price_minor']);
        $this->assertDatabaseCount('fnb_menu_publications', 2);
    }

    public function test_publication_freezes_recipe_revision_and_modifier_rules_until_next_rollout(): void
    {
        $variant = DB::table('fnb_item_variants')->orderBy('id')->first();
        $base = ['website_key' => 'website-main', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
        $ingredient = DB::table('fnb_ingredients')->insertGetId($base + ['code' => 'CHAIN-MILK', 'name' => 'Sữa', 'base_unit' => 'ml']);
        $group = DB::table('fnb_modifier_groups')->insertGetId($base + ['code' => 'CHAIN-EXTRA', 'name' => 'Thêm sữa', 'min_select' => 0, 'max_select' => 1]);
        $option = DB::table('fnb_modifier_options')->insertGetId($base + ['group_id' => $group, 'code' => 'EXTRA-MILK', 'name' => 'Sữa thêm', 'base_price_delta_minor' => 2000]);
        DB::table('fnb_item_modifier_groups')->insert(['website_key' => 'website-main', 'item_id' => $variant->item_id, 'group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);
        $recipes = app(FnbRecipeService::class);
        $recipeInput = ['code' => 'CHAIN-RECIPE', 'name' => 'Cà phê sữa chuẩn', 'variant_id' => $variant->id, 'expected_recipe_version' => 0, 'yield_quantity' => '1', 'yield_unit' => 'cup', 'lines' => [['ingredient_id' => $ingredient, 'quantity' => '100', 'unit' => 'ml']]];
        $firstRecipe = $recipes->publish($this->at(), $recipeInput, 'chain-recipe-v1')['resource'];
        $menus = app(FnbMenuReleaseService::class);
        $menuId = (int) DB::table('fnb_menus')->value('id');
        $menus->publish($this->hq(), ['menu_id' => $menuId, 'expected_version' => 1, 'outlet_ids' => [$this->outlet]], 'chain-freeze-recipe-menu-1');
        $recipeInput['expected_recipe_version'] = 1;
        $recipeInput['lines'][0]['quantity'] = '200';
        $secondRecipe = $recipes->publish($this->at(), $recipeInput, 'chain-recipe-v2')['resource'];
        DB::table('fnb_modifier_options')->where('id', $option)->update(['base_price_delta_minor' => 9000]);
        $oldLine = app(FnbPricingService::class)->priceLine($this->at(), $variant->id, '1', [$option]);
        $this->assertSame($firstRecipe['id'], $oldLine['recipe_id']);
        $this->assertSame(2000, $oldLine['modifiers'][0]['unit_price_delta_minor']);
        $menus->publish($this->hq(), ['menu_id' => $menuId, 'expected_version' => 2, 'outlet_ids' => [$this->outlet]], 'chain-freeze-recipe-menu-2');
        $newLine = app(FnbPricingService::class)->priceLine($this->at(), $variant->id, '1', [$option]);
        $this->assertSame($secondRecipe['id'], $newLine['recipe_id']);
        $this->assertSame(9000, $newLine['modifiers'][0]['unit_price_delta_minor']);
    }

    public function test_price_books_apply_only_to_selected_outlets_and_respect_schedule_without_price_gap(): void
    {
        $second = $this->second();
        $variant = DB::table('fnb_item_variants')->orderBy('id')->first();
        $menus = app(FnbMenuReleaseService::class);
        $menus->priceBook($this->hq(), ['code' => 'REGION-A', 'name' => 'Giá A', 'currency' => 'VND', 'outlet_ids' => [$this->outlet], 'prices' => [['variant_id' => $variant->id, 'amount_minor' => 35000]]], 'chain-pricebook-a');
        $this->assertSame(35000, app(FnbPricingService::class)->priceLine($this->at(), $variant->id, '1')['original_unit_price_minor']);
        $this->assertSame((int) $variant->base_price_minor, app(FnbPricingService::class)->priceLine(new FnbContext('website-main', $second, $this->owner->id), $variant->id, '1')['original_unit_price_minor']);
        $menus->priceBook($this->hq(), ['code' => 'REGION-A-NEXT', 'name' => 'Giá ngày mai', 'currency' => 'VND', 'outlet_ids' => [$this->outlet], 'valid_from' => now()->addDay()->toDateTimeString(), 'prices' => [['variant_id' => $variant->id, 'amount_minor' => 39000]]], 'chain-pricebook-next');
        $this->assertSame(35000, app(FnbPricingService::class)->priceLine($this->at(), $variant->id, '1')['original_unit_price_minor']);
        $this->travel(2)->days();
        $this->assertSame(39000, app(FnbPricingService::class)->priceLine($this->at(), $variant->id, '1')['original_unit_price_minor']);
    }

    public function test_hq_assignment_is_reauth_bound_and_hq_manager_cannot_grant_hq_or_remove_last_owner(): void
    {
        $target = Admin::factory()->create(['must_change_password' => false]);
        $data = ['identifier' => $target->email, 'role_key' => 'fnb-hq-manager'];
        $this->withHeader('Idempotency-Key', 'chain-test-hq-grant');
        $challenge = $this->postJson('/admin/api/fnb/chain/staff', $data)->assertStatus(423)->json('details');
        $proof = $this->postJson('/admin/api/fnb/reauth/proofs', array_intersect_key($challenge, array_flip(['action', 'subject', 'payload_hash'])) + ['password' => 'FnbPassword123!'])->assertOk()->json('data.reauth_proof');
        $this->withHeader('X-FNB-Reauth-Proof', $proof)->postJson('/admin/api/fnb/chain/staff', $data)->assertOk()->assertJsonPath('data.role_key', 'fnb-hq-manager');
        $hq = $target->fresh();
        $this->actingAs($hq, 'admin')->withSession(['admin_auth_version' => $hq->auth_version]);
        $this->getJson('/admin/api/fnb/chain/bootstrap')->assertOk();
        $this->postJson('/admin/api/fnb/chain/staff', ['identifier' => $target->email, 'role_key' => 'fnb-owner', 'expected_version' => 1])->assertForbidden();
        $owner = Admin::factory()->create();
        $staff = app(FnbScopedStaffService::class);
        $scope = DB::transaction(fn () => $staff->assignHq($this->owner, 'website-main', $owner, 'fnb-owner', null));
        try {
            DB::transaction(fn () => $staff->assignHq($owner->fresh(), 'website-main', $owner->fresh(), 'fnb-hq-manager', $scope['version']));
            $this->fail('Last HQ owner cannot be downgraded.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }
        try {
            DB::transaction(fn () => $staff->revokeHq($owner->fresh(), 'website-main', $scope['id'], $scope['version']));
            $this->fail('Last HQ owner cannot be removed.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_revoked_outlet_staff_can_be_resolved_and_reassigned_with_current_versions_without_escalating_hq(): void
    {
        $staff = app(FnbStaffAssignmentService::class);
        $target = Admin::factory()->create();
        $first = DB::transaction(fn () => $staff->assign($this->owner, 'website-main', $this->outlet, $target, 'fnb-cashier', terminalIds: [$this->terminal]));
        $revoked = DB::transaction(fn () => $staff->revoke($this->owner, 'website-main', $this->outlet, $target->fresh(), $first['binding_version'], $first['membership_version']));
        $candidates = app(FnbStaffCandidateService::class);
        $candidate = $candidates->resolve($this->owner, $this->at(), 'review-regrant-session', $target->email, '127.0.0.1')['candidate'];
        $this->assertSame($revoked['binding_version'], $candidate['expected_binding_version']);
        $this->assertSame($revoked['membership_version'], $candidate['expected_membership_version']);
        $this->assertSame([], $candidate['terminal_ids']);
        $reassigned = DB::transaction(fn () => $staff->assign($this->owner, 'website-main', $this->outlet, $target->fresh(), 'fnb-waiter', $candidate['expected_binding_version'], $candidate['expected_membership_version']));
        $this->assertTrue($reassigned['active']);
        $manager = Admin::factory()->create();
        DB::transaction(fn () => $staff->assign($this->owner, 'website-main', $this->outlet, $manager, 'fnb-manager'));
        $hq = Admin::factory()->create();
        DB::transaction(fn () => app(FnbScopedStaffService::class)->assignHq($this->owner, 'website-main', $hq, 'fnb-hq-manager', null));
        $managerCtx = new FnbContext('website-main', $this->outlet, $manager->id, $this->terminal);
        $this->assertNull($candidates->resolve($manager->fresh(), $managerCtx, 'manager-candidate-session', $hq->email, '127.0.0.1')['candidate']);
        $this->expectException(AuthorizationException::class);
        DB::transaction(fn () => $staff->assign($manager->fresh(), 'website-main', $this->outlet, $hq->fresh(), 'fnb-waiter'));
    }

    public function test_upgrade_preserves_legacy_memberships_and_backfills_scopes_under_exact_new_map(): void
    {
        $legacy = app(FnbVersionedSecurity::class)->definition('0.1.0-dev.1');
        $this->assertCount(53, $legacy->permissions);
        $this->assertCount(8, $legacy->roles);
        $module = json_decode(file_get_contents(base_path('modules/FnbPos/module.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(fn () => app(VersionedModuleSecuritySynchronizer::class)->synchronize($module, '0.1.0-dev.1'));
        ModuleInstallation::where('key', 'fnb-pos')->update(['version' => '0.1.0-dev.1']);
        $manager = Admin::factory()->create();
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($this->owner, 'website-main', $this->outlet, $manager, 'fnb-manager'));
        $this->assertDatabaseCount('fnb_staff_role_scopes', 0);
        app(ModuleManager::class)->upgrade('fnb-pos');
        $this->assertSame('0.2.0-dev.3', ModuleInstallation::where('key', 'fnb-pos')->value('version'));
        $this->assertDatabaseHas('fnb_staff_role_scopes', ['admin_id' => $manager->id, 'scope_type' => 'outlet', 'scope_key' => $this->outlet]);
        app(FnbOutletAccessService::class)->authorize($manager->fresh(), 'website-main', $this->outlet, 'fnb.order.create');
        $this->assertNotContains('fnb.menu.manage', app(FnbOutletAccessService::class)->permissionsForOutlet($manager->fresh(), 'website-main', $this->outlet));
    }

    private function second(): int
    {
        return app(FnbChainService::class)->save($this->hq(), 'outlet', null, ['code' => 'B', 'name' => 'Quán B'], 'chain-create-b')['resource']['outlet']['id'];
    }

    private function hq(): FnbContext
    {
        return new FnbContext('website-main', 0, $this->owner->id);
    }

    private function at(): FnbContext
    {
        return new FnbContext('website-main', $this->outlet, $this->owner->id, $this->terminal);
    }
}
