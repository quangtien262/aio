<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvItem;
use App\Models\InvLocation;
use App\Models\InvSerialNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarcodeLookupController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $code = trim($validated['code']);
        $item = InvItem::query()
            ->withSum('balances', 'quantity_on_hand')
            ->where('barcode', $code)
            ->orWhere('sku', $code)
            ->first();

        if ($item) {
            return response()->json([
                'data' => [
                    'type' => 'item',
                    'record' => InventoryDataSerializer::item($item),
                ],
            ]);
        }

        $location = InvLocation::query()->with('warehouse')->where('barcode', $code)->first();

        if ($location) {
            return response()->json([
                'data' => [
                    'type' => 'location',
                    'record' => InventoryDataSerializer::location($location),
                ],
            ]);
        }

        $serial = InvSerialNumber::query()->with(['item', 'batch'])->where('serial_number', $code)->first();

        if ($serial) {
            return response()->json([
                'data' => [
                    'type' => 'serial',
                    'record' => InventoryDataSerializer::serialNumber($serial),
                ],
            ]);
        }

        abort(404, 'Khong tim thay barcode/SKU/serial.');
    }
}
