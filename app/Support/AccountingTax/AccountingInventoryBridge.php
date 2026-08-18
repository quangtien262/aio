<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctInventoryLink;
use App\Models\AcctItem;
use App\Models\AcctItemSource;
use App\Models\InvItem;
use App\Support\AuditLogger;
use App\Support\InventoryStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccountingInventoryBridge
{
    public function __construct(
        private readonly ModuleCapabilityService $capabilities,
        private readonly AccountingInventoryWarehouseService $warehouseMappings,
        private readonly InventoryStockService $inventory,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<int|string, array<string, mixed>> $tracking */
    public function propose(AcctDocument $document, int $warehouseId, array $tracking = []): AcctInventoryLink
    {
        $this->assertAvailable();
        $this->warehouseMappings->assertMapped((int) $document->organization_id, $warehouseId);
        if ($document->workflow_status !== 'posted' || $document->voided_at !== null) {
            throw ValidationException::withMessages(['document' => ['Chỉ chứng từ đã ghi sổ và còn hiệu lực mới được liên kết kho.']]);
        }

        $sign = (int) ($document->effect_sign ?? 1) < 0 ? -1 : 1;
        $inventoryType = match ([$document->direction, $sign]) {
            ['outbound', 1], ['inbound', -1] => 'issue',
            default => 'receipt',
        };
        $lines = [];

        foreach ($document->lines()->with('item')->get() as $line) {
            /** @var AcctItem|null $accountingItem */
            $accountingItem = $line->item;
            if ($accountingItem === null || ! $accountingItem->is_stock_tracked || $line->line_type !== 'item') {
                continue;
            }

            $source = AcctItemSource::query()
                ->where('organization_id', $document->organization_id)
                ->where('accounting_item_id', $accountingItem->id)
                ->where('source_module', 'catalog')
                ->where('source_type', 'catalog.product')
                ->first();
            $inventoryItem = $source
                ? InvItem::query()->where('catalog_product_id', $source->source_id)->first()
                : null;
            if ($inventoryItem === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Mặt hàng {$line->name} chưa được đồng bộ sang Inventory."],
                ]);
            }

            $lineTracking = $tracking[$line->id] ?? $tracking[(string) $line->id] ?? [];
            $lines[] = [
                'item_id' => $inventoryItem->id,
                'quantity' => (string) $line->quantity,
                'unit_cost' => $inventoryType === 'receipt' ? (string) $line->unit_price : '0.00',
                'source_location_id' => $lineTracking['source_location_id'] ?? null,
                'destination_location_id' => $lineTracking['destination_location_id'] ?? null,
                'batch_id' => $lineTracking['batch_id'] ?? null,
                'batch_code' => $lineTracking['batch_code'] ?? null,
                'expires_at' => $lineTracking['expires_at'] ?? null,
                'serial_numbers' => $lineTracking['serial_numbers'] ?? [],
                'note' => "Accounting line {$line->id}",
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => ['Chứng từ không có mặt hàng cần theo dõi tồn kho.']]);
        }

        $payload = [
            'type' => $inventoryType,
            'source_warehouse_id' => $inventoryType === 'issue' ? $warehouseId : null,
            'destination_warehouse_id' => $inventoryType === 'receipt' ? $warehouseId : null,
            'reference' => 'accounting-document-'.$document->id,
            'note' => "Tạo có xác nhận từ chứng từ kế toán {$document->document_no}.",
            'lines' => $lines,
        ];

        return DB::transaction(function () use ($document, $inventoryType, $payload): AcctInventoryLink {
            $lockedDocument = AcctDocument::query()->lockForUpdate()->findOrFail($document->id);
            $existing = AcctInventoryLink::query()->where('document_id', $lockedDocument->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($lockedDocument->workflow_status !== 'posted' || $lockedDocument->voided_at !== null) {
                throw ValidationException::withMessages([
                    'document' => ['Chứng từ không còn ở trạng thái có thể liên kết kho.'],
                ]);
            }

            $link = AcctInventoryLink::query()->create([
                'organization_id' => $lockedDocument->organization_id,
                'document_id' => $lockedDocument->id,
                'direction' => $inventoryType,
                'status' => 'proposed',
                'idempotency_key' => "accounting:{$lockedDocument->organization_id}:document:{$lockedDocument->id}:inventory:v1",
                'payload_snapshot' => $payload,
            ]);
            $lockedDocument->forceFill([
                'inventory_status' => 'draft',
                'version' => $lockedDocument->version + 1,
            ])->saveTrusted();
            $this->auditLogger->record(
                'accounting.inventory.proposed',
                $link,
                null,
                $link->toArray(),
                moduleKey: 'accounting-tax',
            );

            return $link;
        }, 3);
    }

    public function post(AcctInventoryLink $link, ?int $adminId): AcctInventoryLink
    {
        $this->assertAvailable();

        return DB::transaction(function () use ($link, $adminId): AcctInventoryLink {
            $locked = AcctInventoryLink::query()->lockForUpdate()->findOrFail($link->id);
            if ($locked->status === 'posted') {
                return $locked;
            }
            if ($locked->status !== 'proposed') {
                throw ValidationException::withMessages(['status' => ['Liên kết kho không ở trạng thái chờ ghi nhận.']]);
            }

            $warehouseId = (int) ($locked->direction === 'issue'
                ? data_get($locked->payload_snapshot, 'source_warehouse_id')
                : data_get($locked->payload_snapshot, 'destination_warehouse_id'));
            $this->warehouseMappings->assertMapped((int) $locked->organization_id, $warehouseId);

            $inventoryDocument = $this->inventory->createDocument($locked->payload_snapshot, $adminId);
            $locked->forceFill([
                'status' => 'posted',
                'inventory_document_id' => $inventoryDocument->id,
                'posted_at' => now(),
                'posted_by' => $adminId,
                'last_error' => null,
            ])->save();
            $accountingDocument = AcctDocument::query()->lockForUpdate()->findOrFail($locked->document_id);
            $accountingDocument->forceFill([
                'inventory_status' => 'posted',
                'version' => $accountingDocument->version + 1,
            ])->saveTrusted();
            $this->auditLogger->record(
                'accounting.inventory.posted',
                $locked,
                ['status' => 'proposed'],
                $locked->fresh()->toArray(),
                moduleKey: 'accounting-tax',
            );

            return $locked->fresh();
        }, 3);
    }

    private function assertAvailable(): void
    {
        if (! $this->capabilities->has('inventory', 'inventory.documents.write.v1')
            || ! Schema::hasTable('inv_stock_documents')) {
            throw ValidationException::withMessages([
                'inventory' => ['Module Inventory chưa được cài, bật hoặc chưa cung cấp capability ghi chứng từ.'],
            ]);
        }
    }
}
