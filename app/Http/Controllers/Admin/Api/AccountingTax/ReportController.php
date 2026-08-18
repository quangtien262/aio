<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Support\AccountingTax\AccountingOrganizationResolver;
use App\Support\AccountingTax\AccountingTaxReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController
{
    public function __invoke(
        Request $request,
        AccountingOrganizationResolver $organizations,
        AccountingTaxReportService $reports,
    ): JsonResponse {
        $filters = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:acct_organizations,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'mode' => ['sometimes', Rule::in(['operational', 'tax'])],
        ]);
        $organization = $organizations->resolve($request->integer('organization_id') ?: null);
        $from = $request->date('from');
        $to = $request->date('to');
        $mode = $filters['mode'] ?? 'operational';
        $report = $reports->build($organization->id, $from, $to, $mode);

        return response()->json([
            'data' => [
                'organization' => AccountingTaxSerializer::organization($organization),
                'filters' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                ],
                ...$report,
            ],
        ]);
    }
}
