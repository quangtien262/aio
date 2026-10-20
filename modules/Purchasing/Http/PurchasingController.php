<?php

namespace Modules\Purchasing\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Services\PurchasingService;

class PurchasingController
{
    public function __construct(private readonly PurchasingService $service) {}

    public function suppliers(): JsonResponse
    {
        return response()->json(['data' => DB::table('purchase_suppliers')->orderBy('name')->limit(500)->get()])->header('Cache-Control', 'no-store');
    }

    public function createSupplier(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->createSupplier($request->all(), $this->key($request), $request->user('admin')->id))], 201);
    }

    public function orders(Request $request): JsonResponse
    {
        $data = DB::table('purchase_orders')->orderByDesc('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return response()->json(['data' => $data->items(), 'meta' => ['total' => $data->total(), 'current_page' => $data->currentPage(), 'last_page' => $data->lastPage()]])->header('Cache-Control', 'no-store');
    }

    public function show(string $order): JsonResponse
    {
        return response()->json(['data' => $this->service->order($order)])->header('Cache-Control', 'no-store');
    }

    public function commandStatus(Request $request, string $key): JsonResponse
    {
        return response()->json(['data' => $this->service->commandStatus($key, $request->user('admin')->id)])->header('Cache-Control', 'no-store');
    }

    public function createOrder(Request $request): JsonResponse
    {
        $this->nativePermission($request, 'inventory.receipt.manage');

        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->createOrder($request->all(), $this->key($request), $request->user('admin')->id))], 201);
    }

    public function approve(Request $request, string $order): JsonResponse
    {
        $v = $request->validate(['expected_version' => 'required|integer|min:1']);

        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->approveOrder($order, $v['expected_version'], $this->key($request), $request->user('admin')->id))]);
    }

    public function receive(Request $request, string $order): JsonResponse
    {
        $this->nativePermission($request, 'inventory.receipt.manage');

        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->receiveOrder($order, $request->all(), $this->key($request), $request->user('admin')->id))]);
    }

    public function returnGoods(Request $request, string $order): JsonResponse
    {
        $this->nativePermission($request, 'inventory.issue.manage');

        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->returnReceiptLine($order, $request->all(), $this->key($request), $request->user('admin')->id))]);
    }

    public function pay(Request $request, string $order): JsonResponse
    {
        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->payOrder($order, $request->all(), $this->key($request), $request->user('admin')->id))]);
    }

    public function close(Request $request, string $order): JsonResponse
    {
        $v = $request->validate(['expected_version' => 'required|integer|min:1']);

        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), fn (): array => $this->service->closeOrder($order, $v['expected_version'], $this->key($request), $request->user('admin')->id))]);
    }

    private function key(Request $r): string
    {
        return (string) $r->header('Idempotency-Key', '');
    }

    private function nativePermission(Request $r, string $permission): void
    {
        abort_unless($r->user('admin')->canAccess($permission, 'global', null), 403, 'Cần quyền Kho toàn cục để thực hiện nghiệp vụ mua/nhận hàng.');
    }
}
