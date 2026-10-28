<?php

namespace Modules\Pharmacy\Services\Quality;

use App\Models\Admin;
use App\Support\AuditLogger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;

/** Regulatory case evidence and ledger reconciliation; closure never releases recalled stock. */
final class PharmacyRecallService
{
    public function show(Admin $actor, string $id): array
    {
        $recall = D::row('ph_recalls', $id);
        $chain = $this->access()->chain($actor, (int) $recall->chain_id, 'pharmacy.recall.manage');
        app(AuditLogger::class)->record('pharmacy.recall.trace.read', 'pharmacy-recall', null, ['id' => $recall->public_id], $actor, 'pharmacy', '', 'pharmacy');

        return $this->projection($recall, $chain);
    }

    public function reconcile(Admin $actor, array $input, string $key): array
    {
        validator($input, ['pharmacy_id' => 'required|uuid', 'task_id' => 'required|uuid', 'expected_version' => 'required|integer|min:1', 'physical_quantity' => 'required', 'reason' => 'required|string|max:4000'])->validate();

        return $this->commands()->run($actor, 'recall.reconcile', $input, $key,
            function (Admin $live) use ($input): array {
                $p = $this->access()->pharmacy($live, $input['pharmacy_id'], 'pharmacy.recall.manage', true);
                $this->access()->professional($live, $p, 'review', true);

                return ['pharmacy' => $p, 'permission' => 'pharmacy.recall.manage'];
            }, function (Admin $live, array $scope) use ($input): array {
                $p = $scope['pharmacy'];
                $taskRef = D::row('ph_recall_tasks', $input['task_id'], $p->chain_id, $p->id);
                $recall = D::row('ph_recalls', (int) $taskRef->recall_id, $p->chain_id, lock: true);
                abort_if($recall->status === 'closed', 409, 'Hồ sơ đã đóng; cần hồ sơ mới khi phát sinh hàng thu hồi mới.');
                $task = D::row('ph_recall_tasks', $input['task_id'], $p->chain_id, $p->id, true);
                abort_unless((int) $task->version === (int) $input['expected_version'], 409, 'Nhiệm vụ vừa thay đổi.');
                $this->lockWarehouses([(int) $p->warehouse_id]);
                $chain = D::row('ph_chains', (int) $p->chain_id);
                $trace = $this->projection($recall, $chain);
                $site = collect($trace['sites'])->firstWhere('id', $p->public_id);
                $physical = D::decimal($input['physical_quantity'], true);
                $matches = $physical->isEqualTo($site['on_hand']);
                $evidence = ['recall_id' => $recall->public_id, 'task_id' => $task->public_id, 'physical_quantity' => (string) $physical, 'ledger_on_hand' => $site['on_hand'], 'snapshot_fingerprint' => $site['snapshot_fingerprint'], 'recorded_by' => $live->id, 'recorded_at' => now()->toIso8601String(), 'encrypted_reason' => Crypt::encryptString($input['reason'])];
                $case = D::insert('ph_quality_cases', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'product_id' => $recall->product_id, 'batch_id' => $recall->batch_id, 'kind' => 'recall_reconciliation', 'reason' => 'recall_reconciliation', 'evidence' => D::encode($evidence), 'actor_id' => $live->id, 'status' => $matches ? 'reconciled' : 'discrepancy']);
                $task = D::update('ph_recall_tasks', $task, ['status' => $matches ? 'reconciled' : 'discrepancy', 'isolated_quantity' => (string) $physical, 'confirmed_by' => $live->id, 'confirmed_at' => now()], $input['expected_version']);
                $caseEvidence = D::json($recall->evidence);
                $caseEvidence['reconciliations'][$p->public_id] = ['quality_case_id' => $case->public_id, 'task_id' => $task->public_id, 'task_version' => (int) $task->version, 'physical_quantity' => (string) $physical, 'snapshot_fingerprint' => $site['snapshot_fingerprint'], 'reviewer_id' => $live->id, 'recorded_at' => now()->toIso8601String()];
                $recall = D::update('ph_recalls', $recall, ['evidence' => D::encode($caseEvidence)], $recall->version);
                $this->commands()->emit($p, 'pharmacy.recall.reconciled', 'recall-task', $task->public_id, (int) $task->version);

                return ['id' => $task->public_id, 'status' => $task->status, 'version' => (int) $task->version, 'recall_id' => $recall->public_id, 'case_version' => (int) $recall->version, 'quality_case_id' => $case->public_id];
            });
    }

    public function close(Admin $actor, array $input, string $key): array
    {
        validator($input, ['recall_id' => 'required|uuid', 'expected_version' => 'required|integer|min:1', 'reason' => 'required|string|max:4000', 'consumer_followup_evidence' => 'required|string|max:4000', 'consumer_followup_completed' => 'required|accepted'])->validate();

        return $this->commands()->run($actor, 'recall.close', $input, $key,
            function (Admin $live) use ($input): array {
                $recall = D::row('ph_recalls', $input['recall_id']);

                return ['chain' => $this->access()->chain($live, (int) $recall->chain_id, 'pharmacy.recall.manage', true), 'permission' => 'pharmacy.recall.manage'];
            }, function (Admin $live, array $scope) use ($input): array {
                // The same chain lock serializes new-site/recall creation; owner policy takes a shared chain lock for every stock write.
                $chain = D::row('ph_chains', (int) $scope['chain']->id, lock: true);
                $recall = D::row('ph_recalls', $input['recall_id'], $chain->id, lock: true);
                abort_unless((int) $recall->version === (int) $input['expected_version'] && $recall->status !== 'closed', 409, 'Hồ sơ vừa thay đổi hoặc đã đóng.');
                $this->lockWarehouses(DB::table('ph_pharmacies')->where('chain_id', $chain->id)->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all());
                $trace = $this->projection($recall, $chain);
                abort_unless($trace['integrity_issues'] === [], 409, 'Ledger có sai lệch cần điều tra.');
                foreach (['on_hand', 'in_transit', 'reserved', 'pending_handover'] as $field) {
                    abort_unless(BigDecimal::of($trace['totals'][$field])->isZero(), 409, 'Còn hàng, điều chuyển, giữ hàng hoặc bàn giao chưa xử lý.');
                }
                $caseEvidence = D::json($recall->evidence);
                foreach ($trace['sites'] as $site) {
                    if (! $site['task']) {
                        abort_unless($site['status'] !== 'active', 409, 'Điểm hoạt động chưa có nhiệm vụ thu hồi.');

                        continue;
                    }
                    $review = $caseEvidence['reconciliations'][$site['id']] ?? null;
                    abort_unless($site['task']['status'] === 'reconciled' && $review && BigDecimal::of($review['physical_quantity'])->isZero() && hash_equals($review['snapshot_fingerprint'], $site['snapshot_fingerprint']), 409, 'Mỗi điểm cần đối soát lại về không sau xử lý ledger.');
                    $reviewer = Admin::query()->find($review['reviewer_id']);
                    abort_unless($reviewer, 409, 'Không còn người xác nhận đối soát.');
                    $p = D::row('ph_pharmacies', $site['id'], $chain->id);
                    $this->access()->pharmacy($reviewer, $site['id'], 'pharmacy.recall.manage', true);
                    $this->access()->professional($reviewer, $p, 'review', true);
                }
                $caseEvidence['closure'] = ['closed_by' => $live->id, 'closed_at' => now()->toIso8601String(), 'human_followup_attested' => true, 'encrypted_evidence' => Crypt::encryptString(D::encode(['reason' => $input['reason'], 'consumer_followup_evidence' => $input['consumer_followup_evidence']]))];
                $recall = D::update('ph_recalls', $recall, ['status' => 'closed', 'evidence' => D::encode($caseEvidence)], $input['expected_version']);
                $this->commands()->emit(['chain' => $chain], 'pharmacy.recall.closed', 'recall', $recall->public_id, (int) $recall->version);

                return ['id' => $recall->public_id, 'version' => (int) $recall->version, 'status' => 'closed'];
            });
    }

    private function projection(object $recall, object $chain): array
    {
        $product = D::row('ph_products', (int) $recall->product_id, $chain->id);
        $batch = DB::table('inv_batches')->where('id', $recall->batch_id)->where('item_id', $product->inventory_item_id)->first();
        abort_unless($batch, 409, 'Định danh thuốc/lô của hồ sơ không còn khớp.');
        $pharmacies = DB::table('ph_pharmacies')->where('chain_id', $chain->id)->orderBy('id')->get();
        $byWarehouse = $pharmacies->keyBy('warehouse_id');
        $byPharmacy = $pharmacies->keyBy('id');
        $balancesByWarehouse = DB::table('inv_stock_balances')->whereIn('warehouse_id', $byWarehouse->keys())->where('item_id', $product->inventory_item_id)->where('batch_id', $batch->id)->get(['warehouse_id', 'quantity_on_hand', 'quantity_reserved'])->groupBy('warehouse_id');
        $tasksByPharmacy = DB::table('ph_recall_tasks')->where('recall_id', $recall->id)->get()->keyBy('pharmacy_id');
        $holdsById = DB::table('inv_stock_holds')->whereIn('public_id', $tasksByPharmacy->pluck('hold_id'))->get()->keyBy('public_id');
        $movementMarkers = DB::table('inv_stock_movements')->whereIn('warehouse_id', $byWarehouse->keys())->where('item_id', $product->inventory_item_id)->where('batch_id', $batch->id)->selectRaw('warehouse_id, MAX(id) as movement_marker')->groupBy('warehouse_id')->get()->keyBy('warehouse_id');
        $sites = [];
        $issues = [];
        $totals = array_fill_keys(['on_hand', 'in_transit', 'reserved', 'pending_handover'], '0.000000');
        foreach ($pharmacies as $p) {
            $balances = $balancesByWarehouse->get($p->warehouse_id, collect());
            $onHand = BigDecimal::zero();
            $stockReserved = BigDecimal::zero();
            foreach ($balances as $balance) {
                if (BigDecimal::of((string) $balance->quantity_on_hand)->isNegative()) {
                    $issues[] = 'negative_balance:'.$p->public_id;
                }
                if (BigDecimal::of((string) $balance->quantity_reserved)->isNegative() || BigDecimal::of((string) $balance->quantity_reserved)->isGreaterThan((string) $balance->quantity_on_hand)) {
                    $issues[] = 'invalid_reserved_balance:'.$p->public_id;
                }
                $onHand = $onHand->plus((string) $balance->quantity_on_hand);
                $stockReserved = $stockReserved->plus((string) $balance->quantity_reserved);
            }
            $task = $tasksByPharmacy->get($p->id);
            $hold = $task ? $holdsById->get($task->hold_id) : null;
            if ($hold && (int) $hold->warehouse_id !== (int) $p->warehouse_id) {
                $hold = null;
            }
            if ($task && (! $hold || $hold->status !== 'active' || $hold->reason_code !== 'recall')) {
                $issues[] = 'authoritative_hold_missing:'.$p->public_id;
            }
            $sites[$p->public_id] = ['id' => $p->public_id, 'name' => $p->name, 'status' => $p->status, 'on_hand' => (string) $onHand->toScale(6), 'in_transit' => '0.000000', 'reserved' => '0.000000', 'stock_reserved' => (string) $stockReserved, 'pending_handover' => '0.000000', 'task' => $task ? ['id' => $task->public_id, 'version' => (int) $task->version, 'status' => $task->status] : null, 'hold' => $hold ? ['id' => $hold->public_id, 'status' => $hold->status, 'version' => (int) $hold->version] : null, 'movement_marker' => $movementMarkers->get($p->warehouse_id)?->movement_marker];
        }
        $transfers = [];
        $transferRows = DB::table('inv_transfer_lines as l')->join('inv_transfers as t', 't.id', '=', 'l.transfer_id')->where('l.item_id', $product->inventory_item_id)->where('l.batch_id', $batch->id)->where(fn ($q) => $q->whereIn('t.source_warehouse_id', $byWarehouse->keys())->orWhereIn('t.destination_warehouse_id', $byWarehouse->keys()))->get(['t.public_id', 't.status', 't.source_warehouse_id', 't.destination_warehouse_id', 'l.id as line_id', 'l.quantity', 'l.received_quantity']);
        $transferLinks = DB::table('ph_transfers')->where('chain_id', $chain->id)->whereIn('inventory_transfer_id', $transferRows->pluck('public_id')->unique())->get(['inventory_transfer_id', 'public_id'])->keyBy('inventory_transfer_id');
        foreach ($transferRows as $row) {
            $pending = BigDecimal::of((string) $row->quantity)->minus((string) $row->received_quantity);
            if ($pending->isNegative()) {
                $issues[] = 'overreceived_transfer:'.$row->public_id;
            }
            $pending = BigDecimal::max($pending, 0)->toScale(6);
            $source = $byWarehouse->get($row->source_warehouse_id);
            $destination = $byWarehouse->get($row->destination_warehouse_id);
            $owner = $source ?? $destination;
            $sites[$owner->public_id]['in_transit'] = (string) BigDecimal::of($sites[$owner->public_id]['in_transit'])->plus($pending)->toScale(6);
            $link = $transferLinks->get($row->public_id);
            $transfers[] = ['id' => $link?->public_id, 'inventory_transfer_id' => $row->public_id, 'line_id' => $row->line_id, 'source_pharmacy_id' => $source?->public_id, 'destination_pharmacy_id' => $destination?->public_id, 'status' => $row->status, 'quantity' => (string) $row->quantity, 'received_quantity' => (string) $row->received_quantity, 'pending_quantity' => (string) $pending];
        }
        $dispensings = [];
        $allocations = DB::table('ph_dispensing_allocations as a')->join('ph_dispensing_lines as l', 'l.id', '=', 'a.dispensing_line_id')->join('ph_dispensings as d', 'd.id', '=', 'l.dispensing_id')->where('d.chain_id', $chain->id)->where('a.batch_id', $batch->id)->where('l.product_id', $product->id)->get(['a.id as allocation_internal_id', 'a.public_id as allocation_id', 'a.status as allocation_status', 'a.quantity', 'd.public_id', 'd.id as dispensing_internal_id', 'd.pharmacy_id', 'd.status', 'd.handed_over_at', 'l.public_id as line_id']);
        $returnsByAllocation = DB::table('ph_return_lines as r')->join('ph_dispensing_allocations as a', 'a.id', '=', 'r.allocation_id')->join('ph_dispensing_lines as l', 'l.id', '=', 'a.dispensing_line_id')->join('ph_dispensings as d', 'd.id', '=', 'l.dispensing_id')->where('d.chain_id', $chain->id)->where('a.batch_id', $batch->id)->where('l.product_id', $product->id)->get(['r.allocation_id', 'r.quantity'])->groupBy('allocation_id');
        $salesByDispensing = DB::table('ph_sales as s')->join('ph_dispensing_lines as l', 'l.dispensing_id', '=', 's.dispensing_id')->join('ph_dispensing_allocations as a', 'a.dispensing_line_id', '=', 'l.id')->where('s.chain_id', $chain->id)->where('a.batch_id', $batch->id)->where('l.product_id', $product->id)->distinct()->get(['s.dispensing_id', 's.public_id'])->keyBy('dispensing_id');
        foreach ($allocations as $row) {
            $p = $byPharmacy->get($row->pharmacy_id);
            if (! $p) {
                $issues[] = 'foreign_allocation_site:'.$row->allocation_id;

                continue;
            }
            $returned = BigDecimal::zero();
            foreach ($returnsByAllocation->get($row->allocation_internal_id, collect()) as $returnedLine) {
                $returned = $returned->plus((string) $returnedLine->quantity);
            }
            $unreturned = BigDecimal::of((string) $row->quantity)->minus($returned);
            if ($unreturned->isNegative()) {
                $issues[] = 'overreturned_allocation:'.$row->allocation_id;
            }
            $pending = $row->allocation_status === 'committed' && $row->handed_over_at === null ? BigDecimal::max($unreturned, 0) : BigDecimal::zero();
            if ($row->allocation_status === 'reserved') {
                $sites[$p->public_id]['reserved'] = (string) BigDecimal::of($sites[$p->public_id]['reserved'])->plus((string) $row->quantity)->toScale(6);
            }
            $sites[$p->public_id]['pending_handover'] = (string) BigDecimal::of($sites[$p->public_id]['pending_handover'])->plus($pending)->toScale(6);
            $dispensings[] = ['id' => $row->public_id, 'pharmacy_id' => $p->public_id, 'line_id' => $row->line_id, 'allocation_id' => $row->allocation_id, 'allocation_status' => $row->allocation_status, 'status' => $row->status, 'quantity' => (string) $row->quantity, 'returned_quantity' => (string) $returned->toScale(6), 'pending_handover_quantity' => (string) $pending->toScale(6), 'handed_over' => $row->handed_over_at !== null, 'sale_id' => $salesByDispensing->get($row->dispensing_internal_id)?->public_id];
        }
        foreach ($sites as &$site) {
            $site['reserved'] = (string) BigDecimal::max($site['reserved'], $site['stock_reserved'])->toScale(6);
            $site['snapshot_fingerprint'] = hash('sha256', D::encode([$site['id'], $site['on_hand'], $site['in_transit'], $site['reserved'], $site['pending_handover'], $site['movement_marker'], $site['hold']]));
            unset($site['movement_marker'], $site['stock_reserved']);
            foreach ($totals as $field => $quantity) {
                $totals[$field] = (string) BigDecimal::of($quantity)->plus($site[$field])->toScale(6);
            }
        }
        unset($site);
        $receipts = DB::table('ph_receipts as r')->join('ph_pharmacies as p', 'p.id', '=', 'r.pharmacy_id')->where('r.chain_id', $chain->id)->where('r.product_id', $product->id)->where('r.batch_id', $batch->id)->get(['r.public_id as id', 'p.public_id as pharmacy_id', 'r.status', 'r.quantity'])->map(fn ($r) => (array) $r)->all();

        return ['id' => $recall->public_id, 'version' => (int) $recall->version, 'status' => $recall->status, 'chain_id' => $chain->public_id, 'reference' => $recall->reference, 'as_of' => now()->toIso8601String(), 'product' => ['id' => $product->public_id, 'name' => $product->name], 'batch' => ['id' => $batch->id, 'batch_code' => $batch->batch_code, 'expires_at' => $batch->expires_at], 'totals' => $totals, 'sites' => array_values($sites), 'receipts' => $receipts, 'transfers' => $transfers, 'dispensings' => $dispensings, 'integrity_issues' => $issues, 'authoritative_hold_released' => false, 'automatic_consumer_contact' => false];
    }

    private function lockWarehouses(array $ids): void
    {
        DB::table('inv_warehouses')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function access(): PharmacyAccessService
    {
        return app(PharmacyAccessService::class);
    }

    private function commands(): PharmacyCommandService
    {
        return app(PharmacyCommandService::class);
    }
}
