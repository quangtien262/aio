<?php

namespace Modules\FnbPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\FnbIntegrationService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbIntegrationController
{
    public function handle(Request $request, FnbApiController $api, FnbIntegrationService $integrations, FnbOutletAccessService $access, FnbCommandSecurity $security, FnbCommandRunner $commands): JsonResponse
    {
        $action = (string) $request->route('integration_action', 'overview');
        $input = $request->except(['outlet_id', 'website_key', 'actor_id']);
        $permission = $action === 'overview' ? 'fnb.integration.view' : 'fnb.settings.manage';
        $ctx = $api->context($request, $permission, false, $action === 'overview' ? null : $security->authorization($request, $action, $request->all()));
        $access->authorizeHq($request->user('admin'), $ctx->websiteKey, 'fnb.chain.manage');
        if ($action === 'overview') {
            return response()->json(['data' => $integrations->overview($ctx, $request->user('admin'))]);
        }
        $key = (string) $request->header('Idempotency-Key');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();
        if ($action === 'integration.reconcile') {
            $data = validator($input, ['destination' => ['required', 'in:inventory,accounting-tax'], 'retry_dead_letter' => ['sometimes', 'boolean']])->validate();
            $accepted = $commands->run($ctx, $action, $data, $key, fn (): array => ['resource' => $integrations->prepare($ctx, $request->user('admin'), $data['destination'], (bool) ($data['retry_dead_letter'] ?? false))]);

            return response()->json(['data' => $integrations->reconcile($ctx, $request->user('admin'), $data['destination']), 'meta' => ['replayed' => $accepted['replayed']]]);
        }

        return response()->json($integrations->mapping($ctx, $request->user('admin'), $input, $key, $action === 'integration.item.save'));
    }
}
