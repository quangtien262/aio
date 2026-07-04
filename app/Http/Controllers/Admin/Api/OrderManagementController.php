<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderManagementController
{
    private const STATUSES = ['placed', 'pending', 'processing', 'completed', 'cancelled'];

    public function update(Request $request, int $order): JsonResponse
    {
        $record = Order::query()->findOrFail($order);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ]);

        $record->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Da cap nhat trang thai don hang.',
        ]);
    }

    public function markRead(int $order): JsonResponse
    {
        $record = Order::query()->findOrFail($order);

        if ($record->read_at === null) {
            $record->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Da danh dau don hang da doc.',
        ]);
    }

    public function destroy(int $order): JsonResponse
    {
        $record = Order::query()->findOrFail($order);
        $record->delete();

        return response()->json([
            'message' => 'Da xoa don hang.',
        ]);
    }
}
