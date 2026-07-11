<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $location = InvLocation::query()->create($validated);

        return response()->json([
            'message' => 'Da tao vi tri kho.',
            'data' => InventoryDataSerializer::location($location->fresh(['warehouse', 'parent'])),
        ], 201);
    }

    public function update(Request $request, int $location): JsonResponse
    {
        $record = InvLocation::query()->findOrFail($location);
        $validated = $this->validatePayload($request, $record);

        $record->update($validated);

        return response()->json([
            'message' => 'Da cap nhat vi tri kho.',
            'data' => InventoryDataSerializer::location($record->fresh(['warehouse', 'parent'])),
        ]);
    }

    public function destroy(int $location): JsonResponse
    {
        $record = InvLocation::query()
            ->withCount(['children', 'balances'])
            ->findOrFail($location);

        abort_if($record->children_count > 0, 422, 'Khong the xoa vi tri dang co vi tri con.');
        abort_if($record->balances_count > 0, 422, 'Khong the xoa vi tri da co ton kho. Hay tat vi tri thay vi xoa.');

        $record->delete();

        return response()->json([
            'message' => 'Da xoa vi tri kho.',
        ]);
    }

    private function validatePayload(Request $request, ?InvLocation $location = null): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('inv_warehouses', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('inv_locations', 'id')],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('inv_locations', 'code')
                    ->where('warehouse_id', $request->integer('warehouse_id'))
                    ->ignore($location?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
