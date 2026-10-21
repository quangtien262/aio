<?php

namespace Modules\Manufacturing\Services\Execution;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Manufacturing\Services\ManufacturingData as D;

final class SubcontractService
{
    public function __construct(private readonly ExecutionService $execution) {}

    public function dispatch(object $factory, array $data, Admin $actor): array
    {
        $v = validator($data, ['bundle_id' => 'required|uuid', 'vendor_name' => 'required|string|max:120', 'process' => 'required|string|max:80',
            'quantity' => 'required', 'unit_cost' => 'required', 'due_date' => 'nullable|date_format:Y-m-d'])->validate();
        $b = D::row('mfg_bundles', $v['bundle_id'], $factory->company_id, $factory->id, true);
        abort_unless($b->status === 'active' && $b->operation_key === $v['process'], 409, 'Gia công phải đúng công đoạn hiện tại của bó.');
        abort_if(isset(D::json($b->metadata)['operation_targets'][$b->operation_key]), 409, 'Không gửi gia công một công đoạn đã ghi dở.');
        $qty = D::pieces($v['quantity']);
        abort_if(BigDecimal::of($qty)->isGreaterThan(BigDecimal::of((string) $b->quantity)->minus(D::json($b->metadata)['scrap_quantity'] ?? '0')), 422, 'Gửi vượt lượng còn lại sau loại.');
        if (BigDecimal::of($qty)->isLessThan((string) $b->quantity)) {
            $b = $this->execution->splitRow($b, $qty, $data['expected_version'] ?? null);
        } else {
            abort_unless((int) ($data['expected_version'] ?? 0) === (int) $b->version, 409);
        }
        $s = D::insert('mfg_subcontracts', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $b->order_id, 'supplier_ref' => $v['vendor_name'], 'code' => 'SUB-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 16)),
            'operation_key' => $b->operation_key, 'quantity' => $qty, 'sent_quantity' => $qty, 'status' => 'sent',
            'metadata' => D::encode(['bundle_id' => $b->id, 'due_date' => $v['due_date'] ?? null, 'unit_cost' => (string) D::decimal($v['unit_cost'], true)])]);
        D::update('mfg_bundles', $b, ['status' => 'subcontracted'], $b->version);
        D::event($factory, 'subcontract.dispatched', $b->order_id, $b->id, ['quantity' => $qty, 'source_ref' => $s->public_id], $actor->id);

        return D::resource($s);
    }

    public function receive(object $factory, string $resource, array $data, Admin $actor): array
    {
        $s = D::row('mfg_subcontracts', $resource, $factory->company_id, $factory->id, true);
        abort_unless(in_array($s->status, ['sent', 'partially_received'], true), 409, 'Phiếu gia công đã kết thúc.');
        $meta = D::json($s->metadata);
        $b = D::row('mfg_bundles', (int) $meta['bundle_id'], $factory->company_id, $factory->id, true);
        $qty = D::pieces($data['quantity'] ?? '');
        $good = D::pieces($data['good_quantity'] ?? '0', true);
        $rework = D::pieces($data['rework_quantity'] ?? '0', true);
        $scrap = D::pieces($data['scrap_quantity'] ?? '0', true);
        abort_unless(BigDecimal::of($good)->plus($rework)->plus($scrap)->isEqualTo($qty), 422, 'Lượng nhận gia công chưa cân bằng.');
        $received = BigDecimal::of((string) $s->received_quantity)->plus($qty);
        abort_if($received->isGreaterThan((string) $s->sent_quantity), 409, 'Nhận vượt lượng còn ở đối tác.');
        if (BigDecimal::of($qty)->isLessThan((string) $b->quantity)) {
            $b = $this->execution->splitRow($b, $qty, $b->version);
        }
        $fee = D::decimal($meta['unit_cost'], true)->multipliedBy($good)->toScale(6, RoundingMode::HalfUp);
        $metaB = D::json($b->metadata);
        $metaB['subcontract_receipt'] = ['good' => $good, 'scrap' => $scrap];
        $b = D::update('mfg_bundles', $b, ['status' => BigDecimal::of($rework)->isPositive() ? 'quality_hold' : 'active', 'metadata' => D::encode($metaB)], $b->version);
        if (BigDecimal::of($rework)->isPositive()) {
            D::insert('mfg_reworks', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
                'order_id' => $b->order_id, 'bundle_id' => $b->id, 'quantity' => $rework, 'reason' => 'Tái kiểm hàng gia công',
                'operation_key' => $b->operation_key, 'status' => 'open', 'metadata' => D::encode(['subcontract_completion' => true,
                    'subcontract_id' => $s->public_id, 'unit_cost' => $meta['unit_cost']])]);
        } else {
            $this->execution->operation($factory, $b->public_id, ['expected_version' => $b->version,
                'quantity' => $qty, 'good_quantity' => $good, 'scrap_quantity' => $scrap], $actor);
        }
        D::insert('mfg_cost_entries', ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'order_id' => $s->order_id,
            'type' => 'subcontract', 'source_module' => 'manufacturing', 'source_id' => $s->public_id.':'.$s->version,
            'amount' => (string) $fee, 'description' => 'Nghiệm thu gia công '.$s->code, 'status' => 'approved']);
        $s = D::update('mfg_subcontracts', $s, ['received_quantity' => (string) $received,
            'scrap_quantity' => (string) BigDecimal::of((string) $s->scrap_quantity)->plus($scrap),
            'fee_amount' => (string) BigDecimal::of((string) $s->fee_amount)->plus($fee),
            'status' => $received->isEqualTo((string) $s->sent_quantity) ? 'received' : 'partially_received'], $data['expected_version'] ?? null);
        D::event($factory, 'subcontract.received', $s->order_id, $b->id, ['quantity' => $qty, 'source_ref' => $s->public_id], $actor->id);

        return D::resource($s);
    }
}
