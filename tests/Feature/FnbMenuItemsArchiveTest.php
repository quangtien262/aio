<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbMenuReleaseService;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Tests\TestCase;

class FnbMenuItemsArchiveTest extends TestCase
{
    use RefreshDatabase;

    private FnbContext $ctx;

    private FnbService $fnb;

    private array $items;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::findOrFail(1);
        $owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
        $this->fnb = app(FnbService::class);
        $onboard = $this->fnb->onboard(new FnbContext('website-main', 0, 1), [
            'outlet' => ['code' => 'ARCHIVE', 'name' => 'Archive fixture', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS', 'name' => 'POS', 'type' => 'pos'],
            'station' => ['code' => 'BAR', 'name' => 'Bar'], 'table_count' => 0,
        ], 'archive-onboard')['resource'];
        $this->ctx = new FnbContext('website-main', (int) $onboard['outlet']['id'], 1, (int) $onboard['terminal']['id']);
        $this->withHeaders(['X-FNB-Outlet' => $this->ctx->outletId, 'X-FNB-Terminal' => $this->ctx->terminalId]);
        $this->items = [];
        foreach (['A', 'B'] as $code) {
            $this->items[] = $this->fnb->saveCatalogItem($this->ctx, [
                'code' => $code, 'name' => 'Item '.$code, 'station_id' => (int) $onboard['station']['id'],
                'variants' => [['code' => 'DEFAULT', 'name' => 'Default', 'base_price_minor' => 30000, 'is_default' => true]],
            ], 'archive-item-'.$code)['resource'];
        }
    }

    private function payload(): array
    {
        return ['items' => array_map(fn ($item) => ['id' => $item['id'], 'expected_version' => $item['version']], $this->items)];
    }

    public function test_bulk_archive_preserves_order_and_publication_snapshots_and_replays_once(): void
    {
        $menu = DB::table('fnb_menus')->first();
        app(FnbMenuReleaseService::class)->publish($this->ctx, [
            'menu_id' => $menu->id, 'expected_version' => $menu->version, 'outlet_ids' => [$this->ctx->outletId],
        ], 'archive-publish');
        $publication = DB::table('fnb_menu_publications')->first();
        $day = $this->fnb->openBusinessDay($this->ctx, now('Asia/Ho_Chi_Minh')->toDateString(), 'archive-day')['resource'];
        $shift = $this->fnb->openShift($this->ctx, $day['id'], 0, 'archive-shift')['resource'];
        $session = $this->fnb->openSession($this->ctx, ['business_day_id' => $day['id'], 'service_type' => 'counter'], 'archive-session')['resource'];
        $order = $this->fnb->createOrder($this->ctx, $session['id'], $shift['id'], [], 'archive-order', $session['version'])['resource'];
        $this->fnb->addOrderLine($this->ctx, $order['id'], [
            'variant_id' => $this->items[0]['variants'][0]['id'], 'quantity' => '1.000000',
        ], 'archive-order-line', $order['version']);
        $orderLine = DB::table('fnb_order_lines')->first();

        $this->withHeader('Idempotency-Key', 'archive-selected-items')
            ->postJson('/admin/api/fnb/menu/items/archive', $this->payload())->assertOk()->assertJsonPath('data.count', 2);
        $this->postJson('/admin/api/fnb/menu/items/archive', $this->payload())
            ->assertOk()->assertJsonPath('meta.replayed', true);

        foreach ($this->items as $item) {
            $row = DB::table('fnb_menu_items')->find($item['id']);
            $this->assertNotNull($row->archived_at);
            $this->assertSame('inactive', $row->status);
            $this->assertSame($item['version'] + 1, (int) $row->version);
        }
        $this->assertDatabaseCount('fnb_item_variants', 2);
        $this->assertSame([], $this->fnb->catalog($this->ctx, ['draft' => true, 'include_inactive' => true])['resource']['items']);
        $this->assertCount(2, $this->fnb->catalog($this->ctx)['resource']['items']);
        $this->assertEquals($publication, DB::table('fnb_menu_publications')->first());
        $this->assertEquals($orderLine, DB::table('fnb_order_lines')->first());
        $this->assertSame(2, DB::table('fnb_outbox_events')->where('event_type', 'fnb.menu_item.archived')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'fnb.menu_items.archived')->count());
    }

    public function test_stale_version_rolls_back_the_entire_batch(): void
    {
        DB::table('fnb_menu_items')->where('id', $this->items[1]['id'])->increment('version');
        $this->withHeader('Idempotency-Key', 'archive-stale-items')
            ->postJson('/admin/api/fnb/menu/items/archive', $this->payload())->assertConflict();
        $this->assertSame(0, DB::table('fnb_menu_items')->whereNotNull('archived_at')->count());
        $this->assertSame(0, DB::table('fnb_outbox_events')->where('event_type', 'fnb.menu_item.archived')->count());
        $this->assertDatabaseMissing('fnb_idempotency_requests', ['idempotency_key' => 'archive-stale-items']);
    }

    public function test_cross_website_item_cannot_be_archived_or_cause_partial_deletion(): void
    {
        $foreignId = DB::table('fnb_menu_items')->insertGetId([
            'website_key' => 'other-website', 'public_id' => (string) Str::uuid(),
            'code' => 'FOREIGN', 'name' => 'Foreign item', 'status' => 'active', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = $this->payload();
        $payload['items'][1] = ['id' => $foreignId, 'expected_version' => 1];
        $this->withHeader('Idempotency-Key', 'archive-foreign-items')
            ->postJson('/admin/api/fnb/menu/items/archive', $payload)->assertNotFound();
        $this->assertSame(0, DB::table('fnb_menu_items')->whereNotNull('archived_at')->count());
    }

    public function test_outlet_manager_without_hq_master_permission_cannot_archive_or_replay(): void
    {
        $payload = $this->payload();
        $this->withHeader('Idempotency-Key', 'archive-owner-command')
            ->postJson('/admin/api/fnb/menu/items/archive', $payload)->assertOk();
        $staff = Admin::factory()->create(['must_change_password' => false]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(
            Admin::findOrFail(1), 'website-main', $this->ctx->outletId, $staff, 'fnb-manager',
        ));
        $staff = $staff->fresh();
        $this->actingAs($staff, 'admin')->withSession(['admin_auth_version' => $staff->auth_version]);
        $this->postJson('/admin/api/fnb/menu/items/archive', $payload)->assertForbidden();
        $this->withHeader('Idempotency-Key', 'archive-staff-command')
            ->postJson('/admin/api/fnb/menu/items/archive', $payload)->assertForbidden();
    }

    public function test_batch_requires_distinct_item_ids_and_a_version_for_each_item(): void
    {
        foreach ([['items' => []], ['items' => [$this->payload()['items'][0], $this->payload()['items'][0]]],
            ['items' => [['id' => $this->items[0]['id']]]]] as $index => $payload) {
            $this->withHeader('Idempotency-Key', 'archive-invalid-'.$index)
                ->postJson('/admin/api/fnb/menu/items/archive', $payload)->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('fnb_menu_items')->whereNotNull('archived_at')->count());
    }
}
