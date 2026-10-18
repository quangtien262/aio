<?php

namespace Modules\FnbPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FnbPos\Services\FnbBankService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbBankController
{
    public function account(Request $request, FnbApiController $api, FnbBankService $bank, FnbOutletAccessService $access, FnbCommandSecurity $security): JsonResponse
    {
        $ctx = $api->context($request, $request->isMethod('GET') ? 'fnb.settings.view' : 'fnb.settings.manage', false,
            $request->isMethod('GET') ? null : $security->authorization($request, 'bank.account.save', $request->all()));
        $access->authorizeHq($request->user('admin'), $ctx->websiteKey, 'fnb.chain.manage');
        if ($request->isMethod('GET')) {
            return response()->json(['data' => $bank->account($ctx)]);
        }
        $key = (string) $request->header('Idempotency-Key');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();

        return response()->json($bank->saveAccount($ctx, $request->all(), $key));
    }

    public function intent(Request $request, FnbApiController $api, FnbBankService $bank, FnbCommandSecurity $security): JsonResponse
    {
        $input = $request->only('amount_minor');
        $ctx = $api->context($request, 'fnb.payment.collect', false, $security->authorization($request, 'bank.intent.create', $input));
        $key = (string) $request->header('Idempotency-Key');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();

        return response()->json($bank->intent($ctx, (int) $request->route('resource'), $input, $key));
    }
}
