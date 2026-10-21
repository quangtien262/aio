<?php

namespace Modules\Purchasing\Http;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Services\PurchasingProductionService;

class PurchasingProductionController
{
    public function __construct(private readonly PurchasingProductionService $service) {}

    public function requisitions(): JsonResponse
    {
        abort_unless(app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.production.requisitions.v1'), 503);

        return response()->json(['data' => DB::table('purchase_requisitions')->orderByDesc('id')->paginate(50)])->header('Cache-Control', 'private, no-store');
    }

    public function command(Request $request, string $operation): JsonResponse
    {
        $method = match ($operation) {
            'requisitions' => 'requisition', 'orders' => 'createOrder', 'receipts' => 'receive',
            'inspect' => 'inspectReceipt', 'returns' => 'returnRoll', default => abort(404),
        };

        return response()->json(['data' => $this->service->$method($request->all(), (string) $request->header('Idempotency-Key'), (int) $request->user('admin')->id)])->header('Cache-Control', 'private, no-store');
    }
}
