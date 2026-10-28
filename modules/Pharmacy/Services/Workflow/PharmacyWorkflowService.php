<?php

namespace Modules\Pharmacy\Services\Workflow;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Sales\PharmacySaleService;

final class PharmacyWorkflowService
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        $permission = match ($action) {
            'shift.cash-movement' => 'pharmacy.shift.manage', 'replenishment.propose' => 'pharmacy.purchasing.manage', 'dispensing.handover-failed' => 'pharmacy.dispensing.handover', default => 'pharmacy.dispensing.manage'
        };

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key,
            fn ($live) => ['pharmacy' => $this->access()->pharmacy($live, $input['pharmacy_id'] ?? '', $permission, true), 'permission' => $permission],
            function ($live, $scope) use ($action, $input) {
                $p = $scope['pharmacy'];
                if ($action === 'shift.cash-movement') {
                    return $this->cash($live, $p, $input);
                }
                if ($action === 'replenishment.propose') {
                    return $this->replenish($live, $p, $input);
                }
                $d = D::row('ph_dispensings', (string) ($input['dispensing_id'] ?? ''), $p->chain_id, $p->id, true);
                abort_unless((int) ($input['expected_version'] ?? 0) === (int) $d->version, 409, 'Hồ sơ vừa thay đổi.');

                return match ($action) {
                    'dispensing.assign' => $this->assign($live, $p, $d, $input),
                    'dispensing.parties' => $this->parties($live, $p, $d, $input),
                    'dispensing.counsel', 'dispensing.handover-failed' => $this->evidence($live, $p, $d, $input, $action),
                    default => abort(422),
                };
            });
    }

    private function assign(Admin $actor, object $p, object $d, array $input): array
    {
        validator($input, ['assigned_to' => 'required|integer|exists:admins,id', 'stage' => 'required|in:prepare,review,payment,handover', 'priority' => 'sometimes|integer|between:0,3', 'due_at' => 'nullable|date|after:now'])->validate();
        abort_if(in_array($d->status, ['cancelled', 'returned', 'handed_over'], true), 409);
        $permission = match ($input['stage']) {
            'review' => 'pharmacy.dispensing.review', 'payment' => 'pharmacy.payment.collect', 'handover' => 'pharmacy.dispensing.handover', default => 'pharmacy.dispensing.manage'
        };
        $target = Admin::query()->findOrFail($input['assigned_to']);
        $this->access()->pharmacy($target, (int) $p->id, $permission, true);
        if (in_array($input['stage'], ['review', 'handover'], true)) {
            $this->access()->professional($target, $p, $input['stage'], true);
        }
        $task = DB::table('ph_work_tasks')->where('dispensing_id', $d->id)->lockForUpdate()->first();
        $values = ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'dispensing_id' => $d->id, 'assigned_to' => $target->id, 'assigned_by' => $actor->id, 'stage' => $input['stage'], 'priority' => $input['priority'] ?? 0, 'due_at' => $input['due_at'] ?? null];
        $task = $task ? D::update('ph_work_tasks', $task, $values, $task->version) : D::insert('ph_work_tasks', $values);
        $d = D::update('ph_dispensings', $d, [], $input['expected_version']);

        return ['id' => $d->public_id, 'status' => $d->status, 'version' => $d->version, 'task' => D::resource($task)];
    }

    private function parties(Admin $actor, object $p, object $d, array $input): array
    {
        $this->access()->pharmacy($actor, (int) $p->id, 'pharmacy.patient.sensitive', true);
        abort_unless($d->status === 'draft', 409, 'Chỉ khai báo quan hệ nhận/mua/trả tiền trước khi chuẩn bị.');
        $values = validator($input, ['buyer_name' => 'nullable|string|max:255', 'payer_name' => 'nullable|string|max:255', 'recipient_name' => 'nullable|string|max:255', 'relationship' => 'required|string|max:255', 'authority_evidence' => 'required|string|max:2000'])->validate();
        $snapshot = D::json(Crypt::decryptString($d->encrypted_snapshot));
        $snapshot['parties'] = $values;
        $d = D::update('ph_dispensings', $d, ['encrypted_snapshot' => Crypt::encryptString(D::encode($snapshot))], $input['expected_version']);

        return ['id' => $d->public_id, 'status' => $d->status, 'version' => $d->version];
    }

    private function evidence(Admin $actor, object $p, object $d, array $input, string $action): array
    {
        $this->access()->pharmacy($actor, (int) $p->id, 'pharmacy.patient.sensitive', true);
        $this->access()->professional($actor, $p, $action === 'dispensing.counsel' ? 'dispense' : 'handover', true);
        $v = validator($input, ['reason' => 'required|string|max:4000', 'recipient_name' => 'nullable|string|max:255'])->validate();
        if ($action === 'dispensing.handover-failed') {
            abort_unless($d->status === 'finalized' && $d->handed_over_at === null, 409, 'Chỉ ghi giao thất bại khi đã thanh toán, chưa bàn giao.');
            D::insert('ph_custody_events', ['dispensing_id' => $d->id, 'actor_id' => $actor->id, 'action' => 'handover_failed', 'encrypted_evidence' => Crypt::encryptString(D::encode($v))]);
        } else {
            abort_if(in_array($d->status, ['cancelled', 'returned'], true), 409);
        }
        D::insert('ph_counseling_evidence', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'dispensing_id' => $d->id, 'actor_id' => $actor->id, 'kind' => $action === 'dispensing.counsel' ? 'counseling' : 'handover_failed', 'encrypted_evidence' => Crypt::encryptString(D::encode($v))]);
        $d = D::update('ph_dispensings', $d, [], $input['expected_version']);

        // No stock reversal, money reversal, claim restoration, or false handed-over event.
        return ['id' => $d->public_id, 'status' => $d->status, 'version' => $d->version, 'handover_status' => $d->handed_over_at ? 'handed_over' : 'pending'];
    }

    private function cash(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['shift_id' => 'required|uuid', 'expected_version' => 'required|integer', 'direction' => 'required|in:in,out', 'amount_minor' => 'required', 'reason' => 'required|string|max:2000'])->validate();
        $shift = D::row('ph_shifts', $v['shift_id'], $p->chain_id, $p->id, true);
        abort_unless($shift->status === 'open' && (int) $shift->admin_id === (int) $actor->id, 403, 'Chỉ ghi thu/chi ở ca đang mở của mình.');
        $amount = D::money($v['amount_minor']);
        abort_unless($amount > 0, 422);
        if ($v['direction'] === 'out') {
            $current = collect(app(PharmacySaleService::class)->listShifts($actor, ['pharmacy_id' => $p->public_id])['records'])->firstWhere('id', $shift->public_id);
            abort_unless($current && $amount <= $current['expected_minor'], 422, 'Tiền chi vượt số tiền mặt dự kiến trong ca.');
        }
        D::update('ph_shifts', $shift, [], $v['expected_version']);

        return D::resource(D::insert('ph_cash_movements', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'shift_id' => $shift->id, 'actor_id' => $actor->id, 'direction' => $v['direction'], 'amount_minor' => $amount, 'encrypted_reason' => Crypt::encryptString($v['reason'])]));
    }

    private function replenish(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['coverage_days' => 'required|integer|between:1,90', 'product_ids' => 'required|array|min:1|max:100', 'product_ids.*' => 'required|uuid|distinct'])->validate();
        $lines = [];
        foreach ($v['product_ids'] as $id) {
            $product = D::row('ph_products', $id, $p->chain_id);
            $available = BigDecimal::of(app(InventoryV2Service::class)->availability($p->warehouse_id, $product->inventory_item_id));
            $sold = DB::table('ph_dispensing_lines as l')->join('ph_dispensings as d', 'd.id', '=', 'l.dispensing_id')->where('d.pharmacy_id', $p->id)->whereNotNull('d.handed_over_at')->where('d.handed_over_at', '>=', now()->subDays(30))->where('l.product_id', $product->id)->sum('l.quantity');
            $incoming = BigDecimal::zero();
            foreach (DB::table('ph_transfers')->where('destination_pharmacy_id', $p->id)->whereIn('status', ['dispatched', 'partially_received'])->get() as $transfer) {
                foreach (D::json($transfer->lines) as $line) {
                    if ((int) $line['item_id'] === (int) $product->inventory_item_id) {
                        $incoming = $incoming->plus(BigDecimal::of((string) $line['quantity'])->minus((string) ($line['received_quantity'] ?? '0')));
                    }
                }
            }
            $onOrder = BigDecimal::zero();
            if (app(ModuleCapabilityChecker::class)->enabled('purchasing')) {
                $orders = DB::table('purchase_order_lines as l')->join('ph_purchase_links as p', 'p.order_id', '=', 'l.order_id')->where('p.pharmacy_id', $p->id)->whereIn('p.status', ['approved', 'partially_received'])->where('l.sku_id', $product->sku_id)->get();
                foreach ($orders as $line) {
                    $onOrder = $onOrder->plus(BigDecimal::of((string) $line->quantity_ordered)->minus((string) $line->quantity_received));
                }
            }
            $target = BigDecimal::of((string) $sold)->multipliedBy($v['coverage_days'])->dividedBy(30, 6, RoundingMode::Up);
            $increment = BigDecimal::of((string) $product->minimum_increment);
            $needed = BigDecimal::max($target->minus($available)->minus($incoming)->minus($onOrder), 0);
            $proposed = $needed->dividedBy($increment, 0, RoundingMode::Up)->multipliedBy($increment)->toScale(6);
            $lines[] = ['product_id' => $product->public_id, 'name' => $product->name, 'unit' => $product->base_unit, 'available' => (string) $available, 'sold_30_days' => (string) $sold, 'incoming' => (string) $incoming, 'on_order' => (string) $onOrder, 'proposed_quantity' => (string) $proposed];
        }

        return D::resource(D::insert('ph_replenishment_proposals', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'actor_id' => $actor->id, 'coverage_days' => $v['coverage_days'], 'lines' => D::encode($lines), 'as_of' => now(), 'status' => 'proposed']));
    }

    private function access(): PharmacyAccessService
    {
        return app(PharmacyAccessService::class);
    }
}
