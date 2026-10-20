<?php

namespace Modules\RetailPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RetailPos\Services\RetailAccountingBridge;
use Modules\RetailPos\Services\RetailService;

class RetailIntegrationController
{
    public function __construct(private readonly RetailAccountingBridge $bridge, private readonly RetailService $retail) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->bridge->status($request->user('admin'), (string) $request->query('branch_id'))])->header('Cache-Control', 'no-store');
    }

    public function mapping(Request $request): JsonResponse
    {
        $actor = $request->user('admin');
        if ($request->boolean('enabled')) {
            $this->bridge->assertNative($actor, (int) $request->input('organization_id'));
        }
        $result = $this->retail->command($actor, (string) $request->header('Idempotency-Key'), 'integration.mapping', ['data' => $request->all()], function ($live) use ($request): array {
            $status = $this->bridge->configure($live, $request->all());

            // Options are permission-filtered reads, never durable command outcomes.
            // The UI reloads GET integrations with the actor's current grants.
            return ['branch_id' => $request->input('branch_id'), 'accounting' => [
                'enabled' => $status['accounting']['enabled'],
                'organization_id' => $status['accounting']['mapping']['organization_id'],
                'mapping_version' => $status['accounting']['mapping']['version'],
            ]];
        });

        return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'required|uuid', 'limit' => 'sometimes|integer|between:1,100']);
        $result = $this->retail->command($request->user('admin'), (string) $request->header('Idempotency-Key'), 'integration.reconcile', ['data' => $data], fn ($live) => $this->bridge->reconcile($live, $data['branch_id'], $data['limit'] ?? 20));

        return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
    }
}
