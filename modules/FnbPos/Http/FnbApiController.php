<?php

namespace Modules\FnbPos\Http;

use App\Models\ModuleInstallation;
use App\Support\SiteContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbCompensationService;
use Modules\FnbPos\Services\FnbConfigurationService;
use Modules\FnbPos\Services\FnbIntegrationReadiness;
use Modules\FnbPos\Services\FnbReadService;
use Modules\FnbPos\Services\FnbRecipeService;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

class FnbApiController
{
    public function __construct(
        private readonly FnbService $service,
        private readonly FnbOutletAccessService $access,
        private readonly SiteContext $site,
        private readonly FnbCommandSecurity $security,
    ) {}

    public function bootstrap(Request $request, FnbIntegrationReadiness $integrations): JsonResponse
    {
        $website = $this->site->websiteKey();
        abort_unless($this->access->canBootstrapWebsite($request->user('admin'), $website), 403);
        $ids = $this->access->accessibleOutletIds($request->user('admin'), $website, 'fnb.outlet.view');
        $outlets = DB::table('fnb_outlets')->where('website_key', $website)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('id')->get();
        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->firstOrFail();
        $terminals = DB::table('fnb_terminals')->where('website_key', $website)
            ->whereIn('outlet_id', $outlets->pluck('id'))->where('status', 'active')
            ->get(['id', 'outlet_id', 'public_id', 'code', 'name', 'type', 'status'])->groupBy('outlet_id');
        $outletPayload = $outlets->map(function ($outlet) use ($request, $website, $terminals): array {
            $ids = $this->access->accessibleTerminalIds($request->user('admin'), $website, (int) $outlet->id, 'fnb.outlet.view');
            $allowed = ($terminals[$outlet->id] ?? collect())->filter(fn ($terminal) => $ids === null || in_array((int) $terminal->id, $ids, true));

            return (array) $outlet + ['terminals' => $allowed->values()->all()];
        });

        return response()->json(['data' => [
            'operational_state' => DB::table('fnb_site_settings')->where('website_key', $website)->value('operational_state') ?? 'unconfigured',
            'installed_version' => $installation->version,
            'feature_flags' => [],
            'onboarding' => ['completed' => $outlets->isNotEmpty(), 'steps' => ['outlet', 'terminal', 'menu', 'shift']],
            'outlets' => $outletPayload,
            'integrations' => $this->access->canBootstrapWebsite($request->user('admin'), $website, 'fnb.integration.view') ? $integrations->summary() : [],
        ]]);
    }

    public function read(Request $request, FnbReadService $reads): JsonResponse
    {
        $name = (string) $request->route('fnb_read');
        $permission = (string) $request->route('fnb_permission');
        $ctx = $this->context($request, $permission);
        $result = match ($name) {
            'menu' => $this->catalog($request, $ctx),
            'kitchen' => $this->service->pollKitchen($ctx, max(0, $request->integer('cursor')), $request->filled('station_id') ? $request->integer('station_id') : null),
            'report' => $this->report($request, $ctx),
            'customers' => $this->customers($request, $ctx),
            default => ['resource' => $reads->read($ctx, $name, $request->query(), $request->user('admin'))],
        };

        return $this->response($result);
    }

    public function command(Request $request): JsonResponse
    {
        $name = (string) $request->route('fnb_command');
        $permission = (string) $request->route('fnb_permission');
        $input = $request->except(['website_key', 'actor_id', 'admin_id', 'approved', 'approved_by', 'reauth_proof', 'approval_token']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9_.:\\-]+$/']])->validate();
        $ctx = $this->context($request, $permission, $name === 'onboard', $this->security->authorization($request, $name, $input));
        $id = (int) $request->route('resource');
        $version = fn (): int => $this->version($input);
        $result = match ($name) {
            'onboard' => $this->service->onboard($ctx, $input, $key),
            'item.save' => $this->service->saveCatalogItem($ctx, $id ? array_replace($input, ['id' => $id]) : $input, $key, isset($input['expected_version']) ? $version() : null),
            'item.availability' => $this->service->setAvailability($ctx, $id, $this->boolean($input, 'available'), $input['sold_out_until'] ?? null, $input['reason'] ?? null, $key, $version()),
            'customer.create' => $this->service->createCustomer($ctx, $input, $key),
            'day.open' => $this->service->openBusinessDay($ctx, $this->businessDate($input), $key),
            'day.close' => $this->service->closeBusinessDay($ctx, $id, $key, $version()),
            'shift.open' => $this->service->openShift($ctx, $this->integer($input, 'business_day_id', 1), $this->integer($input, 'opening_float_minor'), $key),
            'shift.close' => $this->service->closeShift($ctx, $id, $this->array($input, 'counted_tenders'), $key, $version()),
            'shift.reconcile' => $this->service->reconcileShift($ctx, $id, $key, $version()),
            'cash.record' => $this->service->recordCashMovement($ctx, $id, $this->choice($input, 'kind', ['cash_in', 'cash_out']), $this->integer($input, 'amount_minor', 1), $this->reason($input), $key, $version()),
            'session.open' => $this->service->openSession($ctx, $input, $key),
            'session.transfer' => $this->service->transferSession($ctx, $id, $this->integer($input, 'table_id', 1), $key, $version()),
            'session.settle' => $this->service->settleSession($ctx, $id, $key, $version()),
            'session.close' => $this->service->closeSession($ctx, $id, $key, $version()),
            'order.create' => $this->service->createOrder($ctx, $id, $this->integer($input, 'shift_id', 1), $input, $key, $version()),
            'line.add' => $this->service->addOrderLine($ctx, $id, $input, $key, $version()),
            'line.update' => $this->service->updateDraftOrderLine($ctx, $id, $input, $key, $version()),
            'line.remove' => $this->service->removeDraftOrderLine($ctx, $id, $key, $version()),
            'order.submit' => $this->service->submitOrder($ctx, $id, $key, $version()),
            'order.discount' => $this->service->applyOrderDiscount($ctx, $id, $input, $key, $version()),
            'line.void' => $this->service->voidOrderLine($ctx, $id, $this->reason($input), $key, $version()),
            'kitchen.transition' => $this->service->transitionKitchenLine($ctx, $id, $this->choice($input, 'status', ['preparing', 'ready', 'served']), $key, $version()),
            'check.create' => $this->service->createCheck($ctx, $id, $this->array($input, 'allocations'), $key, $version()),
            'check.finalize' => $this->service->finalizeCheck($ctx, $id, $key, $version()),
            'check.reopen' => $this->service->reopenCheck($ctx, $id, $this->reason($input), $key, $version()),
            'check.void' => $this->service->voidCheck($ctx, $id, $this->reason($input), $key, $version()),
            'check.plan' => $this->service->setSettlementPlan($ctx, $id, $this->choice($input, 'mode', ['single_cash', 'single_non_cash', 'mixed']), $key, $version()),
            'payment.collect' => $this->service->collectPayment($ctx, $id, $this->integer($input, 'shift_id', 1), $input, $key, $version()),
            'payment.cancel' => $this->service->cancelPayment($ctx, $id, $this->reason($input), $key),
            'payment.refund' => $this->service->refundPayment($ctx, $id, $this->integer($input, 'shift_id', 1), $input, $key),
            'refund.cancel' => $this->service->cancelRefund($ctx, $id, $this->reason($input), $key),
            default => abort(404),
        };

        return $this->response($result);
    }

    public function configure(Request $request, FnbConfigurationService $configuration): JsonResponse
    {
        $kind = (string) $request->route('fnb_kind');
        $permission = (string) $request->route('fnb_permission');
        $input = $request->except(['website_key', 'actor_id', 'admin_id', 'approved', 'approved_by', 'reauth_proof', 'approval_token']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();
        $ctx = $this->context($request, $permission, false, $this->security->authorization($request, $kind.'.save', $input));
        $id = $request->route('resource') ? (int) $request->route('resource') : null;
        if ($kind === 'outlet') {
            $id = $ctx->outletId;
        }

        return $this->response($configuration->save($ctx, $kind, $id, $input, $key));
    }

    public function publishRecipe(Request $request, FnbRecipeService $recipes): JsonResponse
    {
        $input = $request->except(['website_key', 'actor_id', 'reauth_proof', 'approval_token']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();
        $ctx = $this->context($request, 'fnb.recipe.manage', false, $this->security->authorization($request, 'recipe.publish', $input));

        return $this->response($recipes->publish($ctx, $input, $key));
    }

    public function compensate(Request $request, FnbCompensationService $compensations): JsonResponse
    {
        $input = $request->only(['shift_id', 'quantity', 'expected_version', 'reason']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();
        $ctx = $this->context($request, 'fnb.order.void', false, $this->security->authorization($request, 'fulfillment.compensate', $input));

        return $this->response($compensations->compensate($ctx, (int) $request->route('resource'), $input, $key));
    }

    public function context(Request $request, string $permission, bool $onboarding = false, ?\Closure $authorize = null): FnbContext
    {
        $website = $this->site->websiteKey();
        $outlet = $request->route('outlet') ?? $request->header('X-FNB-Outlet') ?? $request->input('outlet_id');
        if ($onboarding && ! $outlet) {
            abort_unless($this->access->canBootstrapWebsite($request->user('admin'), $website, $permission), 403);
            $outlet = 0;
        } else {
            validator(['outlet_id' => $outlet], ['outlet_id' => ['required', 'integer', 'min:1']])->validate();
            if ($request->route('outlet') && $request->header('X-FNB-Outlet')) {
                abort_unless((int) $outlet === (int) $request->header('X-FNB-Outlet'), 409, 'Điểm bán trong yêu cầu không nhất quán.');
            }
            $this->access->authorize($request->user('admin'), $website, (int) $outlet, $permission);
        }
        $terminal = $request->header('X-FNB-Terminal') ?? $request->input('terminal_id');
        if ($terminal !== null) {
            validator(['terminal_id' => $terminal], ['terminal_id' => ['required', 'integer', 'min:1']])->validate();
            abort_unless(DB::table('fnb_terminals')->where('website_key', $website)->where('outlet_id', $outlet)->where('id', $terminal)->where('status', 'active')->exists(), 404);
        }
        if ((int) $outlet > 0) {
            $this->access->authorizeTerminal($request->user('admin'), $website, (int) $outlet, $terminal !== null ? (int) $terminal : null, $permission);
        }

        return new FnbContext($website, (int) $outlet, (int) $request->user('admin')->id, $terminal !== null ? (int) $terminal : null, $authorize);
    }

    private function report(Request $request, FnbContext $ctx): array
    {
        $type = (string) $request->input('report_type', 'financial');
        abort_unless(in_array($type, ['financial', 'operations'], true), 422);
        $this->access->authorize($request->user('admin'), $ctx->websiteKey, $ctx->outletId, 'fnb.report.'.$type.'.view');
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $result = $this->service->summaryReport($ctx, $request->only(['from', 'to', 'business_day_id', 'shift_id', 'terminal_id']));
        if ($type === 'operations') {
            $resource = $result['resource'] ?? $result;
            $result['resource'] = [
                'period' => $resource['period'] ?? [],
                'totals' => array_intersect_key($resource['totals'] ?? [], array_flip(['checks', 'orders'])),
                'top_items' => array_map(fn ($item) => array_intersect_key((array) $item, array_flip(['item_id', 'item_code', 'item_name', 'quantity'])), $resource['top_items'] ?? []),
            ];
        }

        return $result;
    }

    private function catalog(Request $request, FnbContext $ctx): array
    {
        if ($request->boolean('include_inactive') || $request->input('status') === 'inactive') {
            $this->access->authorize($request->user('admin'), $ctx->websiteKey, $ctx->outletId, 'fnb.menu.manage');
        }

        return $this->service->catalog($ctx, $request->only(['query', 'status']) + ['include_inactive' => $request->boolean('include_inactive')]);
    }

    private function customers(Request $request, FnbContext $ctx): array
    {
        $key = 'fnb:customer-lookup:'.hash('sha256', $ctx->websiteKey.':'.$ctx->actorId.':'.$request->ip());
        abort_if(RateLimiter::tooManyAttempts($key, 30), 429, 'Vui lòng thử lại sau một phút.');
        RateLimiter::hit($key, 60);
        $request->validate(['query' => ['nullable', 'string', 'max:100']]);
        $result = $this->service->searchCustomers($ctx, (string) $request->input('query', ''), 20);
        try {
            $this->access->authorizeTerminal($request->user('admin'), $ctx->websiteKey, $ctx->outletId, $ctx->terminalId, 'fnb.customer.view');
            $fullProfile = true;
        } catch (AuthorizationException) {
            $fullProfile = false;
        }
        if (! $fullProfile) {
            $result['resource']['items'] = array_map(function ($customer): array {
                $customer = array_intersect_key((array) $customer, array_flip(['id', 'public_id', 'code', 'name', 'status', 'version', 'phone_normalized']));
                $phone = (string) ($customer['phone_normalized'] ?? '');
                $customer['phone_normalized'] = $phone === '' ? null : '***'.substr($phone, -4);

                return $customer;
            }, $result['resource']['items']);
        }

        return $result;
    }

    private function response(array $result): JsonResponse
    {
        return response()->json([
            'data' => FnbPublicProjection::operational($result['resource'] ?? $result),
            'meta' => ['replayed' => (bool) ($result['replayed'] ?? false)] + ($result['meta'] ?? []),
        ]);
    }

    private function version(array $input): int
    {
        return $this->integer($input, 'expected_version', 1);
    }

    private function integer(array $input, string $key, int $min = 0): int
    {
        validator($input, [$key => ['required', 'integer', 'min:'.$min, 'max:9000000000000']])->validate();

        return (int) $input[$key];
    }

    private function boolean(array $input, string $key): bool
    {
        validator($input, [$key => ['required', 'boolean']])->validate();

        return (bool) $input[$key];
    }

    private function array(array $input, string $key): array
    {
        validator($input, [$key => ['present', 'array', 'max:500']])->validate();

        return $input[$key];
    }

    private function choice(array $input, string $key, array $choices): string
    {
        validator($input, [$key => ['required', Rule::in($choices)]])->validate();

        return $input[$key];
    }

    private function reason(array $input): string
    {
        validator($input, ['reason' => ['required', 'string', 'min:3', 'max:500']])->validate();

        return $input['reason'];
    }

    private function businessDate(array $input): string
    {
        validator($input, ['business_date' => ['required', 'date_format:Y-m-d']])->validate();

        return $input['business_date'];
    }
}
