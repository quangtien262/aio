<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemManagementController
{
    public function update(Request $request, int $item): JsonResponse
    {
        $record = InvItem::query()->findOrFail($item);
        $validated = $request->validate([
            'barcode' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:40'],
            'costing_method' => ['nullable', Rule::in(['fifo', 'avg'])],
            'track_batch' => ['nullable', 'boolean'],
            'track_serial' => ['nullable', 'boolean'],
            'reorder_min' => ['nullable', 'numeric', 'min:0'],
            'reorder_max' => ['nullable', 'numeric', 'min:0'],
            'preferred_supplier' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $record->update($validated);

        return response()->json([
            'message' => 'Da cap nhat cau hinh hang hoa.',
            'data' => InventoryDataSerializer::item($record->fresh(['catalogProduct'])->loadSum('balances', 'quantity_on_hand')),
        ]);
    }
}
