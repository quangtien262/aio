<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvStockDocument;
use App\Support\InventoryStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockDocumentManagementController
{
    public function store(Request $request, InventoryStockService $stockService): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['receipt', 'issue', 'transfer', 'adjustment'])],
            'code' => ['nullable', 'string', 'max:60', Rule::unique('inv_stock_documents', 'code')],
            'source_warehouse_id' => ['nullable', 'integer', Rule::exists('inv_warehouses', 'id')],
            'destination_warehouse_id' => ['nullable', 'integer', Rule::exists('inv_warehouses', 'id')],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('inv_items', 'id')],
            'lines.*.source_location_id' => ['nullable', 'integer', Rule::exists('inv_locations', 'id')],
            'lines.*.destination_location_id' => ['nullable', 'integer', Rule::exists('inv_locations', 'id')],
            'lines.*.batch_id' => ['nullable', 'integer', Rule::exists('inv_batches', 'id')],
            'lines.*.batch_code' => ['nullable', 'string', 'max:255'],
            'lines.*.expires_at' => ['nullable', 'date'],
            'lines.*.serial_numbers' => ['nullable', 'array'],
            'lines.*.serial_numbers.*' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string'],
        ]);

        $this->validateWarehouseRequirements($validated);
        $this->authorizeDocumentType($request, $validated['type']);

        $document = $stockService->createDocument($validated, $request->user('admin')?->id);

        return response()->json([
            'message' => 'Da tao va post phieu kho.',
            'data' => InventoryDataSerializer::document($document),
        ], 201);
    }

    public function destroy(int $document): JsonResponse
    {
        $record = InvStockDocument::query()->findOrFail($document);

        abort(422, 'Phieu kho da post khong ho tro xoa trong phase 1.');
    }

    private function validateWarehouseRequirements(array $payload): void
    {
        $type = $payload['type'];
        $sourceWarehouseId = $payload['source_warehouse_id'] ?? null;
        $destinationWarehouseId = $payload['destination_warehouse_id'] ?? null;

        if (in_array($type, ['issue', 'transfer'], true) && ! $sourceWarehouseId) {
            abort(422, 'Phieu xuat/chuyen kho can kho nguon.');
        }

        if (in_array($type, ['receipt', 'transfer', 'adjustment'], true) && ! $destinationWarehouseId) {
            abort(422, 'Phieu nhap/chuyen/dieu chinh can kho dich.');
        }

        if ($type === 'transfer' && (int) $sourceWarehouseId === (int) $destinationWarehouseId) {
            abort(422, 'Kho nguon va kho dich phai khac nhau.');
        }
    }

    private function authorizeDocumentType(Request $request, string $type): void
    {
        $permission = match ($type) {
            'receipt' => 'inventory.receipt.manage',
            'issue' => 'inventory.issue.manage',
            'transfer' => 'inventory.transfer.manage',
            'adjustment' => 'inventory.adjustment.manage',
            default => null,
        };

        abort_if(! $permission || ! $request->user('admin')?->hasPermission($permission), 403, 'Ban khong co quyen tao loai phieu kho nay.');
    }
}
