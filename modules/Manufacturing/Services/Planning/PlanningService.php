<?php

namespace Modules\Manufacturing\Services\Planning;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Engineering\EngineeringService;
use Modules\Manufacturing\Services\ManufacturingData as D;

final class PlanningService
{
    public function __construct(private readonly EngineeringService $engineering) {}

    public function demand(object $factory, array $data, ?string $resource): array
    {
        $v = validator($data, ['code' => 'required|string|max:60', 'style_id' => 'required|uuid',
            'type' => 'required|in:order,forecast,replenishment', 'due_date' => 'required|date_format:Y-m-d',
            'customer_name' => 'nullable|string|max:255', 'forecast_id' => 'nullable|uuid', 'lines' => 'required|array|min:1|max:400'])->validate();
        $style = D::row('mfg_styles', $v['style_id'], $factory->company_id);
        $revision = $this->engineering->currentRevision($style);
        abort_unless($revision->status === 'approved', 422, 'Chọn mã hàng có phiên bản kỹ thuật đã duyệt.');
        $old = $resource ? D::row('mfg_demands', $resource, $factory->company_id, $factory->id, true) : null;
        if ($old) {
            abort_unless($old->source_type === ($v['type'] === 'order' ? 'customer_order' : $v['type']), 409, 'Không đổi loại nguồn của nhu cầu; tạo nhu cầu mới có liên kết thay thế.');
            abort_if(DB::table('mfg_orders')->where('demand_id', $old->id)->where('status', '!=', 'cancelled')->exists(), 409, 'Nhu cầu đã có lệnh; tạo thay đổi có kiểm soát thay vì sửa trực tiếp.');
        }
        $forecast = ! empty($v['forecast_id']) ? D::row('mfg_demands', $v['forecast_id'], $factory->company_id, $factory->id, true) : null;
        if ($forecast) {
            abort_unless($v['type'] === 'order' && $forecast->source_type === 'forecast' && substr($v['due_date'], 0, 7) === substr($forecast->due_date, 0, 7), 422, 'Forecast được tiêu thụ phải cùng tháng cam kết và cùng phân xưởng.');
        }
        $seen = [];
        $lines = [];
        foreach ($v['lines'] as $line) {
            $variant = $this->engineering->variant($revision, (string) ($line['color'] ?? ''), (string) ($line['size'] ?? ''));
            abort_if(isset($seen[$variant['sku_id']]), 422, 'Mỗi biến thể chỉ được xuất hiện một lần.');
            $seen[$variant['sku_id']] = true;
            $quantity = D::pieces($line['quantity'] ?? '');
            if ($forecast) {
                $forecastLine = DB::table('mfg_demand_lines')->where('demand_id', $forecast->id)->where('sku_id', $variant['sku_id'])->lockForUpdate()->first();
                abort_unless($forecastLine, 422, 'Biến thể không nằm trong forecast.');
                $consumed = DB::table('mfg_demand_lines as l')->join('mfg_demands as d', 'd.id', '=', 'l.demand_id')
                    ->where('d.source_id', $forecast->public_id)->where('d.status', '!=', 'cancelled')->where('l.sku_id', $variant['sku_id'])
                    ->when($old, fn ($q) => $q->where('d.id', '!=', $old->id))->pluck('l.quantity');
                $total = BigDecimal::of($quantity);
                foreach ($consumed as $q) {
                    $total = $total->plus($q);
                }
                abort_if($total->plus((string) $forecastLine->allocated_quantity)->isGreaterThan((string) $forecastLine->quantity), 422, 'Forecast đã được tiêu thụ hoặc phân bổ; giảm lệnh forecast trước khi chuyển sang đơn thực.');
            }
            if ($old && $old->source_type === 'forecast') {
                $consumed = $this->forecastConsumption($old, (int) $variant['sku_id']);
                abort_if($consumed->isGreaterThan($quantity), 409, 'Không được giảm forecast dưới lượng đơn thực đã tiêu thụ.');
            }
            $lines[] = ['style_id' => $style->id, 'style_revision_id' => $revision->id, 'sku_id' => $variant['sku_id'],
                'variant' => D::encode(['color' => $variant['color'], 'size' => $variant['size']]), 'quantity' => $quantity];
        }
        if ($old && $old->source_type === 'forecast') {
            foreach (DB::table('mfg_demand_lines')->where('demand_id', $old->id)->pluck('sku_id') as $skuId) {
                abort_if(! isset($seen[$skuId]) && $this->forecastConsumption($old, (int) $skuId)->isPositive(), 409, 'Không được bỏ biến thể forecast đã được đơn thực tiêu thụ.');
            }
        }
        $duplicate = DB::table('mfg_demands')->where('company_id', $factory->company_id)->where('code', $v['code'])
            ->when($old, fn ($q) => $q->where('id', '!=', $old->id))->exists();
        abort_if($duplicate, 422, 'Mã nhu cầu đã tồn tại.');
        $values = ['code' => $v['code'], 'source_type' => $v['type'] === 'order' ? 'customer_order' : $v['type'],
            'source_id' => $forecast?->public_id, 'due_date' => $v['due_date'], 'status' => 'open',
            'buyer_snapshot' => D::encode(['name' => $v['customer_name'] ?? null]), 'metadata' => D::encode(['style_id' => $style->id])];
        $demand = $old ? D::update('mfg_demands', $old, $values, $data['expected_version'] ?? null)
            : D::insert('mfg_demands', $values + ['company_id' => $factory->company_id, 'factory_id' => $factory->id]);
        if ($old) {
            DB::table('mfg_demand_lines')->where('demand_id', $old->id)->delete();
        }
        foreach ($lines as $line) {
            D::insert('mfg_demand_lines', $line + ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'demand_id' => $demand->id, 'status' => 'open']);
        }

        return D::resource($demand);
    }

    public function order(object $factory, array $data, ?string $resource, Admin $actor): array
    {
        $v = validator($data, ['code' => 'required|string|max:60', 'demand_id' => 'required|uuid', 'lines' => 'required|array|min:1|max:400',
            'workshop_id' => 'required|uuid', 'line_id' => 'nullable|uuid', 'planned_start' => 'nullable|date_format:Y-m-d', 'planned_end' => 'nullable|date_format:Y-m-d'])->validate();
        $demand = D::row('mfg_demands', $v['demand_id'], $factory->company_id, lock: true);
        if ($demand->factory_id && (int) $demand->factory_id !== (int) $factory->id) {
            app(ManufacturingAccessService::class)->factory($actor, (int) $demand->factory_id, 'manufacturing.plan', true);
        }
        abort_unless($demand->status === 'open', 422, 'Nhu cầu không còn mở.');
        $workshop = D::row('mfg_workshops', $v['workshop_id'], $factory->company_id, $factory->id);
        $line = ! empty($v['line_id']) ? D::row('mfg_lines', $v['line_id'], $factory->company_id, $factory->id) : null;
        abort_if($line && (int) $line->workshop_id !== (int) $workshop->id, 422, 'Chuyền không thuộc phân xưởng.');
        $old = $resource ? D::row('mfg_orders', $resource, $factory->company_id, $factory->id, true) : null;
        abort_if($old && $old->status !== 'draft', 409, 'Chỉ sửa lệnh nháp.');
        abort_if($old && (D::json($old->metadata)['schedule']['status'] ?? null) === 'committed', 409, 'Kế hoạch đã chốt; dùng điều chỉnh lịch có lý do.');
        if ($old) {
            // Draft allocations are released before calculating the new allocation, inside the command transaction.
            foreach (DB::table('mfg_order_lines')->where('order_id', $old->id)->get() as $prior) {
                $d = DB::table('mfg_demand_lines')->where('id', $prior->demand_line_id)->lockForUpdate()->first();
                DB::table('mfg_demand_lines')->where('id', $d->id)->update(['allocated_quantity' => (string) BigDecimal::of((string) $d->allocated_quantity)->minus((string) $prior->quantity)]);
            }
            DB::table('mfg_allocations')->where('order_id', $old->id)->delete();
            DB::table('mfg_order_lines')->where('order_id', $old->id)->delete();
        }
        $orderLines = [];
        $revision = null;
        $seen = [];
        foreach ($v['lines'] as $input) {
            $match = DB::table('mfg_demand_lines')->where('demand_id', $demand->id)->lockForUpdate()->get()->first(function ($l) use ($input): bool {
                $variant = D::json($l->variant);

                return ($variant['color'] ?? null) === ($input['color'] ?? null) && ($variant['size'] ?? null) === ($input['size'] ?? null);
            });
            abort_unless($match && ! isset($seen[$match->id]), 422, 'Biến thể lệnh không thuộc nhu cầu hoặc bị trùng.');
            $seen[$match->id] = true;
            $quantity = D::pieces($input['quantity'] ?? '');
            $consumed = $demand->source_type === 'forecast' ? $this->forecastConsumption($demand, (int) $match->sku_id) : BigDecimal::zero();
            abort_if(BigDecimal::of((string) $match->allocated_quantity)->plus($quantity)->plus($consumed)->isGreaterThan((string) $match->quantity), 409, 'Phân bổ vượt nhu cầu sau khi trừ đơn thực đã tiêu thụ forecast.');
            $r = D::row('mfg_style_revisions', (int) $match->style_revision_id, $factory->company_id);
            abort_if($revision && $revision->id !== $r->id, 422, 'Một lệnh chỉ sử dụng một phiên bản kỹ thuật.');
            $revision = $r;
            $orderLines[] = ['demand_line_id' => $match->id, 'sku_id' => $match->sku_id, 'variant' => $match->variant, 'quantity' => $quantity];
            DB::table('mfg_demand_lines')->where('id', $match->id)->update(['allocated_quantity' => (string) BigDecimal::of((string) $match->allocated_quantity)->plus($quantity)]);
        }
        $duplicate = DB::table('mfg_orders')->where('company_id', $factory->company_id)->where('code', $v['code'])->when($old, fn ($q) => $q->where('id', '!=', $old->id))->exists();
        abort_if($duplicate, 422, 'Mã lệnh đã tồn tại.');
        $values = ['code' => $v['code'], 'demand_id' => $demand->id, 'style_revision_id' => $revision->id,
            'due_date' => $v['planned_end'] ?? $demand->due_date, 'planned_start_date' => $v['planned_start'] ?? null,
            'buyer_snapshot' => $demand->buyer_snapshot, 'metadata' => D::encode(['workshop_id' => $workshop->id, 'line_id' => $line?->id])];
        $order = $old ? D::update('mfg_orders', $old, $values, $data['expected_version'] ?? null)
            : D::insert('mfg_orders', $values + ['company_id' => $factory->company_id, 'factory_id' => $factory->id]);
        foreach ($orderLines as $item) {
            $ol = D::insert('mfg_order_lines', $item + ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'order_id' => $order->id]);
            D::insert('mfg_allocations', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
                'order_id' => $order->id, 'order_line_id' => $ol->id, 'workshop_id' => $workshop->id, 'line_id' => $line?->id,
                'quantity' => $item['quantity'], 'starts_at' => $order->planned_start_date, 'ends_at' => $order->due_date, 'status' => 'planned']);
        }

        return D::resource($order);
    }

    public function release(object $factory, string $id, array $data, Admin $actor): array
    {
        $order = D::row('mfg_orders', $id, $factory->company_id, $factory->id, true);
        abort_unless($order->status === 'draft', 409, 'Lệnh đã phát hành hoặc đã kết thúc.');
        $revision = D::row('mfg_style_revisions', (int) $order->style_revision_id, $factory->company_id);
        abort_unless($revision->status === 'approved', 422, 'Phiên bản kỹ thuật chưa duyệt.');
        $lines = DB::table('mfg_order_lines')->where('order_id', $order->id)->get()->map(fn ($r) => (array) $r)->all();
        $snapshot = ['style_revision_id' => $revision->public_id, 'revision' => $revision->revision,
            'bom' => D::json($revision->bom), 'routing' => D::json($revision->routing), 'measurements' => D::json($revision->measurements),
            'variants' => D::json($revision->metadata)['variants'] ?? [], 'lines' => $lines];
        foreach ($this->engineering->requirements($revision, $lines) as $r) {
            D::insert('mfg_material_requirements', $r + ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'order_id' => $order->id, 'status' => 'open']);
        }
        $order = D::update('mfg_orders', $order, ['status' => 'released', 'released_snapshot' => D::encode($snapshot),
            'released_by' => $actor->id, 'released_at' => now()], $data['expected_version'] ?? null);
        D::event($factory, 'order.released', $order->id, null, ['source_ref' => $order->public_id, 'revision' => $revision->revision], $actor->id);

        return D::resource($order);
    }

    public function cancel(object $factory, string $id, array $data): array
    {
        $order = D::row('mfg_orders', $id, $factory->company_id, $factory->id, true);
        abort_unless($order->status === 'draft', 409, 'Lệnh đã phát hành cần quy trình đối soát trước khi hủy.');
        foreach (DB::table('mfg_order_lines')->where('order_id', $order->id)->get() as $l) {
            $d = DB::table('mfg_demand_lines')->where('id', $l->demand_line_id)->lockForUpdate()->first();
            DB::table('mfg_demand_lines')->where('id', $d->id)->update(['allocated_quantity' => (string) BigDecimal::of((string) $d->allocated_quantity)->minus((string) $l->quantity)]);
        }

        return D::resource(D::update('mfg_orders', $order, ['status' => 'cancelled'], $data['expected_version'] ?? null));
    }

    private function forecastConsumption(object $forecast, int $skuId): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach (DB::table('mfg_demand_lines as l')->join('mfg_demands as d', 'd.id', '=', 'l.demand_id')
            ->where('d.source_id', $forecast->public_id)->where('d.status', '!=', 'cancelled')->where('l.sku_id', $skuId)->pluck('l.quantity') as $quantity) {
            $total = $total->plus($quantity);
        }

        return $total;
    }
}
