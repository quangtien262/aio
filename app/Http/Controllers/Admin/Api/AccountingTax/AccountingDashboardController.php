<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctExternalInvoice;
use App\Models\AcctItem;
use App\Support\AccountingTax\AccountingOrganizationResolver;
use App\Support\AccountingTax\ModuleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingDashboardController
{
    public function __invoke(
        Request $request,
        AccountingOrganizationResolver $organizations,
        ModuleCapabilityService $capabilities,
    ): JsonResponse {
        $organization = $organizations->resolve($request->integer('organization_id') ?: null);

        return response()->json([
            'data' => [
                'organization' => AccountingTaxSerializer::organization($organization),
                'integrations' => $capabilities->accountingIntegrations($organization->id),
                'metrics' => [
                    'items' => AcctItem::query()->where('organization_id', $organization->id)->count(),
                    'draft_documents' => AcctDocument::query()->where('organization_id', $organization->id)->where('workflow_status', 'draft')->count(),
                    'posted_documents' => AcctDocument::query()->where('organization_id', $organization->id)->where('workflow_status', 'posted')->count(),
                    'unmatched_external_invoices' => AcctExternalInvoice::query()
                        ->where('organization_id', $organization->id)
                        ->where('reconciliation_status', 'unmatched')
                        ->count(),
                ],
            ],
        ]);
    }
}
