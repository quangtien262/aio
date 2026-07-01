<?php

namespace App\Http\Controllers\Customer\Api;

use App\Models\CmsService;
use App\Models\CustomerServiceInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceInterestController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cms_service_id' => ['nullable', 'integer', 'exists:cms_services,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = isset($validated['cms_service_id'])
            ? CmsService::query()->find($validated['cms_service_id'])
            : null;

        $title = $service?->title ?: $validated['title'] ?? null;

        if (! filled($title)) {
            return response()->json([
                'message' => 'Vui lòng chọn dịch vụ hoặc nhập nhu cầu quan tâm.',
                'errors' => ['title' => ['Vui lòng chọn dịch vụ hoặc nhập nhu cầu quan tâm.']],
            ], 422);
        }

        $interest = $request->user('customer')->serviceInterests()->create([
            'cms_service_id' => $service?->id,
            'title' => $title,
            'message' => $validated['message'] ?? null,
            'status' => 'interested',
        ]);

        return response()->json([
            'message' => 'Đã lưu dịch vụ quan tâm.',
            'data' => [
                'id' => $interest->id,
                'title' => $interest->title,
                'message' => $interest->message,
                'status' => $interest->status,
            ],
        ], 201);
    }

    public function destroy(Request $request, CustomerServiceInterest $interest): JsonResponse
    {
        abort_unless($interest->customer_id === $request->user('customer')?->id, 404);

        $interest->delete();

        return response()->json(null, 204);
    }
}
