<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvItem;
use App\Models\InvBatch;
use App\Models\InvLocation;
use App\Models\InvSerialNumber;
use App\Models\InvStockBalance;
use App\Models\InvStockDocument;
use App\Models\InvStockMovement;
use App\Models\InvSyncRun;
use App\Models\InvWarehouse;

class InventoryDataSerializer
{
    public static function warehouse(InvWarehouse $warehouse): array
    {
        return [
            'id' => $warehouse->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'phone' => $warehouse->phone,
            'email' => $warehouse->email,
            'address' => $warehouse->address,
            'description' => $warehouse->description,
            'is_default' => $warehouse->is_default,
            'is_active' => $warehouse->is_active,
            'items_count' => $warehouse->balances_count ?? null,
            'created_at' => $warehouse->created_at?->toIso8601String(),
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
        ];
    }

    public static function item(InvItem $item): array
    {
        return [
            'id' => $item->id,
            'catalog_product_id' => $item->catalog_product_id,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'name' => $item->name,
            'unit' => $item->unit,
            'costing_method' => $item->costing_method,
            'track_batch' => $item->track_batch,
            'track_serial' => $item->track_serial,
            'sale_price' => (float) $item->sale_price,
            'reorder_min' => (float) ($item->reorder_min ?? 0),
            'reorder_max' => (float) ($item->reorder_max ?? 0),
            'preferred_supplier' => $item->preferred_supplier,
            'image_url' => $item->image_url,
            'is_active' => $item->is_active,
            'last_synced_at' => $item->last_synced_at?->toIso8601String(),
            'total_on_hand' => (float) ($item->balances_sum_quantity_on_hand ?? $item->balances->sum('quantity_on_hand')),
            'catalog_product' => $item->catalogProduct ? [
                'id' => $item->catalogProduct->id,
                'name' => $item->catalogProduct->name,
                'sku' => $item->catalogProduct->sku,
                'stock' => $item->catalogProduct->stock,
            ] : null,
        ];
    }

    public static function balance(InvStockBalance $balance): array
    {
        return [
            'id' => $balance->id,
            'warehouse_id' => $balance->warehouse_id,
            'warehouse_name' => $balance->warehouse?->name,
            'warehouse_code' => $balance->warehouse?->code,
            'item_id' => $balance->item_id,
            'item_name' => $balance->item?->name,
            'item_sku' => $balance->item?->sku,
            'batch_id' => $balance->batch_id,
            'batch_code' => $balance->batch?->batch_code,
            'expires_at' => $balance->batch?->expires_at?->toDateString(),
            'quantity_on_hand' => (float) $balance->quantity_on_hand,
            'quantity_reserved' => (float) $balance->quantity_reserved,
            'quantity_available' => (float) $balance->quantity_on_hand - (float) $balance->quantity_reserved,
            'last_movement_at' => $balance->last_movement_at?->toIso8601String(),
        ];
    }

    public static function document(InvStockDocument $document): array
    {
        return [
            'id' => $document->id,
            'code' => $document->code,
            'type' => $document->type,
            'status' => $document->status,
            'source_warehouse_id' => $document->source_warehouse_id,
            'source_warehouse_name' => $document->sourceWarehouse?->name,
            'destination_warehouse_id' => $document->destination_warehouse_id,
            'destination_warehouse_name' => $document->destinationWarehouse?->name,
            'reference' => $document->reference,
            'note' => $document->note,
            'posted_at' => $document->posted_at?->toIso8601String(),
            'lines' => $document->lines->map(fn ($line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_name' => $line->item?->name,
                'item_sku' => $line->item?->sku,
                'source_location_id' => $line->source_location_id,
                'destination_location_id' => $line->destination_location_id,
                'batch_id' => $line->batch_id,
                'batch_code' => $line->batch?->batch_code ?? $line->batch_code,
                'expires_at' => $line->expires_at?->toDateString(),
                'serial_numbers' => $line->serial_numbers ?? [],
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'note' => $line->note,
            ])->values()->all(),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    public static function movement(InvStockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'item_id' => $movement->item_id,
            'item_name' => $movement->item?->name,
            'item_sku' => $movement->item?->sku,
            'batch_id' => $movement->batch_id,
            'batch_code' => $movement->batch?->batch_code,
            'warehouse_id' => $movement->warehouse_id,
            'warehouse_name' => $movement->warehouse?->name,
            'quantity_delta' => (float) $movement->quantity_delta,
            'balance_after' => (float) $movement->balance_after,
            'unit_cost' => (float) $movement->unit_cost,
            'reference' => $movement->reference,
            'note' => $movement->note,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }

    public static function location(InvLocation $location): array
    {
        return [
            'id' => $location->id,
            'warehouse_id' => $location->warehouse_id,
            'warehouse_name' => $location->warehouse?->name,
            'parent_id' => $location->parent_id,
            'code' => $location->code,
            'name' => $location->name,
            'barcode' => $location->barcode,
            'type' => $location->type,
            'sort_order' => $location->sort_order,
            'is_default' => $location->is_default,
            'is_active' => $location->is_active,
        ];
    }

    public static function batch(InvBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'item_id' => $batch->item_id,
            'item_name' => $batch->item?->name,
            'item_sku' => $batch->item?->sku,
            'batch_code' => $batch->batch_code,
            'manufactured_at' => $batch->manufactured_at?->toDateString(),
            'expires_at' => $batch->expires_at?->toDateString(),
            'note' => $batch->note,
            'is_active' => $batch->is_active,
        ];
    }

    public static function serialNumber(InvSerialNumber $serialNumber): array
    {
        return [
            'id' => $serialNumber->id,
            'item_id' => $serialNumber->item_id,
            'item_name' => $serialNumber->item?->name,
            'batch_id' => $serialNumber->batch_id,
            'batch_code' => $serialNumber->batch?->batch_code,
            'warehouse_id' => $serialNumber->warehouse_id,
            'serial_number' => $serialNumber->serial_number,
            'status' => $serialNumber->status,
            'received_at' => $serialNumber->received_at?->toIso8601String(),
            'issued_at' => $serialNumber->issued_at?->toIso8601String(),
        ];
    }

    public static function syncRun(InvSyncRun $run): array
    {
        return [
            'id' => $run->id,
            'source' => $run->source,
            'created_count' => $run->created_count,
            'updated_count' => $run->updated_count,
            'skipped_count' => $run->skipped_count,
            'failed_count' => $run->failed_count,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'lines' => $run->lines->map(fn ($line): array => [
                'id' => $line->id,
                'catalog_product_id' => $line->catalog_product_id,
                'item_id' => $line->item_id,
                'sku' => $line->sku,
                'name' => $line->name,
                'action' => $line->action,
                'message' => $line->message,
            ])->values()->all(),
        ];
    }
}
