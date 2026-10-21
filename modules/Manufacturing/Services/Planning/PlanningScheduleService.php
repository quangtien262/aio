<?php

namespace Modules\Manufacturing\Services\Planning;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\ManufacturingData as D;

/** Manual scheduling controls and explicit warnings, not finite-capacity optimization. */
class PlanningScheduleService
{
    public function dispatch(Admin $actor, object $factory, string $operation, array $data, ?string $resource = null): array
    {
        app(ManufacturingAccessService::class)->factory($actor, (int) $factory->id, 'manufacturing.plan', true);

        return match ($operation) {
            'plan.calendar.save' => $this->calendar($factory, $data, $resource),
            'plan.commit', 'plan.reschedule' => $this->schedule($actor, $factory, $operation, $data, $resource),
            default => abort(422, 'Thao tác kế hoạch không hợp lệ.'),
        };
    }

    public function read(Admin $actor, object $factory, string $kind, array $query = []): array
    {
        $permission = $kind === 'engineering-impact' ? 'manufacturing.engineering' : 'manufacturing.plan';
        $access = app(ManufacturingAccessService::class);
        $access->factory($actor, (int) $factory->id, $permission);
        if ($kind === 'engineering-impact') {
            $access->company($actor, (int) $factory->company_id, $permission);

            return $this->engineeringImpact($actor, $factory, $query);
        }
        if ($kind === 'plan-calendar') {
            return ['items' => $this->calendarQuery($factory)->orderByDesc('d.work_date')->limit(365)->get()->map(fn ($r) => $this->calendarResource($r, $factory))->all()];
        }
        $order = D::row('mfg_orders', (string) ($query['order_id'] ?? ''), $factory->company_id, $factory->id);
        if ($kind === 'plan-history') {
            return ['items' => DB::table('mfg_plan_changes')->where('order_id', $order->id)->orderByDesc('id')->limit(100)->get()->map(function ($r): array {
                $data = D::resource($r);
                foreach (['before_snapshot', 'after_snapshot', 'warnings'] as $key) {
                    $data[$key] = D::json($data[$key]);
                }

                return $data;
            })->all()];
        }
        abort_unless($kind === 'plan-capacity', 404);

        return $this->capacity($factory, $order, $query);
    }

    private function calendar(object $factory, array $data, ?string $resource): array
    {
        $v = validator($data, ['work_date' => 'required|date_format:Y-m-d', 'scope_type' => 'required|in:factory,workshop,line', 'workshop_id' => 'nullable|uuid',
            'line_id' => 'nullable|uuid', 'is_working' => 'required|boolean', 'available_minutes' => 'required', 'notes' => 'nullable|string|max:2000'])->validate();
        $workshop = ! empty($v['workshop_id']) ? D::row('mfg_workshops', $v['workshop_id'], $factory->company_id, $factory->id) : null;
        $line = ! empty($v['line_id']) ? D::row('mfg_lines', $v['line_id'], $factory->company_id, $factory->id) : null;
        abort_if($v['scope_type'] === 'line' && ! $line, 422, 'Chọn chuyền cho lịch cấp chuyền.');
        abort_if($v['scope_type'] === 'workshop' && ! $workshop, 422, 'Chọn phân xưởng cho lịch cấp phân xưởng.');
        abort_if($line && $workshop && (int) $line->workshop_id !== (int) $workshop->id, 422, 'Chuyền không thuộc phân xưởng.');
        $scope = match ($v['scope_type']) {
            'line' => 'line:'.$line->id, 'workshop' => 'workshop:'.$workshop->id, default => 'factory'
        };
        $existing = DB::table('mfg_calendar_days')->where('factory_id', $factory->id)->where('scope_key', $scope)->where('work_date', $v['work_date'])->lockForUpdate()->first();
        if ($resource) {
            abort_unless($existing && $existing->public_id === $resource, 404);
        }
        $values = ['scope_key' => $scope, 'work_date' => $v['work_date'], 'is_working' => $v['is_working'], 'available_minutes' => (string) D::decimal($v['available_minutes'], true),
            'line_id' => $v['scope_type'] === 'line' ? $line->id : null, 'workshop_id' => $v['scope_type'] === 'workshop' ? $workshop->id : $line?->workshop_id,
            'metadata' => D::encode(['notes' => $v['notes'] ?? null]), 'status' => 'active'];
        $row = $existing ? D::update('mfg_calendar_days', $existing, $values, $data['expected_version'] ?? null)
            : D::insert('mfg_calendar_days', $values + ['company_id' => $factory->company_id, 'factory_id' => $factory->id]);

        return $this->calendarResource($this->calendarQuery($factory)->where('d.id', $row->id)->first(), $factory);
    }

    private function calendarQuery(object $factory): Builder
    {
        return DB::table('mfg_calendar_days as d')->where('d.factory_id', $factory->id)
            ->join('mfg_companies as c', 'c.id', '=', 'd.company_id')
            ->leftJoin('mfg_workshops as w', 'w.id', '=', 'd.workshop_id')->leftJoin('mfg_lines as l', 'l.id', '=', 'd.line_id')
            ->select('d.*', 'c.public_id as company_public_id', 'w.public_id as workshop_public_id', 'w.code as workshop_code', 'l.public_id as line_public_id', 'l.code as line_code');
    }

    private function calendarResource(object $row, object $factory): array
    {
        $data = D::resource($row);
        $data['company_id'] = $row->company_public_id;
        $data['factory_id'] = $factory->public_id;
        $data['workshop_id'] = $row->workshop_public_id;
        $data['line_id'] = $row->line_public_id;
        $data['scope_type'] = explode(':', $row->scope_key, 2)[0];
        $data['scope_key'] = match ($data['scope_type']) {
            'line' => 'line:'.$row->line_public_id, 'workshop' => 'workshop:'.$row->workshop_public_id, default => 'factory'
        };
        unset($data['company_public_id'], $data['workshop_public_id'], $data['line_public_id']);

        return $data;
    }

    private function schedule(Admin $actor, object $factory, string $operation, array $data, ?string $resource): array
    {
        $v = validator($data, ['planned_start' => 'required|date_format:Y-m-d', 'planned_end' => 'required|date_format:Y-m-d|after_or_equal:planned_start',
            'reason' => 'required|string|min:5|max:2000', 'freeze_until' => 'nullable|date_format:Y-m-d', 'override_frozen' => 'sometimes|boolean',
            'acknowledge_warnings' => 'sometimes|boolean', 'workshop_id' => 'nullable|uuid', 'line_id' => 'nullable|uuid'])->validate();
        $order = D::row('mfg_orders', (string) ($resource ?? $data['order_id'] ?? ''), $factory->company_id, $factory->id, true);
        abort_unless(in_array($order->status, ['draft', 'released', 'in_progress', 'partially_shipped'], true), 409, 'Lệnh không còn được lập lịch.');
        $metadata = D::json($order->metadata);
        $oldSchedule = $metadata['schedule'] ?? [];
        if ($operation === 'plan.commit') {
            abort_if(($oldSchedule['status'] ?? null) === 'committed', 409, 'Kế hoạch đã cam kết; dùng thao tác đổi lịch.');
        }
        if ($operation === 'plan.reschedule') {
            abort_unless(($oldSchedule['status'] ?? null) === 'committed', 409, 'Cam kết kế hoạch trước khi đổi lịch.');
            if (! empty($oldSchedule['freeze_until']) && Carbon::now($factory->timezone)->toDateString() <= $oldSchedule['freeze_until']) {
                abort_unless($v['override_frozen'] ?? false, 409, 'Kế hoạch đang trong cửa sổ đóng băng; cần override tường minh.');
                app(ManufacturingAccessService::class)->factory($actor, (int) $factory->id, 'manufacturing.settings', true);
            }
        }
        $allocations = DB::table('mfg_allocations')->where('order_id', $order->id)->where('factory_id', $factory->id)->lockForUpdate()->get();
        abort_unless($allocations->isNotEmpty(), 422, 'Lệnh chưa có phân bổ để cam kết.');
        $before = ['planned_start' => $order->planned_start_date, 'planned_end' => $order->due_date, 'schedule' => $oldSchedule,
            'allocations' => $allocations->map(fn ($a) => ['id' => $a->public_id, 'line_id' => $a->line_id, 'workshop_id' => $a->workshop_id, 'starts_at' => $a->starts_at, 'ends_at' => $a->ends_at])->all()];
        if (! empty($v['workshop_id']) || ! empty($v['line_id'])) {
            $workshop = ! empty($v['workshop_id']) ? D::row('mfg_workshops', $v['workshop_id'], $factory->company_id, $factory->id) : null;
            $line = ! empty($v['line_id']) ? D::row('mfg_lines', $v['line_id'], $factory->company_id, $factory->id) : null;
            abort_if($line && $workshop && (int) $line->workshop_id !== (int) $workshop->id, 422);
            foreach ($allocations as $allocation) {
                DB::table('mfg_allocations')->where('id', $allocation->id)->update(['line_id' => $line?->id, 'workshop_id' => $workshop?->id ?? $line?->workshop_id ?? $allocation->workshop_id]);
            }
        }
        $report = $this->capacity($factory, $order, ['planned_start' => $v['planned_start'], 'planned_end' => $v['planned_end']]);
        abort_if($report['warnings'] !== [] && ! ($v['acknowledge_warnings'] ?? false), 409, 'Còn cảnh báo năng lực/lịch; xem báo cáo rồi xác nhận cảnh báo trước khi cam kết.');
        $schedule = ['status' => 'committed', 'freeze_until' => $v['freeze_until'] ?? $oldSchedule['freeze_until'] ?? null, 'committed_by' => $actor->id,
            'committed_at' => now()->toIso8601String(), 'reason' => $v['reason'], 'warnings_acknowledged' => $report['warnings'] !== [], 'policy_version' => 1];
        $order = D::update('mfg_orders', $order, ['planned_start_date' => $v['planned_start'], 'due_date' => $v['planned_end'], 'metadata' => D::encode(array_replace($metadata, ['schedule' => $schedule]))], $data['expected_version'] ?? null);
        DB::table('mfg_allocations')->where('order_id', $order->id)->where('factory_id', $factory->id)->update(['starts_at' => $v['planned_start'].' 00:00:00', 'ends_at' => $v['planned_end'].' 23:59:59', 'status' => 'committed', 'updated_at' => now()]);
        $after = ['planned_start' => $order->planned_start_date, 'planned_end' => $order->due_date, 'schedule' => $schedule, 'allocations' => DB::table('mfg_allocations')->where('order_id', $order->id)->get()->map(fn ($a) => ['id' => $a->public_id, 'line_id' => $a->line_id, 'workshop_id' => $a->workshop_id])->all()];
        D::insert('mfg_plan_changes', ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'order_id' => $order->id, 'action' => $operation,
            'reason' => $v['reason'], 'recorded_by' => $actor->id, 'before_snapshot' => D::encode($before), 'after_snapshot' => D::encode($after), 'warnings' => D::encode($report['warnings']), 'status' => 'recorded']);

        return D::resource($order) + ['warnings' => $report['warnings']];
    }

    private function capacity(object $factory, object $order, array $query): array
    {
        $start = $query['planned_start'] ?? $order->planned_start_date;
        $end = $query['planned_end'] ?? $order->due_date;
        validator(['planned_start' => $start, 'planned_end' => $end], ['planned_start' => 'required|date_format:Y-m-d', 'planned_end' => 'required|date_format:Y-m-d|after_or_equal:planned_start'])->validate();
        $from = Carbon::parse($start, $factory->timezone);
        $to = Carbon::parse($end, $factory->timezone);
        abort_if($from->diffInDays($to) > 90, 422, 'Mỗi lần kiểm năng lực tối đa 91 ngày.');
        $dates = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dates[] = $day->toDateString();
        }
        $calendar = DB::table('mfg_calendar_days')->where('factory_id', $factory->id)->where('status', 'active')->get()->keyBy(fn ($r) => $r->scope_key.'|'.$r->work_date);
        $lines = DB::table('mfg_lines')->where('factory_id', $factory->id)->get()->keyBy('id');
        $allocations = DB::table('mfg_allocations as a')->join('mfg_orders as o', 'o.id', '=', 'a.order_id')->where('a.factory_id', $factory->id)->whereNotIn('o.status', ['cancelled', 'closed', 'shipped'])
            ->where(function ($q) use ($order, $start, $end): void {
                $q->where('a.order_id', $order->id)->orWhere(fn ($other) => $other->where('a.starts_at', '<=', $end.' 23:59:59')->where('a.ends_at', '>=', $start.' 00:00:00'));
            })
            ->get(['a.*', 'o.public_id as order_public_id', 'o.code as order_code', 'o.style_revision_id', 'o.released_snapshot']);
        $revisions = DB::table('mfg_style_revisions')->whereIn('id', $allocations->pluck('style_revision_id')->filter()->unique())->get()->keyBy('id');
        $load = [];
        $warnings = [];
        foreach ($allocations as $allocation) {
            $line = $lines[$allocation->line_id] ?? null;
            if (! $line) {
                $warnings['line:'.$allocation->order_id] = ['code' => 'line_not_assigned', 'order_id' => $allocation->order_public_id, 'message' => 'Phân bổ chưa có chuyền và năng lực được xác nhận.'];

                continue;
            }
            $isTarget = (int) $allocation->order_id === (int) $order->id;
            $allocationStart = $isTarget ? $start : substr((string) $allocation->starts_at, 0, 10);
            $allocationEnd = $isTarget ? $end : substr((string) $allocation->ends_at, 0, 10);
            $allDates = [];
            $allocationFrom = Carbon::parse($allocationStart, $factory->timezone);
            $allocationTo = Carbon::parse($allocationEnd, $factory->timezone);
            abort_if($allocationFrom->diffInDays($allocationTo) > 366, 422, 'Phân bổ quá dài; chia lại cửa sổ kế hoạch.');
            for ($day = $allocationFrom->copy(); $day->lte($allocationTo); $day->addDay()) {
                $date = $day->toDateString();
                $rule = $calendar['line:'.$line->id.'|'.$date] ?? $calendar['workshop:'.$line->workshop_id.'|'.$date] ?? $calendar['factory|'.$date] ?? null;
                if ($rule === null || $rule->is_working) {
                    $allDates[] = $date;
                }
            }
            $routing = D::json($allocation->released_snapshot)['routing'] ?? D::json($revisions[$allocation->style_revision_id]->routing ?? null);
            $minutes = BigDecimal::zero();
            foreach ($routing as $operation) {
                $minutes = $minutes->plus((string) ($operation['smv'] ?? '0'));
            }
            if ($minutes->isZero()) {
                $warnings['smv:'.$allocation->order_id] = ['code' => 'standard_minutes_missing', 'order_id' => $allocation->order_public_id, 'message' => 'Routing chưa có phút tiêu chuẩn.'];
            }
            if ($allDates === []) {
                $warnings['closed:'.$allocation->order_id] = ['code' => 'no_working_day', 'order_id' => $allocation->order_public_id, 'message' => 'Cửa sổ được chọn không có ngày làm việc.'];

                continue;
            }
            $daily = $minutes->multipliedBy((string) $allocation->quantity)->dividedBy(count($allDates), 6, RoundingMode::HalfUp);
            foreach (array_intersect($allDates, $dates) as $date) {
                $key = $line->id.'|'.$date;
                $load[$key] = ($load[$key] ?? BigDecimal::zero())->plus($daily);
            }
        }
        $rows = [];
        foreach ($load as $key => $minutes) {
            [$lineId, $date] = explode('|', $key);
            $line = $lines[(int) $lineId];
            $rule = $calendar['line:'.$lineId.'|'.$date] ?? $calendar['workshop:'.$line->workshop_id.'|'.$date] ?? $calendar['factory|'.$date] ?? null;
            $available = BigDecimal::of((string) ($rule ? ($rule->is_working ? $rule->available_minutes : '0') : $line->capacity_minutes));
            if (! $rule) {
                $warnings['calendar:'.$key] = ['code' => 'calendar_not_confirmed', 'line_id' => $line->public_id, 'work_date' => $date, 'message' => 'Đang dùng năng lực danh nghĩa; chưa có lịch xác nhận ngày này.'];
            }
            if ($minutes->isGreaterThan($available)) {
                $warnings['overload:'.$key] = ['code' => 'capacity_exceeded', 'line_id' => $line->public_id, 'work_date' => $date, 'message' => 'Tải kế hoạch vượt năng lực ngày đã cấu hình.'];
            }
            $rows[] = ['line_id' => $line->public_id, 'line_code' => $line->code, 'work_date' => $date, 'planned_minutes' => (string) $minutes->toScale(6), 'available_minutes' => (string) $available->toScale(6), 'calendar_confirmed' => $rule !== null, 'overloaded' => $minutes->isGreaterThan($available)];
        }

        return ['order_id' => $order->public_id, 'items' => $rows, 'days' => $rows, 'warnings' => array_values($warnings), 'total' => count($rows), 'basis' => 'Manual calendar-day load from allocation quantity × frozen routing SMV, evenly spread across working dates; includes draft allocations; not finite scheduling'];
    }

    private function engineeringImpact(Admin $actor, object $factory, array $query): array
    {
        $style = D::row('mfg_styles', (string) ($query['style_id'] ?? ''), $factory->company_id);
        $revision = ! empty($query['revision_id']) ? D::row('mfg_style_revisions', $query['revision_id'], $factory->company_id) : DB::table('mfg_style_revisions')->where('style_id', $style->id)->latest('revision')->first();
        abort_unless($revision && (int) $revision->style_id === (int) $style->id, 404);
        $factoryIds = DB::table('mfg_factories')->where('company_id', $factory->company_id)->whereIn('id', app(ManufacturingAccessService::class)->allowedFactoryIds($actor))->pluck('id');
        $orders = DB::table('mfg_orders')->where('company_id', $factory->company_id)->whereIn('factory_id', $factoryIds)->whereIn('style_revision_id', DB::table('mfg_style_revisions')->where('style_id', $style->id)->select('id'))
            ->whereIn('status', ['draft', 'released', 'in_progress', 'partially_shipped'])->orderBy('id')->limit(500)->get();
        $warehouses = DB::table('mfg_factories')->whereIn('id', $factoryIds)->pluck('warehouse_id')->filter();
        $holds = DB::table('inv_reservations')->whereIn('warehouse_id', $warehouses)->where('purpose', 'production')->whereIn('order_ref', $orders->pluck('public_id'))->where('status', 'active')->get(['public_id', 'order_ref', 'quantity', 'version']);
        $bundles = DB::table('mfg_bundles')->whereIn('order_id', $orders->pluck('id'))->whereIn('factory_id', $factoryIds)->whereNotIn('status', ['merged', 'cancelled'])->get();
        $materials = array_column(D::json($revision->bom), 'sku_id');
        $priorRevisions = DB::table('mfg_style_revisions')->whereIn('id', $orders->pluck('style_revision_id'))->get();
        foreach ($priorRevisions as $prior) {
            $materials = array_merge($materials, array_column(D::json($prior->bom), 'sku_id'));
        }
        $materials = array_unique($materials);
        $purchasingVisible = app(ModuleCapabilityChecker::class)->enabled('purchasing') && $actor->canAccess('purchasing.view', 'global');
        $purchase = $purchasingVisible ? DB::table('purchase_orders as p')->join('purchase_order_lines as l', 'l.order_id', '=', 'p.id')->whereIn('p.warehouse_id', $warehouses)
            ->whereIn('l.sku_id', $materials)->whereIn('p.status', ['draft', 'approved', 'partially_received'])->select('p.public_id', 'p.code', 'p.status', 'p.expected_at')->distinct()->limit(200)->get()->map(fn ($r) => (array) $r)->all() : [];

        return ['style_id' => $style->public_id, 'target_revision_id' => $revision->public_id, 'target_revision' => $revision->revision,
            'orders' => $orders->map(fn ($o) => ['id' => $o->public_id, 'code' => $o->code, 'status' => $o->status, 'uses_target_revision' => (int) $o->style_revision_id === (int) $revision->id, 'released_snapshot_preserved' => true])->all(),
            'holds' => $holds->map(fn ($r) => (array) $r)->all(), 'wip' => $bundles->map(fn ($b): array => ['id' => $b->public_id, 'code' => $b->code, 'status' => $b->status, 'quantity' => (string) BigDecimal::of((string) $b->wip_quantity)->toScale(6)])->all(),
            'candidate_purchase_orders' => $purchase, 'purchasing_visible' => $purchasingVisible, 'purchase_match_basis' => 'Matching material SKU and authorized company warehouses only; not a confirmed allocation/source link',
            'mutation_performed' => false, 'items' => [], 'total' => $orders->count()];
    }
}
