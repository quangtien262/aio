<?php

namespace Modules\FnbPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FnbPos\Services\FnbCustomerService;

final class FnbCustomerController
{
    public function __construct(private readonly FnbApiController $api, private readonly FnbCommandSecurity $security, private readonly FnbCustomerService $customers) {}

    public function show(Request $request): JsonResponse
    {
        $ctx = $this->api->context($request, 'fnb.customer.view');
        $id = (int) $request->route('resource');
        $result = $request->route('fnb_history')
            ? $this->customers->purchaseHistory($ctx, $id, min(100, max(1, $request->integer('limit', 25))), $request->filled('before_id') ? $request->integer('before_id') : null)
            : $this->customers->profile($ctx, $id);

        return response()->json(['data' => $result['resource'] ?? $result, 'meta' => $result['meta'] ?? []]);
    }

    public function command(Request $request): JsonResponse
    {
        $action = (string) $request->route('fnb_customer_action');
        $input = $request->except(['website_key', 'actor_id', 'reauth_proof', 'approval_token']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key] + $input, ['key' => ['required', 'string', 'min:8', 'max:120'],
            'expected_version' => ['required', 'integer', 'min:1'], 'customer_profile_id' => [$action === 'attach' ? 'required' : 'nullable', 'integer', 'min:1']])->validate();
        $ctx = $this->api->context($request, 'fnb.customer.'.$action, false, $this->security->authorization($request, 'customer.'.$action, $input));
        $id = (int) $request->route('resource');
        $result = $action === 'attach'
            ? $this->customers->attachToSession($ctx, $id, (int) $input['customer_profile_id'], $key, (int) $input['expected_version'])
            : $this->customers->updateProfile($ctx, $id, $input, $key, (int) $input['expected_version']);

        return response()->json(['data' => $result['resource'] ?? $result, 'meta' => ['replayed' => (bool) ($result['replayed'] ?? false)] + ($result['meta'] ?? [])]);
    }
}
