<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\Order;
use App\Support\AccountingTax\AccountingOrderInvoiceService;
use App\Support\AccountingTax\AccountingOrganizationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderConversionController
{
    public function __invoke(
        Request $request,
        Order $order,
        AccountingOrganizationResolver $organizations,
        AccountingOrderInvoiceService $converter,
    ): JsonResponse {
        $data = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:acct_organizations,id'],
        ]);
        $organization = $organizations->resolve($data['organization_id'] ?? null);
        $document = $converter->createDraft($order->loadMissing('items'), $organization, $request->user('admin')?->id);

        return response()->json(['data' => AccountingTaxSerializer::document($document->load(['party', 'lines']))], 201);
    }
}
