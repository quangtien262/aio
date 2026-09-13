<?php

namespace Modules\FnbPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FnbPos\Services\FnbReceiptService;

final class FnbReceiptController
{
    public function __construct(private readonly FnbApiController $api, private readonly FnbCommandSecurity $security, private readonly FnbReceiptService $receipts) {}

    public function show(Request $request): JsonResponse
    {
        $ctx = $this->api->context($request, 'fnb.payment.collect');

        return response()->json(['data' => $this->receipts->read($ctx, (int) $request->route('resource'))]);
    }

    public function command(Request $request): JsonResponse
    {
        $action = (string) $request->route('fnb_receipt_action');
        $input = $request->only(['expected_version']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key] + $input, ['key' => ['required', 'string', 'min:8', 'max:120'],
            'expected_version' => [$action === 'generate' ? 'nullable' : 'required', 'integer', 'min:1']])->validate();
        $ctx = $this->api->context($request, 'fnb.payment.collect', false, $this->security->authorization($request, 'receipt.'.$action, $input));
        $id = (int) $request->route('resource');
        $result = $action === 'generate'
            ? $this->receipts->generate($ctx, $id, $key)
            : $this->receipts->transition($ctx, $id, $action, (int) $input['expected_version'], $key);

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }
}
