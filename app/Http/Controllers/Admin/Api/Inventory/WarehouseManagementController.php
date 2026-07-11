<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvLocation;
use App\Models\InvWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WarehouseManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $warehouse = DB::transaction(function () use ($validated): InvWarehouse {
            if ($validated['is_default'] ?? false) {
                InvWarehouse::query()->update(['is_default' => false]);
            }

            $warehouse = InvWarehouse::query()->create([
                ...$validated,
                'code' => $validated['code'] ?: $this->generateCode($validated['name']),
            ]);

            InvLocation::query()->firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => 'MAIN'],
                ['name' => 'Main storage', 'type' => 'storage', 'is_default' => true, 'is_active' => true],
            );

            return $warehouse;
        });

        return response()->json([
            'message' => 'Da tao kho.',
            'data' => InventoryDataSerializer::warehouse($warehouse),
        ], 201);
    }

    public function update(Request $request, int $warehouse): JsonResponse
    {
        $record = InvWarehouse::query()->findOrFail($warehouse);
        $validated = $this->validatePayload($request, $record);

        DB::transaction(function () use ($record, $validated): void {
            if ($validated['is_default'] ?? false) {
                InvWarehouse::query()->whereKeyNot($record->id)->update(['is_default' => false]);
            }

            $record->update($validated);
        });

        return response()->json([
            'message' => 'Da cap nhat kho.',
            'data' => InventoryDataSerializer::warehouse($record->fresh()),
        ]);
    }

    public function destroy(int $warehouse): JsonResponse
    {
        $record = InvWarehouse::query()->withCount(['balances'])->findOrFail($warehouse);

        abort_if($record->balances_count > 0, 422, 'Khong the xoa kho da co ton kho.');

        $record->delete();

        return response()->json([
            'message' => 'Da xoa kho.',
        ]);
    }

    private function validatePayload(Request $request, ?InvWarehouse $warehouse = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('inv_warehouses', 'code')->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function generateCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '')) ?: 'WH';
        $candidate = Str::limit($base, 12, '');
        $suffix = 1;

        while (InvWarehouse::query()->where('code', $candidate)->exists()) {
            $candidate = Str::limit($base, 10, '').$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
