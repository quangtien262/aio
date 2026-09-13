<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

class FnbReadService
{
    public function __construct(private readonly FnbService $service, private readonly FnbOutletAccessService $access) {}

    public function read(FnbContext $ctx, string $projection, array $filters, Admin $actor): array
    {
        return match ($projection) {
            'pos' => $this->pos($ctx, $actor),
            'dashboard' => $this->dashboard($ctx, $actor),
            'settings' => $this->settings($ctx),
            'shifts' => ['items' => $this->scope('fnb_shifts', $ctx)->when($ctx->terminalId, fn ($q, $terminal) => $q->where('terminal_id', $terminal))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->orderByDesc('id')->limit(100)->get()->map(fn ($row) => $this->decode($row))->all()],
            'business_day' => ['business_day' => $this->currentDay($ctx)],
            'recipes' => $this->recipes($ctx),
            'exports' => ['items' => $this->scope('fnb_report_exports', $ctx)->where('requested_by', $actor->id)->orderByDesc('id')->limit(100)->get(['id', 'report_type', 'format', 'status', 'queued_at', 'completed_at', 'expires_at'])->all()],
            default => abort(404),
        };
    }

    private function pos(FnbContext $ctx, Admin $actor): array
    {
        $settings = $this->settings($ctx);
        $sessions = $this->scope('fnb_service_sessions', $ctx)->whereNotIn('status', ['closed', 'cancelled', 'merged'])->orderBy('id')->limit(200)->get();
        $sessionTables = DB::table('fnb_service_session_tables as links')
            ->join('fnb_dining_tables as tables', 'tables.id', '=', 'links.table_id')
            ->where('links.website_key', $ctx->websiteKey)->where('links.outlet_id', $ctx->outletId)
            ->whereIn('links.session_id', $sessions->pluck('id'))->whereNotNull('links.active_slot')
            ->get(['links.session_id', 'tables.id', 'tables.code', 'tables.name', 'tables.version'])->groupBy('session_id');
        $orders = $this->scope('fnb_orders', $ctx)->whereIn('session_id', $sessions->pluck('id'))->get();
        $lines = $this->scope('fnb_order_lines', $ctx)->whereIn('order_id', $orders->pluck('id'))->get()->groupBy('order_id');
        $lineModifiers = $this->scope('fnb_order_line_modifiers', $ctx)->whereIn('order_line_id', $lines->flatten(1)->pluck('id'))
            ->get(['order_line_id', 'modifier_group_id', 'modifier_option_id', 'group_name_snapshot', 'option_name_snapshot', 'quantity', 'unit_price_delta_minor', 'total_minor'])->groupBy('order_line_id');
        $checks = $this->scope('fnb_checks', $ctx)->whereIn('session_id', $sessions->pluck('id'))->get();
        $financials = $this->scope('fnb_check_financial_projections', $ctx)->whereIn('check_id', $checks->pluck('id'))->get()->keyBy('check_id');
        $checkLines = $this->scope('fnb_check_lines', $ctx)->whereIn('check_id', $checks->pluck('id'))->get()->groupBy('check_id');
        $canSeePayment = $this->canRead($ctx, $actor, 'fnb.payment.collect') || $this->canRead($ctx, $actor, 'fnb.report.financial.view');
        $payments = $canSeePayment
            ? $this->scope('fnb_payments', $ctx)->whereIn('check_id', $checks->pluck('id'))->get([
                'id', 'public_id', 'check_id', 'payment_method_id', 'method_code_snapshot', 'method_name_snapshot', 'method_kind_snapshot',
                'status', 'currency', 'amount_minor', 'tendered_minor', 'change_minor', 'reserved_at', 'processed_at', 'cancelled_at', 'expired_at',
            ])->groupBy('check_id')
            : collect();
        $refunds = $canSeePayment
            ? $this->scope('fnb_refunds', $ctx)->whereIn('check_id', $checks->pluck('id'))->get([
                'id', 'public_id', 'payment_id', 'check_id', 'refund_no', 'status', 'amount_minor', 'reason', 'requested_at', 'processed_at', 'cancelled_at',
            ])->groupBy('payment_id')
            : collect();
        $orders = $orders->map(function ($order) use ($lines, $lineModifiers): array {
            return $this->decode($order) + ['lines' => ($lines[$order->id] ?? collect())->map(fn ($line) => $this->decode($line) + [
                'modifiers' => ($lineModifiers[$line->id] ?? collect())->all(),
                'modifier_option_ids' => ($lineModifiers[$line->id] ?? collect())->pluck('modifier_option_id')->all(),
            ])->all()];
        })->groupBy('session_id');
        $checks = $checks->map(function ($check) use ($checkLines, $payments, $refunds, $financials, $canSeePayment): array {
            $financial = isset($financials[$check->id]) ? (array) $financials[$check->id] : [];
            unset($financial['id'], $financial['website_key'], $financial['outlet_id'], $financial['check_id']);
            $checkPayments = $payments[$check->id] ?? collect();
            // Refunds never reopen the original bill. Reservations reduce collectable capacity, not gross debt.
            $due = max(0, (int) ($check->settlement_total_minor ?? $check->grand_total_minor) - (int) ($financial['gross_paid_total_minor'] ?? 0));
            $reserved = (int) $checkPayments->whereIn('status', ['reserved', 'processing', 'uncertain', 'reconciling'])->sum('amount_minor');
            $paymentProjection = $canSeePayment ? [
                'financial_projection' => $financial,
                'payment_status' => $financial['payment_status'] ?? 'unpaid',
                'refund_status' => $financial['refund_status'] ?? 'none',
                'balance_due_minor' => $due,
                'available_to_collect_minor' => $check->settlement_total_minor === null ? null : max(0, $due - $reserved),
            ] : [];

            return $this->decode($check) + $paymentProjection + [
                'lines' => ($checkLines[$check->id] ?? collect())->map(fn ($line) => $this->decode($line))->all(),
                'payments' => $checkPayments->map(fn ($payment) => $this->decode($payment) + ['refunds' => ($refunds[$payment->id] ?? collect())->all()])->all(),
            ];
        })->groupBy('session_id');
        $catalog = $this->service->catalog($ctx);

        return [
            'outlet' => $settings['outlet'],
            'business_day' => $this->currentDay($ctx),
            'shift' => $this->scope('fnb_shifts', $ctx)->where('status', 'open')->when($ctx->terminalId, fn ($q, $terminal) => $q->where('terminal_id', $terminal))->first(),
            'areas' => $settings['areas'], 'terminals' => $settings['terminals'], 'stations' => $settings['stations'],
            'payment_methods' => $settings['payment_methods'],
            'active_sessions' => $sessions->map(fn ($session) => $this->decode($session) + [
                'tables' => ($sessionTables[$session->id] ?? collect())->all(),
                'table' => ($sessionTables[$session->id] ?? collect())->first(),
                'table_ids' => ($sessionTables[$session->id] ?? collect())->pluck('id')->all(),
                'orders' => ($orders[$session->id] ?? collect())->values()->all(),
                'checks' => ($checks[$session->id] ?? collect())->values()->all(),
            ])->all(),
            'published_menu' => $catalog['resource'] ?? $catalog,
        ];
    }

    private function settings(FnbContext $ctx): array
    {
        $tables = $this->scope('fnb_dining_tables', $ctx)->orderBy('sort_order')->get([
            'id', 'service_area_id', 'code', 'name', 'capacity', 'status', 'sort_order', 'version',
        ])->groupBy('service_area_id');
        $areas = $this->scope('fnb_service_areas', $ctx)->orderBy('sort_order')->get()->map(fn ($area) => (array) $area + ['tables' => ($tables[$area->id] ?? collect())->all()]);

        return [
            'outlet' => $this->decode(DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->where('id', $ctx->outletId)->firstOrFail()),
            'terminals' => $this->scope('fnb_terminals', $ctx)->orderBy('id')->get(['id', 'public_id', 'code', 'name', 'type', 'status', 'version'])->all(),
            'areas' => $areas->all(),
            'tables' => $tables->flatten(1)->all(),
            'stations' => $this->scope('fnb_prep_stations', $ctx)->orderBy('sort_order')->get()->all(),
            'payment_methods' => $this->scope('fnb_payment_methods', $ctx)->orderBy('sort_order')->get(['id', 'code', 'name', 'kind', 'requires_reference', 'status', 'version'])->all(),
            'policies' => ['online_required' => true, 'money_unit' => 'minor', 'manual_transfer_confirmation' => true],
        ];
    }

    private function dashboard(FnbContext $ctx, Admin $actor): array
    {
        $report = $this->service->summaryReport($ctx);
        $report = $report['resource'] ?? $report;
        if (! $this->canRead($ctx, $actor, 'fnb.report.financial.view')) {
            $report = [];
        }

        return [
            'business_day' => $this->currentDay($ctx),
            'open_shifts' => $this->scope('fnb_shifts', $ctx)->where('status', 'open')->when($ctx->terminalId, fn ($q, $terminal) => $q->where('terminal_id', $terminal))->count(),
            'active_sessions' => $this->scope('fnb_service_sessions', $ctx)->whereNotIn('status', ['closed', 'cancelled', 'merged'])->count(),
            'report' => $report,
        ];
    }

    private function recipes(FnbContext $ctx): array
    {
        $recipes = DB::table('fnb_recipes')->where('website_key', $ctx->websiteKey)->orderBy('id')->get();
        $lines = DB::table('fnb_recipe_lines')->where('website_key', $ctx->websiteKey)->whereIn('recipe_id', $recipes->pluck('id'))->get()->groupBy('recipe_id');
        $bindings = DB::table('fnb_variant_recipes')->where('website_key', $ctx->websiteKey)->whereNotNull('current_slot')->get()->groupBy('recipe_id');

        return [
            'ingredients' => DB::table('fnb_ingredients')->where('website_key', $ctx->websiteKey)->orderBy('name')->get()->all(),
            'unit_conversions' => DB::table('fnb_unit_conversions')->where('website_key', $ctx->websiteKey)->orderBy('id')->get()->all(),
            'recipes' => $recipes->map(fn ($recipe) => (array) $recipe + ['lines' => ($lines[$recipe->id] ?? collect())->all(), 'variant_bindings' => ($bindings[$recipe->id] ?? collect())->all()])->all(),
        ];
    }

    private function currentDay(FnbContext $ctx): ?object
    {
        return $this->scope('fnb_business_days', $ctx)->where('status', 'open')->first();
    }

    private function scope(string $table, FnbContext $ctx): Builder
    {
        return DB::table($table)->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId);
    }

    private function canRead(FnbContext $ctx, Admin $actor, string $permission): bool
    {
        try {
            $this->access->authorizeTerminal($actor, $ctx->websiteKey, $ctx->outletId, $ctx->terminalId, $permission);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function decode(object $record): array
    {
        $values = (array) $record;
        foreach ($values as $key => $value) {
            if (in_array($key, ['customer_snapshot', 'billing_snapshot', 'buyer_snapshot', 'recipe_snapshot', 'recipe_snapshot_hash', 'request_fingerprint', 'request_hash', 'token_hash', 'qr_token_hash', 'device_uid', 'metadata', 'idempotency_key', 'provider_connection_key', 'provider_operation_key', 'provider_reference', 'reference'], true)) {
                unset($values[$key]);

                continue;
            }
            if (is_string($value) && (str_ends_with($key, '_snapshot') || in_array($key, ['settings', 'payload', 'snapshot'], true))) {
                $values[$key] = json_decode($value, true) ?? $value;
            }
        }

        return $values;
    }
}
