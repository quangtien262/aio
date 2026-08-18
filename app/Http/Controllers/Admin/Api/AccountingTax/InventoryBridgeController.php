<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctInventoryLink;
use App\Models\AcctInventoryWarehouseMapping;
use App\Support\AccountingTax\AccountingInventoryBridge;
use App\Support\AccountingTax\AccountingInventoryWarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryBridgeController
{
    public function warehouses(Request $request, AccountingInventoryWarehouseService $warehouses): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
        ]);

        $organizationId = (int) $data['organization_id'];

        return response()->json([
            'data' => [
                'items' => $warehouses->forOrganization($organizationId),
                'available_items' => $warehouses->availableForOrganization($organizationId),
            ],
        ]);
    }

    public function mapWarehouse(Request $request, AccountingInventoryWarehouseService $warehouses): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $mapping = $warehouses->map(
            (int) $data['organization_id'],
            (int) $data['warehouse_id'],
            (bool) ($data['is_default'] ?? false),
            $request->user('admin')?->id,
        );

        return response()->json(['data' => $warehouses->serialize($mapping)], 201);
    }

    public function unmapWarehouse(
        AcctInventoryWarehouseMapping $mapping,
        AccountingInventoryWarehouseService $warehouses,
    ): JsonResponse {
        $warehouses->unmap($mapping);

        return response()->json(['data' => ['removed' => true]]);
    }

    public function propose(Request $request, AcctDocument $document, AccountingInventoryBridge $bridge): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'tracking' => ['sometimes', 'array'],
            'tracking.*.source_location_id' => ['nullable', 'integer'],
            'tracking.*.destination_location_id' => ['nullable', 'integer'],
            'tracking.*.batch_id' => ['nullable', 'integer'],
            'tracking.*.batch_code' => ['nullable', 'string', 'max:120'],
            'tracking.*.expires_at' => ['nullable', 'date'],
            'tracking.*.serial_numbers' => ['sometimes', 'array'],
            'tracking.*.serial_numbers.*' => ['string', 'max:255'],
        ]);
        $link = $bridge->propose($document, (int) $data['warehouse_id'], $data['tracking'] ?? []);

        return response()->json(['data' => $this->serialize($link)], 201);
    }

    public function post(Request $request, AcctInventoryLink $link, AccountingInventoryBridge $bridge): JsonResponse
    {
        $link = $bridge->post($link, $request->user('admin')?->id);

        return response()->json(['data' => $this->serialize($link)]);
    }

    /** @return array<string, mixed> */
    private function serialize(AcctInventoryLink $link): array
    {
        return [
            'id' => $link->id,
            'organization_id' => $link->organization_id,
            'document_id' => $link->document_id,
            'direction' => $link->direction,
            'inventory_document_id' => $link->inventory_document_id,
            'status' => $link->status,
            'payload_snapshot' => $link->payload_snapshot,
            'last_error' => $link->last_error,
            'posted_at' => $link->posted_at?->toIso8601String(),
        ];
    }
}
