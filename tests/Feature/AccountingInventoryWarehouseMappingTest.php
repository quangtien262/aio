<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\AcctDocument;
use App\Models\AcctItem;
use App\Models\AcctItemSource;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\InvItem;
use App\Models\InvWarehouse;
use App\Support\AccountingTax\AccountingInventoryBridge;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingInventoryWarehouseMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_api_returns_friendly_available_warehouses_and_enforces_exclusive_ownership(): void
    {
        $this->seed(DatabaseSeeder::class);
        $modules = app(ModuleManager::class);
        $modules->install('catalog');
        $modules->enable('catalog');
        $modules->install('inventory');
        $modules->enable('inventory');
        $modules->install('accounting-tax');
        $modules->enable('accounting-tax');

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
        $main = InvWarehouse::query()->where('code', 'MAIN')->firstOrFail();
        $east = InvWarehouse::query()->create([
            'code' => 'EAST',
            'name' => 'Kho miền Đông',
            'address' => 'Thành phố Hồ Chí Minh',
            'is_active' => true,
        ]);
        $inactive = InvWarehouse::query()->create([
            'code' => 'CLOSED',
            'name' => 'Kho ngừng hoạt động',
            'is_active' => false,
        ]);

        $this->actingAs(Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID), 'admin');

        $mappingA = $this->postJson('/admin/api/accounting-tax/inventory/warehouses', [
            'organization_id' => $organizationA->id,
            'warehouse_id' => $main->id,
            'is_default' => true,
        ])->assertCreated()
            ->assertJsonPath('data.warehouse.code', 'MAIN')
            ->assertJsonPath('data.warehouse.name', 'Main Warehouse')
            ->json('data');

        $this->getJson("/admin/api/accounting-tax/inventory/warehouses?organization_id={$organizationA->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $mappingA['id'])
            ->assertJsonPath('data.items.0.is_default', true)
            ->assertJsonPath('data.available_items.0.id', $east->id)
            ->assertJsonPath('data.available_items.0.code', 'EAST')
            ->assertJsonMissing(['id' => $inactive->id]);

        $this->postJson('/admin/api/accounting-tax/inventory/warehouses', [
            'organization_id' => $organizationB->id,
            'warehouse_id' => $main->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['warehouse_id']);

        $this->postJson('/admin/api/accounting-tax/inventory/warehouses', [
            'organization_id' => $organizationB->id,
            'warehouse_id' => $east->id,
            'is_default' => true,
        ])->assertCreated();

        $this->getJson("/admin/api/accounting-tax/inventory/warehouses?organization_id={$organizationA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(0, 'data.available_items');

        $product = CatalogProduct::query()->create([
            'name' => 'Inventory bridge product',
            'slug' => 'inventory-bridge-product',
            'sku' => 'INV-BRIDGE-1',
            'price' => '100.00',
            'stock' => 0,
            'website_key' => 'website-main',
            'is_active' => true,
        ]);
        $inventoryItem = InvItem::query()->create([
            'catalog_product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => 'pcs',
            'is_active' => true,
        ]);
        $accountingItem = AcctItem::query()->create([
            'organization_id' => $organizationA->id,
            'kind' => 'goods',
            'name' => $inventoryItem->name,
            'sku' => $inventoryItem->sku,
            'unit' => $inventoryItem->unit,
            'is_stock_tracked' => true,
            'status' => 'active',
        ]);
        AcctItemSource::query()->create([
            'organization_id' => $organizationA->id,
            'accounting_item_id' => $accountingItem->id,
            'source_module' => 'catalog',
            'source_type' => 'catalog.product',
            'source_id' => (string) $inventoryItem->catalog_product_id,
            'source_hash' => str_repeat('a', 64),
        ]);
        $document = AcctDocument::query()->create([
            'organization_id' => $organizationA->id,
            'direction' => 'inbound',
            'document_type' => 'internal_invoice',
            'document_no' => 'INVENTORY-MAPPING-1',
            'document_date' => '2026-08-17',
            'currency' => 'VND',
            'base_currency' => 'VND',
            'workflow_status' => 'draft',
            'effect_sign' => 1,
            'version' => 1,
        ]);
        $document->lines()->create([
            'accounting_item_id' => $accountingItem->id,
            'line_type' => 'item',
            'sort_order' => 0,
            'item_kind' => 'goods',
            'name' => $accountingItem->name,
            'sku' => $accountingItem->sku,
            'unit' => $accountingItem->unit,
            'quantity' => '1.0000',
            'unit_price' => '100.00',
            'line_subtotal' => '100.00',
            'discount_amount' => '0.00',
            'tax_category' => 'standard',
            'tax_rate' => '10.00',
            'tax_base' => '100.00',
            'tax_amount' => '10.00',
            'line_total' => '110.00',
        ]);
        $document->forceFill(['workflow_status' => 'posted', 'posted_at' => now()])->save();
        $bridge = app(AccountingInventoryBridge::class);
        $link = $bridge->propose($document, $main->id);
        $this->assertSame('proposed', $link->status);

        $this->deleteJson("/admin/api/accounting-tax/inventory/warehouses/{$mappingA['id']}")
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->getJson("/admin/api/accounting-tax/inventory/warehouses?organization_id={$organizationA->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.available_items.0.id', $main->id)
            ->assertJsonPath('data.available_items.0.code', 'MAIN');

        try {
            $bridge->post($link, Admin::SYSTEM_OWNER_ID);
            $this->fail('Posting must recheck warehouse ownership after a mapping is removed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('warehouse_id', $exception->errors());
        }

        $this->postJson('/admin/api/accounting-tax/inventory/warehouses', [
            'organization_id' => $organizationA->id,
            'warehouse_id' => $main->id,
            'is_default' => true,
        ])->assertCreated();
        $posted = $bridge->post($link->fresh(), Admin::SYSTEM_OWNER_ID);
        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->inventory_document_id);
        $this->assertSame('posted', $document->fresh()->inventory_status);
    }
}
