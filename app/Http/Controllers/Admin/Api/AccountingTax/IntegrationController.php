<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Support\AccountingTax\ModuleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController
{
    public function __invoke(Request $request, ModuleCapabilityService $capabilities): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
        ]);

        return response()->json([
            'data' => [
                'integrations' => $capabilities->accountingIntegrations((int) $validated['organization_id']),
                'notes' => [
                    'inventory' => 'Inventory chỉ được dùng khi module có status enabled; bảng inv_* tồn tại chưa đủ để bật tích hợp.',
                    'einvoice' => 'Minvoice connector cần API contract/sandbox mới trước khi bật gửi thật.',
                ],
            ],
        ]);
    }
}
