<?php

namespace Modules\Pharmacy\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyService;
use Modules\Pharmacy\Services\Read\PharmacyReadService;

final class PharmacyController
{
    public function bootstrap(Request $request, PharmacyReadService $reader): JsonResponse
    {
        $request->validate(['pharmacy_id' => 'nullable|uuid']);

        return $this->json($reader->bootstrap($request->user('admin'), $request->query('pharmacy_id')));
    }

    public function read(Request $request, string $kind, PharmacyReadService $reader, ?string $id = null): JsonResponse
    {
        $request->validate(['pharmacy_id' => 'required|uuid', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100', 'q' => 'sometimes|string|max:255']);

        return $this->json($reader->read($request->user('admin'), $kind, $request->query(), $id));
    }

    public function command(Request $request, PharmacyService $service, ?string $operation = null): JsonResponse
    {
        $action = $operation ?? $request->validate(['action' => 'required|string|max:90'])['action'];
        $input = $request->all();
        unset($input['action']);
        if (isset($input['data']) && is_array($input['data'])) {
            $input = array_replace($input, $input['data']);
            unset($input['data']);
        }

        return $this->json($service->command($request->user('admin'), $action, $input, (string) $request->header('Idempotency-Key', '')));
    }

    public function status(Request $request, string $key, PharmacyCommandService $commands): JsonResponse
    {
        return $this->json($commands->status($request->user('admin'), $key));
    }

    private function json(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
