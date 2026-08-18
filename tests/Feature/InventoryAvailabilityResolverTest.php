<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\AcctInventoryWarehouseMapping;
use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Models\CatalogProduct;
use App\Models\Customer;
use App\Models\InvItem;
use App\Models\InvStockBalance;
use App\Models\InvWarehouse;
use App\Models\ModuleInstallation;
use App\Models\Order;
use App\Support\InventoryAvailabilityResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAvailabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_remains_authority_when_inventory_is_installed_but_disabled(): void
    {
        $this->bootModules(enableInventory: false);
        [$product, $item, $warehouse] = $this->inventoryFixture(catalogStock: 7, onHand: 2);

        $availability = app(InventoryAvailabilityResolver::class)->resolve($product);

        $this->assertSame('catalog', $availability['source']);
        $this->assertSame(7, $availability['quantity']);
        $this->assertNotNull($item);
        $this->assertNotNull($warehouse);
    }

    public function test_enabled_inventory_uses_available_balance_instead_of_catalog_stock(): void
    {
        $this->bootModules();
        [$product] = $this->inventoryFixture(catalogStock: 99, onHand: 8, reserved: 2);

        $availability = app(InventoryAvailabilityResolver::class)->resolve($product);

        $this->assertSame('inventory', $availability['source']);
        $this->assertSame(6, $availability['quantity']);
    }

    public function test_accounting_warehouse_mapping_prevents_cross_organization_stock_leakage(): void
    {
        $this->bootModules(enableAccounting: true);
        [$product, $item, $warehouseA] = $this->inventoryFixture(catalogStock: 99, onHand: 4);
        $warehouseB = InvWarehouse::query()->create([
            'code' => 'WH-B',
            'name' => 'Warehouse B',
            'is_active' => true,
        ]);
        InvStockBalance::query()->create([
            'warehouse_id' => $warehouseB->id,
            'location_id' => null,
            'location_key' => 0,
            'item_id' => $item->id,
            'batch_id' => null,
            'batch_key' => 0,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
        $organizationA = AcctOrganization::query()->create([
            'name' => 'Legal entity A',
            'status' => 'active',
        ]);
        $organizationB = AcctOrganization::query()->create([
            'name' => 'Legal entity B',
            'status' => 'active',
        ]);
        AcctOrganizationWebsite::query()->create([
            'organization_id' => $organizationA->id,
            'website_key' => 'website-main',
            'is_primary' => true,
        ]);
        AcctOrganizationWebsite::query()->create([
            'organization_id' => $organizationB->id,
            'website_key' => 'website-secondary',
            'is_primary' => true,
        ]);
        $this->mapWarehouse($organizationA, $warehouseA);
        $this->mapWarehouse($organizationB, $warehouseB);

        $resolver = app(InventoryAvailabilityResolver::class);
        $availability = $resolver->resolve($product, 'website-main');

        $this->assertSame(4, $availability['quantity']);
        $this->assertSame([$warehouseA->id], $availability['warehouse_ids']);
        $this->assertSame(0, $resolver->quantity($product, 'website-without-legal-entity'));
    }

    public function test_checkout_revalidates_inventory_after_item_was_added_to_cart(): void
    {
        $this->bootModules();
        [$product, , , $balance] = $this->inventoryFixture(catalogStock: 99, onHand: 5);
        $customer = Customer::factory()->create();

        $this->post(route('site.cart.add', ['slug' => $product->slug]), [
            'quantity' => 5,
        ])->assertRedirect();

        $balance->forceFill(['quantity_on_hand' => 2])->save();
        $this->actingAs($customer, 'customer');

        $this->from(route('site.checkout.index'))
            ->post(route('site.checkout.store'), [
                'customer_name' => $customer->name,
                'customer_phone' => '0900000000',
                'customer_email' => $customer->email,
                'delivery_address' => '1 Test Street',
                'payment_method' => 'cod',
            ])
            ->assertRedirect(route('site.checkout.index'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    private function bootModules(bool $enableInventory = true, bool $enableAccounting = false): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);

        $manager->install('catalog');
        $manager->enable('catalog');
        $manager->install('inventory');

        if ($enableInventory) {
            $manager->enable('inventory');
        }

        if ($enableAccounting) {
            $manager->install('accounting-tax');
            $manager->enable('accounting-tax');
        }

        $this->assertSame(
            $enableInventory ? 'enabled' : 'installed',
            ModuleInstallation::query()->where('key', 'inventory')->value('status'),
        );
    }

    /**
     * @return array{CatalogProduct, InvItem, InvWarehouse, InvStockBalance}
     */
    private function inventoryFixture(int $catalogStock, int $onHand, int $reserved = 0): array
    {
        $product = CatalogProduct::query()->create([
            'name' => 'Inventory authority product',
            'slug' => 'inventory-authority-product',
            'sku' => 'INV-AUTH-001',
            'price' => 125000,
            'stock' => $catalogStock,
            'is_active' => true,
            'website_key' => 'website-main',
        ]);
        $item = InvItem::query()->create([
            'catalog_product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => 'pcs',
            'is_active' => true,
        ]);
        $warehouse = InvWarehouse::query()->create([
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'is_active' => true,
        ]);
        $balance = InvStockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'location_id' => null,
            'location_key' => 0,
            'item_id' => $item->id,
            'batch_id' => null,
            'batch_key' => 0,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);

        return [$product, $item, $warehouse, $balance];
    }

    private function mapWarehouse(AcctOrganization $organization, InvWarehouse $warehouse): void
    {
        AcctInventoryWarehouseMapping::query()->create([
            'organization_id' => $organization->id,
            'inventory_warehouse_id' => $warehouse->id,
            'is_default' => true,
            'warehouse_snapshot' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ],
        ]);
    }
}
