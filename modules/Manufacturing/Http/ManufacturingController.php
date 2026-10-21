<?php

namespace Modules\Manufacturing\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Manufacturing\Services\Commands\ManufacturingCommandService;
use Modules\Manufacturing\Services\ManufacturingService;

final class ManufacturingController
{
    public function __construct(private readonly ManufacturingService $service) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $request->validate(['factory_id' => 'nullable|uuid']);

        return response()->json(['data' => $this->service->bootstrap($request->user('admin'), $request->query('factory_id'))])->header('Cache-Control', 'no-store');
    }

    public function read(Request $request, string $kind): JsonResponse
    {
        $request->validate(['factory_id' => 'nullable|uuid']);

        return response()->json(['data' => $this->service->read($request->user('admin'), $kind, $request->query('factory_id'), $request->query())])->header('Cache-Control', 'no-store');
    }

    public function mutate(Request $request, string $operation): JsonResponse
    {
        $result = $this->service->command($request->user('admin'), $operation, $request->all(), (string) $request->header('Idempotency-Key', ''));

        return response()->json(['data' => $result, 'meta' => ['replayed' => $result['replayed'] ?? false, 'command_id' => $result['command_id'] ?? null]])->header('Cache-Control', 'no-store');
    }

    public function commandStatus(Request $request, string $key): JsonResponse
    {
        return response()->json(['data' => app(ManufacturingCommandService::class)->status($request->user('admin'), $key)])->header('Cache-Control', 'no-store');
    }
}
