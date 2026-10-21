<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Internal append-only valuation events with mutable remaining/realized projections. */
class InventoryValuationService
{
    public function consume(object $layer, BigDecimal $quantity, string $operation): BigDecimal
    {
        $cost = BigDecimal::zero();
        if (! Schema::hasTable('inv_valuation_adjustments')) {
            return $cost;
        }
        foreach (DB::table('inv_valuation_adjustments')->where('cost_layer_id', $layer->id)->where('remaining_value', '!=', 0)->orderBy('id')->lockForUpdate()->get() as $row) {
            $remaining = $this->decimal($row->remaining_value);
            $amount = $quantity->isEqualTo((string) $layer->quantity_remaining) ? $remaining : $remaining->multipliedBy($quantity)->dividedBy((string) $layer->quantity_remaining, 6, RoundingMode::HalfUp);
            DB::table('inv_valuation_adjustments')->where('id', $row->id)->update(['remaining_value' => $this->format($remaining->minus($amount)), 'realized_value' => $this->format($this->decimal($row->realized_value)->plus($amount)), 'updated_at' => now()]);
            DB::table('inv_valuation_consumptions')->insert(['adjustment_id' => $row->id, 'cost_layer_id' => $layer->id, 'operation_id' => $operation, 'quantity' => $this->format($quantity), 'amount' => $this->format($amount), 'created_at' => now(), 'updated_at' => now()]);
            $cost = $cost->plus($amount);
        }

        return $cost;
    }

    public function remaining(int $layerId): BigDecimal
    {
        if (! Schema::hasTable('inv_valuation_adjustments')) {
            return BigDecimal::zero();
        }
        $value = BigDecimal::zero();
        foreach (DB::table('inv_valuation_adjustments')->where('cost_layer_id', $layerId)->pluck('remaining_value') as $amount) {
            $value = $value->plus((string) $amount);
        }

        return $value;
    }

    public function receiptLayer(int $documentId): object
    {
        $layer = DB::table('inv_cost_layers as c')->join('inv_stock_document_lines as l', 'l.id', '=', 'c.document_line_id')->where('l.document_id', $documentId)->select('c.*')->lockForUpdate()->first();
        abort_unless($layer, 409, 'Receipt thiếu layer giá vốn.');

        return $layer;
    }

    public function createEvent(int $postingId, string $type, string $source, BigDecimal $amount, string $reason, int $actor, string $fingerprint): object
    {
        $id = DB::table('inv_valuation_events')->insertGetId(['public_id' => (string) Str::uuid(), 'posting_id' => $postingId, 'source_type' => $type, 'source_id' => $source, 'fingerprint' => $fingerprint, 'amount' => $this->format($amount), 'reason' => $reason, 'actor_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('inv_valuation_events')->find($id);
    }

    /** Allocate new value through physical split/return/transfer lineage, never rewrite historical unit prices. */
    public function allocate(int $event, int $layerId, BigDecimal $amount, array $visited = []): void
    {
        abort_if(in_array($layerId, $visited, true), 409, 'Cost genealogy có chu trình.');
        $visited[] = $layerId;
        $layer = DB::table('inv_cost_layers')->where('id', $layerId)->lockForUpdate()->first();
        abort_unless($layer && $this->decimal($layer->quantity_received)->isPositive(), 409);
        $fullValue = $this->decimal($layer->quantity_received)->multipliedBy((string) $layer->unit_cost);
        foreach (DB::table('inv_valuation_adjustments')->where('cost_layer_id', $layerId)->where('row_type', 'entitlement')->pluck('amount_total') as $prior) {
            $fullValue = $fullValue->plus((string) $prior);
        }
        // A sold output still has an immutable cost identity. A reduction must
        // not make that output negative just because its remaining stock is zero.
        abort_if($fullValue->plus($amount)->isNegative(), 422, 'Điều chỉnh làm âm giá trị một output hoặc cost layer.');
        $remaining = $amount->multipliedBy((string) $layer->quantity_remaining)->dividedBy((string) $layer->quantity_received, 6, RoundingMode::HalfUp);
        abort_if($this->decimal($layer->quantity_remaining)->multipliedBy((string) $layer->unit_cost)->plus((string) $this->remaining($layerId))->plus($remaining)->isNegative(), 422, 'Điều chỉnh làm âm giá trị tồn.');
        $this->row($event, $layer, $amount, $remaining, $amount->minus($remaining), 'entitlement');
        $links = DB::table('inv_cost_layer_links')->where('origin_layer_id', $layerId)->orderBy('id')->lockForUpdate()->get();
        $consumed = $this->decimal($layer->quantity_received)->minus((string) $layer->quantity_remaining);
        $linked = BigDecimal::zero();
        foreach ($links as $link) {
            $linked = $linked->plus((string) $link->quantity);
        }
        abort_if($linked->isGreaterThan($consumed), 409, 'Physical cost lineage vượt lượng đã xuất.');
        $assigned = BigDecimal::zero();
        foreach ($links as $i => $link) {
            $share = $i === $links->count() - 1 && $linked->isEqualTo($consumed) ? $amount->minus($remaining)->minus($assigned) : $amount->multipliedBy((string) $link->quantity)->dividedBy((string) $layer->quantity_received, 6, RoundingMode::HalfUp);
            $assigned = $assigned->plus($share);
            $this->row($event, $layer, $share->negated(), BigDecimal::zero(), $share->negated(), 'outgoing');
            $this->allocate($event, (int) $link->target_layer_id, $share, $visited);
        }
    }

    /** Preserve the value of previously issued goods when physical material is returned/split/transferred. */
    public function link(int $origin, int $target, BigDecimal $quantity, string $type, string $source): BigDecimal
    {
        abort_unless($target > $origin && $quantity->isPositive(), 409, 'Cost layer link không hợp lệ.');
        $layer = DB::table('inv_cost_layers')->where('id', $origin)->lockForUpdate()->first();
        $destination = DB::table('inv_cost_layers')->where('id', $target)->lockForUpdate()->first();
        abort_unless($layer && $destination && (int) $layer->item_id === (int) $destination->item_id, 422);
        $existing = BigDecimal::zero();
        foreach (DB::table('inv_cost_layer_links')->where('origin_layer_id', $origin)->pluck('quantity') as $q) {
            $existing = $existing->plus((string) $q);
        }
        $consumed = $this->decimal($layer->quantity_received)->minus((string) $layer->quantity_remaining);
        abort_if($existing->plus($quantity)->isGreaterThan($consumed), 409, 'Cost lineage tạo lại lượng đã chuyển.');
        DB::table('inv_cost_layer_links')->insert(['origin_layer_id' => $origin, 'target_layer_id' => $target, 'quantity' => $this->format($quantity), 'source_type' => $type, 'source_id' => $source, 'created_at' => now(), 'updated_at' => now()]);
        $value = BigDecimal::zero();
        foreach (DB::table('inv_valuation_adjustments')->where('cost_layer_id', $origin)->where('row_type', 'entitlement')->orderBy('id')->lockForUpdate()->get() as $adjustment) {
            $amount = $this->decimal($adjustment->amount_total)->multipliedBy($quantity)->dividedBy((string) $adjustment->quantity_basis, 6, RoundingMode::HalfUp);
            $this->row($adjustment->event_id, $layer, $amount->negated(), BigDecimal::zero(), $amount->negated(), 'outgoing');
            $this->allocate($adjustment->event_id, $target, $amount);
            $value = $value->plus($amount);
        }

        return $value;
    }

    public function event(int $id): array
    {
        $event = DB::table('inv_valuation_events')->find($id);
        abort_unless($event, 404);
        $remaining = BigDecimal::zero();
        $realized = BigDecimal::zero();
        $rows = DB::table('inv_valuation_adjustments')->where('event_id', $id)->orderBy('id')->get();
        foreach ($rows as $row) {
            $remaining = $remaining->plus((string) $row->remaining_value);
            $realized = $realized->plus((string) $row->realized_value);
        }

        $included = BigDecimal::zero();
        foreach (DB::table('inv_valuation_consumptions')->whereIn('adjustment_id', $rows->pluck('id'))->pluck('amount') as $amount) {
            $included = $included->plus((string) $amount);
        }

        return (array) $event + ['remaining_value' => $this->format($remaining), 'realized_value' => $this->format($realized), 'included_in_issues_value' => $this->format($included), 'realized_variance_value' => $this->format($realized->minus($included)), 'layers' => $rows->map(fn ($r) => (array) $r)->all()];
    }

    public function reverseComponents(array $components, BigDecimal $previousReturned, BigDecimal $quantity, BigDecimal $issueQuantity, string $operation): void
    {
        foreach ($components as $component) {
            $value = $this->decimal($component['amount']);
            $before = $value->multipliedBy($previousReturned)->dividedBy($issueQuantity, 6, RoundingMode::HalfUp);
            $after = $value->multipliedBy($previousReturned->plus($quantity))->dividedBy($issueQuantity, 6, RoundingMode::HalfUp);
            DB::table('inv_valuation_consumptions')->insert(['adjustment_id' => $component['adjustment_id'], 'cost_layer_id' => $component['cost_layer_id'], 'operation_id' => $operation, 'quantity' => $this->format($quantity->negated()), 'amount' => $this->format($after->minus($before)->negated()), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function reversePhysicalConsumptions(mixed $rows, string $operation): void
    {
        foreach ($rows as $row) {
            DB::table('inv_valuation_consumptions')->insert(['adjustment_id' => $row->adjustment_id, 'cost_layer_id' => $row->cost_layer_id, 'operation_id' => $operation, 'quantity' => $this->format($this->decimal($row->quantity)->negated()), 'amount' => $this->format($this->decimal($row->amount)->negated()), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function warehouse(int $warehouse): array
    {
        $base = BigDecimal::zero();
        $adjustment = BigDecimal::zero();
        foreach (DB::table('inv_cost_layers')->where('warehouse_id', $warehouse)->get() as $layer) {
            $base = $base->plus((string) $this->decimal($layer->quantity_remaining)->multipliedBy((string) $layer->unit_cost));
            $adjustment = $adjustment->plus((string) $this->remaining($layer->id));
        }

        return ['warehouse_id' => $warehouse, 'base_value' => $this->format($base), 'adjustment_value' => $this->format($adjustment), 'valuation' => $this->format($base->plus($adjustment))];
    }

    private function row(int $event, object $layer, BigDecimal $amount, BigDecimal $remaining, BigDecimal $realized, string $type): void
    {
        DB::table('inv_valuation_adjustments')->insert(['public_id' => (string) Str::uuid(), 'event_id' => $event, 'cost_layer_id' => $layer->id, 'quantity_basis' => $layer->quantity_received, 'row_type' => $type, 'amount_total' => $this->format($amount), 'remaining_value' => $this->format($remaining), 'realized_value' => $this->format($realized), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) $value)->toScale(6, RoundingMode::HalfUp);
    }

    private function format(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
