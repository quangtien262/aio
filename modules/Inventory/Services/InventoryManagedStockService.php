<?php

namespace Modules\Inventory\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Inventory\Contracts\ManagedStockPolicy;

/** Shared physical stock restrictions. Native consumers own professional decisions. */
final class InventoryManagedStockService
{
    private const CONTEXT = 'inventory.managed_stock_context';

    public function withinOwner(string $module, string $scope, int $actor, string $action, callable $callback, array $metadata = []): mixed
    {
        $container = app();
        $previous = $container->bound(self::CONTEXT) ? $container->make(self::CONTEXT) : null;
        $container->instance(self::CONTEXT, ['module' => $module, 'scope' => $scope, 'actor' => $actor, 'action' => $action, 'metadata' => $metadata]);
        try {
            return $callback();
        } finally {
            $previous === null ? $container->forgetInstance(self::CONTEXT) : $container->instance(self::CONTEXT, $previous);
        }
    }

    public function context(): ?array
    {
        return app()->bound(self::CONTEXT) ? app(self::CONTEXT) : null;
    }

    public function authorize(object $warehouse, string $operation, int $actor, array $intent = []): void
    {
        if (empty($warehouse->managed_owner_module)) {
            return;
        }
        $this->ready();
        $owner = DB::table('module_installations')->where('key', $warehouse->managed_owner_module)->sharedLock()->first();
        abort_unless($owner && $owner->status === 'enabled' && app(ModuleCapabilityChecker::class)->enabled($warehouse->managed_owner_module), 503, 'App sở hữu kho chưa sẵn sàng; giữ nguyên chặn kho.');
        $context = $this->context();
        abort_unless($context && $context['module'] === $warehouse->managed_owner_module && ($context['scope'] === $warehouse->managed_owner_scope || in_array($warehouse->managed_owner_scope, $context['metadata']['authorized_scopes'] ?? [], true)) && $context['actor'] === $actor, 403, 'Kho được quản lý phải ghi qua nghiệp vụ của app sở hữu.');
        $class = config('inventory.managed_policies.'.$warehouse->managed_owner_module);
        abort_unless(is_string($class) && class_exists($class), 503, 'Thiếu policy của app sở hữu kho.');
        $policy = app($class);
        abort_unless($policy instanceof ManagedStockPolicy, 503, 'Policy kho không đúng contract.');
        $context['stock_intent'] = $intent;
        $context['origin_scope'] = $context['scope'];
        $context['scope'] = $warehouse->managed_owner_scope;
        $policy->authorize($actor, $warehouse, $operation, $context);
    }

    /** All generic commands, including cached replays, pass this boundary. */
    public function authorizeCommand(string $operation, array $input, int $actor): void
    {
        $ids = [];
        foreach (['warehouse_id', 'warehouseId', 'source_warehouse_id', 'destination_warehouse_id'] as $field) {
            if (isset($input[$field])) {
                $ids[] = (int) $input[$field];
            }
        }
        foreach (['reservationId' => 'inv_reservations', 'transferId' => 'inv_transfers', 'stocktakeId' => 'inv_stocktakes', 'escrowId' => 'inv_escrows'] as $field => $table) {
            if (empty($input[$field])) {
                continue;
            }
            $row = DB::table($table)->where('public_id', $input[$field])->first();
            if ($row) {
                $columns = $operation === 'transfer.receive' ? ['destination_warehouse_id'] : ['warehouse_id', 'source_warehouse_id', 'destination_warehouse_id'];
                foreach ($columns as $column) {
                    if (isset($row->$column)) {
                        $ids[] = (int) $row->$column;
                    }
                }
            }
        }
        sort($ids);
        foreach (array_unique($ids) as $id) {
            $warehouse = DB::table('inv_warehouses')->where('id', $id)->lockForUpdate()->first();
            if ($warehouse) {
                $this->authorize($warehouse, $operation, $actor, $input);
            }
        }
    }

    public function tagWarehouse(int $warehouseId, string $module, string $scope, string $key, int $actor): array
    {
        return $this->command('warehouse.tag', $warehouseId, compact('module', 'scope'), $key, $actor, function ($warehouse) use ($module, $scope, $actor): array {
            abort_unless(preg_match('/^[a-z][a-z0-9-]{1,79}$/D', $module) && $scope !== '' && strlen($scope) <= 100, 422);
            abort_if($warehouse->production_managed ?? false, 409, 'Không chuyển kho production sang owner khác.');
            abort_unless(empty($warehouse->managed_owner_module) || ($warehouse->managed_owner_module === $module && $warehouse->managed_owner_scope === $scope), 409, 'Kho đã thuộc phạm vi quản lý khác.');
            $candidate = clone $warehouse;
            $candidate->managed_owner_module = $module;
            $candidate->managed_owner_scope = $scope;
            $this->authorize($candidate, 'warehouse.tag', $actor);
            if (empty($warehouse->managed_owner_module)) {
                abort_if(DB::table('inv_stock_movements')->where('warehouse_id', $warehouse->id)->exists() || DB::table('inv_reservations')->where('warehouse_id', $warehouse->id)->exists(), 409, 'Chỉ gắn owner tự động cho kho mới; kho đang vận hành cần cutover riêng.');
            }
            DB::table('inv_warehouses')->where('id', $warehouse->id)->update(['managed_owner_module' => $module, 'managed_owner_scope' => $scope, 'updated_at' => now()]);

            return ['warehouse_id' => $warehouse->id, 'owner_module' => $module, 'owner_scope' => $scope];
        });
    }

    public function placeHold(array $input, string $key, int $actor): array
    {
        $v = validator($input, ['warehouse_id' => 'required|integer', 'item_id' => 'nullable|integer', 'batch_id' => 'nullable|integer', 'reason_code' => 'required|string|max:80', 'reason' => 'required|string|max:1000', 'source_ref' => 'nullable|string|max:191'])->validate();

        return $this->command('hold.place', (int) $v['warehouse_id'], $v, $key, $actor, function ($warehouse) use ($v, $actor): array {
            $this->references($warehouse, $v['item_id'] ?? null, $v['batch_id'] ?? null);
            $id = DB::table('inv_stock_holds')->insertGetId($v + ['public_id' => (string) Str::uuid(), 'actor_id' => $actor, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

            return (array) DB::table('inv_stock_holds')->find($id);
        });
    }

    public function releaseHold(string $holdId, int $expectedVersion, string $reason, string $key, int $actor): array
    {
        $hold = DB::table('inv_stock_holds')->where('public_id', $holdId)->first();
        abort_unless($hold, 404);

        return $this->command('hold.release', $hold->warehouse_id, compact('holdId', 'expectedVersion', 'reason'), $key, $actor, function () use ($holdId, $expectedVersion, $reason, $actor): array {
            validator(['reason' => $reason], ['reason' => 'required|string|max:1000'])->validate();
            $hold = DB::table('inv_stock_holds')->where('public_id', $holdId)->lockForUpdate()->first();
            abort_unless($hold->status === 'active' && (int) $hold->version === $expectedVersion, 409, 'Hold đã thay đổi.');
            DB::table('inv_stock_holds')->where('id', $hold->id)->update(['status' => 'released', 'version' => $hold->version + 1, 'released_by' => $actor, 'release_reason' => $reason, 'released_at' => now(), 'updated_at' => now()]);

            return (array) DB::table('inv_stock_holds')->find($hold->id);
        });
    }

    public function applyEligibility(Builder $query, int $warehouse, int $item): Builder
    {
        if (! Schema::hasTable('inv_stock_holds')) {
            return $query;
        }

        return $query->whereNotExists(function (Builder $hold) use ($warehouse, $item): void {
            $hold->selectRaw('1')->from('inv_stock_holds')->where('warehouse_id', $warehouse)->where('status', 'active')
                ->where(fn (Builder $q) => $q->whereNull('item_id')->orWhere('item_id', $item))
                ->where(fn (Builder $q) => $q->whereNull('batch_id')->orWhereColumn('batch_id', 'inv_stock_balances.batch_id'));
        });
    }

    public function assertEligible(int $warehouse, int $item, ?int $batch, int $actor, int $minimumShelfLifeDays = 0): void
    {
        $row = DB::table('inv_warehouses')->where('id', $warehouse)->first();
        abort_unless($row, 404);
        $this->authorize($row, 'eligibility.check', $actor);
        $this->references($row, $item, $batch);
        if (Schema::hasTable('inv_stock_holds')) {
            abort_if(DB::table('inv_stock_holds')->where('warehouse_id', $warehouse)->where('status', 'active')->where(fn ($q) => $q->whereNull('item_id')->orWhere('item_id', $item))->where(fn ($q) => $q->whereNull('batch_id')->orWhere('batch_id', $batch))->exists(), 409, 'Hàng/lô đang bị giữ; không được xuất hoặc bàn giao.');
        }
        if ($batch !== null) {
            $lot = DB::table('inv_batches')->where('id', $batch)->where('item_id', $item)->first();
            $date = now($row->timezone ?: 'UTC')->addDays(max(0, $minimumShelfLifeDays))->toDateString();
            $requiresExpiry = DB::table('inv_items as i')->join('merch_skus as s', 's.id', '=', 'i.merchandise_sku_id')->where('i.id', $item)->value('s.requires_expiry');
            abort_if($requiresExpiry && empty($lot?->expires_at), 409, 'Lô hàng bắt buộc hạn sử dụng chưa đủ thông tin.');
            abort_unless($lot && $lot->is_active && (! $lot->expires_at || substr($lot->expires_at, 0, 10) >= $date), 409, 'Lô hết hạn/bị khóa hoặc thiếu hạn còn lại theo policy.');
        }
    }

    public function lots(int $warehouse, int $item, int $actor): array
    {
        $row = DB::table('inv_warehouses')->where('id', $warehouse)->first();
        abort_unless($row, 404);
        $this->authorize($row, 'lots.read', $actor);
        $this->references($row, $item);

        return DB::table('inv_stock_balances as b')->leftJoin('inv_batches as l', 'l.id', '=', 'b.batch_id')->leftJoin('inv_locations as p', 'p.id', '=', 'b.location_id')
            ->where('b.warehouse_id', $warehouse)->where('b.item_id', $item)->orderByRaw('l.expires_at IS NULL')->orderBy('l.expires_at')->limit(500)
            ->get(['b.id as balance_id', 'b.batch_id', 'l.batch_code', 'l.expires_at', 'l.is_active as batch_active', 'b.location_id', 'p.type as disposition', 'b.quantity_on_hand', 'b.quantity_reserved'])->map(fn ($r) => array_replace((array) $r, ['quantity_on_hand' => (string) BigDecimal::of((string) $r->quantity_on_hand)->toScale(6), 'quantity_reserved' => (string) BigDecimal::of((string) $r->quantity_reserved)->toScale(6)]))->all();
    }

    public function trace(int $warehouse, int $item, ?int $batch, int $actor, int $limit = 100): array
    {
        $row = DB::table('inv_warehouses')->where('id', $warehouse)->first();
        abort_unless($row, 404);
        $this->authorize($row, 'trace.read', $actor);
        $this->references($row, $item, $batch);

        return DB::table('inv_stock_movements as m')->leftJoin('inv_stock_source_links as s', 's.movement_id', '=', 'm.id')->where('m.warehouse_id', $warehouse)->where('m.item_id', $item)->when($batch !== null, fn ($q) => $q->where('m.batch_id', $batch))->orderByDesc('m.id')->limit(min(500, max(1, $limit)))
            ->get(['m.id', 'm.document_id', 'm.document_line_id', 'm.batch_id', 'm.location_id', 'm.quantity_delta', 'm.type', 'm.reference', 'm.created_at', 's.owner_module', 's.source_type', 's.source_id', 's.source_line_id'])->map(fn ($r) => array_replace((array) $r, ['quantity_delta' => (string) BigDecimal::of((string) $r->quantity_delta)->toScale(6)]))->all();
    }

    public function recordMovement(int $movement, int $warehouse, int $item, ?int $batch): void
    {
        $context = $this->context();
        if (! $context || ! Schema::hasTable('inv_stock_source_links')) {
            return;
        }
        $metadata = $context['metadata'];
        DB::table('inv_stock_source_links')->insert(['owner_module' => $context['module'], 'owner_scope' => $context['scope'], 'source_type' => substr((string) ($metadata['source_type'] ?? $context['action']), 0, 80), 'source_id' => substr((string) ($metadata['source_id'] ?? $metadata['dispensing_id'] ?? $movement), 0, 191), 'source_line_id' => isset($metadata['source_line_id']) ? (string) $metadata['source_line_id'] : null, 'movement_id' => $movement, 'warehouse_id' => $warehouse, 'item_id' => $item, 'batch_id' => $batch, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function changeDisposition(array $input, string $key, int $actor): array
    {
        return app(InventoryV2Service::class)->changeDisposition($input, $key, $actor);
    }

    public function withdrawRestricted(array $input, string $key, int $actor): array
    {
        return app(InventoryV2Service::class)->withdrawRestricted($input, $key, $actor);
    }

    private function command(string $operation, int $warehouse, array $input, string $key, int $actor, callable $effect): array
    {
        abort_unless($key !== '' && strlen($key) <= 191, 422);

        return DB::transaction(function () use ($operation, $warehouse, $input, $key, $actor, $effect): array {
            $this->ready();
            app(InventoryRuntimeState::class)->guard();
            $row = DB::table('inv_warehouses')->where('id', $warehouse)->where('v2_managed', true)->where('is_active', true)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->authorize($row, $operation, $actor, $input);
            // A tag command's candidate policy must also run when the original response is replayed.
            if ($operation === 'warehouse.tag') {
                $candidate = clone $row;
                $candidate->managed_owner_module = $input['module'];
                $candidate->managed_owner_scope = $input['scope'];
                $this->authorize($candidate, $operation, $actor);
            } else {
                abort_unless(! empty($row->managed_owner_module), 422, 'Contract managed stock yêu cầu kho có owner.');
            }
            $fingerprint = hash('sha256', json_encode($this->canonical([$operation, $warehouse, $input, $actor, $this->context()]), JSON_THROW_ON_ERROR));
            DB::table('inv_managed_commands')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'command_key' => $key, 'operation' => $operation, 'actor_id' => $actor, 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $command = DB::table('inv_managed_commands')->where('command_key', $key)->lockForUpdate()->first();
            abort_unless(hash_equals($command->fingerprint, $fingerprint), 409, 'Key đã dùng cho nội dung khác.');
            if ($command->response !== null) {
                return json_decode($command->response, true, flags: JSON_THROW_ON_ERROR) + ['replayed' => true];
            }
            $result = $effect($row);
            DB::table('inv_managed_commands')->where('id', $command->id)->update(['response' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $result;
        }, 3);
    }

    private function references(object $warehouse, ?int $item, ?int $batch = null): void
    {
        abort_if($batch !== null && $item === null, 422, 'Hold theo lô cần định danh hàng.');
        if ($item !== null) {
            abort_unless(DB::table('inv_items as i')->join('merch_skus as s', 's.id', '=', 'i.merchandise_sku_id')->where('i.id', $item)->where('s.catalog_id', $warehouse->catalog_id)->exists(), 403, 'Hàng ngoài danh mục kho.');
        }
        if ($batch !== null) {
            abort_unless(DB::table('inv_batches')->where('id', $batch)->where('item_id', $item)->exists(), 404, 'Lô không thuộc hàng.');
        }
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonical($item), $value);
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('inv_stock_holds') && app(ModuleCapabilityChecker::class)->has('inventory', 'inventory.managed_stock.v1'), 503, 'Cần cập nhật và bật ứng dụng “Quản lý kho” để sử dụng kho do ứng dụng khác quản lý.');
    }
}
