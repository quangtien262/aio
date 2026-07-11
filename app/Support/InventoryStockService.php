<?php

namespace App\Support;

use App\Models\InvBatch;
use App\Models\InvCostLayer;
use App\Models\InvItem;
use App\Models\InvSerialNumber;
use App\Models\InvStockBalance;
use App\Models\InvStockDocument;
use App\Models\InvStockDocumentLine;
use App\Models\InvStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function createOpeningBalance(int $itemId, int $warehouseId, float $quantity, float $unitCost = 0, ?int $adminId = null, ?string $reference = null): InvStockDocument
    {
        return $this->createDocument([
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouseId,
            'reference' => $reference ?? 'opening-balance',
            'note' => 'Opening balance from product sync.',
            'lines' => [[
                'item_id' => $itemId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]],
        ], $adminId);
    }

    public function createDocument(array $payload, ?int $adminId = null): InvStockDocument
    {
        return DB::transaction(function () use ($payload, $adminId): InvStockDocument {
            $type = $payload['type'];
            $document = InvStockDocument::query()->create([
                'code' => $payload['code'] ?? $this->nextCode($type),
                'type' => $type,
                'status' => 'posted',
                'source_warehouse_id' => $payload['source_warehouse_id'] ?? null,
                'destination_warehouse_id' => $payload['destination_warehouse_id'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'note' => $payload['note'] ?? null,
                'created_by_admin_id' => $adminId,
                'posted_at' => now(),
            ]);

            foreach ($payload['lines'] as $linePayload) {
                $line = $document->lines()->create([
                    'item_id' => $linePayload['item_id'],
                    'source_location_id' => $linePayload['source_location_id'] ?? null,
                    'destination_location_id' => $linePayload['destination_location_id'] ?? null,
                    'batch_id' => $linePayload['batch_id'] ?? null,
                    'batch_code' => $linePayload['batch_code'] ?? null,
                    'expires_at' => $linePayload['expires_at'] ?? null,
                    'serial_numbers' => $linePayload['serial_numbers'] ?? [],
                    'quantity' => $linePayload['quantity'],
                    'unit_cost' => $linePayload['unit_cost'] ?? 0,
                    'note' => $linePayload['note'] ?? null,
                ]);

                $this->postLine($document, $line, $adminId);
            }

            return $document->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item']);
        });
    }

    private function postLine(InvStockDocument $document, InvStockDocumentLine $line, ?int $adminId): void
    {
        $quantity = (float) $line->quantity;
        $this->ensureTrackingRules($line);
        $this->resolveLineBatch($line, in_array($document->type, ['receipt', 'adjustment'], true));

        match ($document->type) {
            'receipt' => $this->applyInbound($document, $line, (int) $document->destination_warehouse_id, $quantity, $adminId),
            'issue' => $this->applyOutbound($document, $line, (int) $document->source_warehouse_id, $quantity, $adminId),
            'adjustment' => $this->applyAdjustment($document, $line, (int) ($document->destination_warehouse_id ?: $document->source_warehouse_id), $quantity, $adminId),
            'transfer' => $this->postTransfer($document, $line, $quantity, $adminId),
            default => throw ValidationException::withMessages(['type' => ['Loai phieu kho khong hop le.']]),
        };
    }

    private function postTransfer(InvStockDocument $document, InvStockDocumentLine $line, float $quantity, ?int $adminId): void
    {
        $unitCost = $this->applyOutbound($document, $line, (int) $document->source_warehouse_id, $quantity, $adminId);

        if ((float) $line->unit_cost <= 0 && $unitCost > 0) {
            $line->forceFill(['unit_cost' => $unitCost])->save();
        }

        $this->applyInbound($document, $line, (int) $document->destination_warehouse_id, $quantity, $adminId);
    }

    private function applyInbound(InvStockDocument $document, InvStockDocumentLine $line, int $warehouseId, float $quantity, ?int $adminId, bool $createCostLayer = true): void
    {
        $this->applyMovement($document, $line, $warehouseId, $line->destination_location_id, $quantity, $adminId);
        $this->receiveSerialNumbers($line, $warehouseId, $document->type === 'transfer');

        if ($createCostLayer && $quantity > 0) {
            InvCostLayer::query()->create([
                'item_id' => $line->item_id,
                'warehouse_id' => $warehouseId,
                'batch_id' => $line->batch_id,
                'document_line_id' => $line->id,
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'unit_cost' => $line->unit_cost ?? 0,
                'received_at' => now(),
            ]);
        }
    }

    private function applyOutbound(InvStockDocument $document, InvStockDocumentLine $line, int $warehouseId, float $quantity, ?int $adminId): float
    {
        $this->applyMovement($document, $line, $warehouseId, $line->source_location_id, -1 * $quantity, $adminId);
        $this->consumeSerialNumbers($line);

        return $this->consumeCostLayers($line, $warehouseId, $quantity);
    }

    private function applyAdjustment(InvStockDocument $document, InvStockDocumentLine $line, int $warehouseId, float $quantityDelta, ?int $adminId): void
    {
        $locationId = $quantityDelta < 0 ? $line->source_location_id : $line->destination_location_id;
        $this->applyMovement($document, $line, $warehouseId, $locationId, $quantityDelta, $adminId);

        if ($quantityDelta > 0) {
            $this->receiveSerialNumbers($line, $warehouseId, false);
            InvCostLayer::query()->create([
                'item_id' => $line->item_id,
                'warehouse_id' => $warehouseId,
                'batch_id' => $line->batch_id,
                'document_line_id' => $line->id,
                'quantity_received' => $quantityDelta,
                'quantity_remaining' => $quantityDelta,
                'unit_cost' => $line->unit_cost ?? 0,
                'received_at' => now(),
            ]);
            return;
        }

        if ($quantityDelta < 0) {
            $this->consumeSerialNumbers($line);
            $this->consumeCostLayers($line, $warehouseId, abs($quantityDelta));
        }
    }

    private function applyMovement(InvStockDocument $document, InvStockDocumentLine $line, int $warehouseId, ?int $locationId, float $quantityDelta, ?int $adminId): void
    {
        if ($warehouseId <= 0) {
            throw ValidationException::withMessages(['warehouse' => ['Phieu kho thieu kho nguon hoac kho dich.']]);
        }

        $locationKey = $locationId ?: 0;
        $batchKey = $line->batch_id ?: 0;
        $balance = InvStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('location_key', $locationKey)
            ->where('item_id', $line->item_id)
            ->where('batch_key', $batchKey)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = InvStockBalance::query()->create([
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'location_key' => $locationKey,
                'item_id' => $line->item_id,
                'batch_id' => $line->batch_id,
                'batch_key' => $batchKey,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]);
        }

        $nextQuantity = (float) $balance->quantity_on_hand + $quantityDelta;

        if ($nextQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Ton kho khong du de thuc hien phieu nay.'],
            ]);
        }

        $balance->forceFill([
            'quantity_on_hand' => $nextQuantity,
            'last_movement_at' => now(),
        ])->save();

        InvStockMovement::query()->create([
            'item_id' => $line->item_id,
            'batch_id' => $line->batch_id,
            'serial_number_id' => null,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'document_id' => $document->id,
            'document_line_id' => $line->id,
            'type' => $document->type,
            'quantity_delta' => $quantityDelta,
            'balance_after' => $nextQuantity,
            'unit_cost' => $line->unit_cost ?? 0,
            'reference' => $document->reference,
            'note' => $line->note ?? $document->note,
            'created_by_admin_id' => $adminId,
        ]);
    }

    private function ensureTrackingRules(InvStockDocumentLine $line): void
    {
        $item = InvItem::query()->findOrFail($line->item_id);
        $serialNumbers = $this->normalizeSerialNumbers($line->serial_numbers ?? []);

        if ($item->track_serial && count($serialNumbers) !== abs((int) $line->quantity)) {
            throw ValidationException::withMessages([
                'serial_numbers' => ['Hang hoa track serial phai co so serial bang so luong.'],
            ]);
        }

        if ($item->track_batch && blank($line->batch_id) && blank($line->batch_code)) {
            throw ValidationException::withMessages([
                'batch_code' => ['Hang hoa track batch phai co batch/lot.'],
            ]);
        }
    }

    private function resolveLineBatch(InvStockDocumentLine $line, bool $createWhenMissing): void
    {
        if ($line->batch_id || blank($line->batch_code)) {
            return;
        }

        $batch = $createWhenMissing
            ? InvBatch::query()->firstOrCreate([
                'item_id' => $line->item_id,
                'batch_code' => $line->batch_code,
            ], [
                'expires_at' => $line->expires_at,
                'is_active' => true,
            ])
            : InvBatch::query()
                ->where('item_id', $line->item_id)
                ->where('batch_code', $line->batch_code)
                ->first();

        if (! $batch) {
            throw ValidationException::withMessages([
                'batch_code' => ['Khong tim thay batch/lot de xuat kho.'],
            ]);
        }

        $line->forceFill(['batch_id' => $batch->id])->save();
    }

    private function receiveSerialNumbers(InvStockDocumentLine $line, int $warehouseId, bool $allowIssuedTransfer): void
    {
        foreach ($this->normalizeSerialNumbers($line->serial_numbers ?? []) as $serialNumber) {
            $existing = InvSerialNumber::query()->where('serial_number', $serialNumber)->first();

            if ($existing && ! ($allowIssuedTransfer && $existing->status === 'issued' && (int) $existing->item_id === (int) $line->item_id)) {
                throw ValidationException::withMessages([
                    'serial_numbers' => ["Serial {$serialNumber} da ton tai."],
                ]);
            }

            ($existing ?? new InvSerialNumber(['serial_number' => $serialNumber]))->forceFill([
                'item_id' => $line->item_id,
                'batch_id' => $line->batch_id,
                'warehouse_id' => $warehouseId,
                'status' => 'in_stock',
                'received_at' => $existing?->received_at ?? now(),
                'issued_at' => null,
            ])->save();
        }
    }

    private function consumeSerialNumbers(InvStockDocumentLine $line): void
    {
        foreach ($this->normalizeSerialNumbers($line->serial_numbers ?? []) as $serialNumber) {
            $serial = InvSerialNumber::query()
                ->where('item_id', $line->item_id)
                ->where('serial_number', $serialNumber)
                ->where('status', 'in_stock')
                ->first();

            if (! $serial) {
                throw ValidationException::withMessages([
                    'serial_numbers' => ["Serial {$serialNumber} khong co trong kho."],
                ]);
            }

            $serial->forceFill([
                'status' => 'issued',
                'issued_at' => now(),
            ])->save();
        }
    }

    private function consumeCostLayers(InvStockDocumentLine $line, int $warehouseId, float $quantity): float
    {
        $remaining = $quantity;
        $consumedValue = 0.0;
        $layers = InvCostLayer::query()
            ->where('item_id', $line->item_id)
            ->where('warehouse_id', $warehouseId)
            ->when($line->batch_id, fn ($query) => $query->where('batch_id', $line->batch_id))
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consume = min((float) $layer->quantity_remaining, $remaining);
            $consumedValue += $consume * (float) $layer->unit_cost;
            $layer->forceFill([
                'quantity_remaining' => (float) $layer->quantity_remaining - $consume,
            ])->save();
            $remaining -= $consume;
        }

        if ($remaining > 0.00001) {
            throw ValidationException::withMessages([
                'cost_layer' => ['Khong du cost layer de xuat kho. Hay tao phieu nhap/co gia von truoc.'],
            ]);
        }

        return $quantity > 0 ? $consumedValue / $quantity : 0.0;
    }

    private function normalizeSerialNumbers(array|string|null $serialNumbers): array
    {
        if (is_string($serialNumbers)) {
            $serialNumbers = preg_split('/[\r\n,]+/', $serialNumbers) ?: [];
        }

        return collect($serialNumbers ?? [])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function nextCode(string $type): string
    {
        $prefix = match ($type) {
            'receipt' => 'IN',
            'issue' => 'OUT',
            'transfer' => 'TR',
            'adjustment' => 'ADJ',
            default => 'INV',
        };

        $nextId = (InvStockDocument::query()->max('id') ?? 0) + 1;

        return sprintf('%s-%s-%05d', $prefix, now()->format('ymd'), $nextId);
    }
}
