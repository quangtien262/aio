<?php

namespace Modules\FnbPos\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\AcctItem;
use App\Models\Admin;
use App\Models\InvItem;
use App\Support\AccountingTax\AccountingDocumentService;
use App\Support\InventoryStockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Local database adapters. No provider call, tax issuance or automatic approval occurs here. */
final class FnbIntegrationService
{
    private const CAPABILITIES = ['inventory' => 'inventory.documents.write.v1', 'accounting-tax' => 'accounting.documents.manage.v1'];

    public function __construct(private readonly FnbCommandRunner $commands, private readonly ModuleCapabilityChecker $capabilities, private readonly FnbOutletAccessService $access, private readonly FnbOutboxService $outbox) {}

    public function overview(FnbContext $ctx, Admin $actor): array
    {
        $this->authorizeActor($ctx, $actor, 'fnb.integration.view');
        $mappings = $this->scope('fnb_integration_mappings', $ctx)->get()->map(function ($row) {
            $row->config = json_decode($row->config, true);

            return $row;
        });
        $ready = collect(['inventory' => 'Kho nguyên liệu', 'accounting-tax' => 'Kế toán'])->map(function ($label, $key) use ($ctx, $actor, $mappings): array {
            $mapping = $mappings->firstWhere('destination', $key);
            $enabled = $this->capabilities->has($key, self::CAPABILITIES[$key]);
            $configured = (bool) ($mapping?->enabled && ! empty($mapping?->config));
            $valid = false;
            try {
                if ($configured) {
                    $this->validateDestination($ctx, $actor, $key, $mapping->config);
                    $valid = true;
                }
            } catch (Throwable) {
            }

            return ['key' => $key, 'label' => $label, 'enabled' => $enabled, 'configured' => $configured,
                'ready' => false, 'status' => $enabled && $valid ? 'configured' : 'pending',
                'automatic' => (bool) ($mapping?->automatic ?? false),
                'reason' => 'Adapter cục bộ cần kiểm tra từng snapshot, mapping và quyền khi giao nhận. Kho v1 chỉ nhận 3 chữ số thập phân; kế toán hiện chỉ hỗ trợ VND và tạo nháp chờ phân loại thuế.'];
        })->values();

        return ['readiness' => $ready, 'destinations' => $ready, 'mappings' => $mappings,
            'item_mappings' => $this->scope('fnb_integration_item_mappings', $ctx)->get(),
            'backlog' => $this->scope('fnb_outbox_deliveries', $ctx)->orderByDesc('id')->limit(100)->get(['id', 'destination', 'status', 'attempts', 'max_attempts', 'available_at', 'last_error', 'updated_at']),
            'totals' => $this->scope('fnb_outbox_deliveries', $ctx)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all(),
            'source_options' => [
                'ingredients' => DB::table('fnb_ingredients')->where('website_key', $ctx->websiteKey)->get(['id', 'code', 'name', 'base_unit']),
                'variants' => DB::table('fnb_item_variants as v')->join('fnb_menu_items as i', 'i.id', '=', 'v.item_id')->where('v.website_key', $ctx->websiteKey)->get(['v.id', 'v.code', 'v.name', 'i.name as item_name']),
            ],
            'destination_options' => [
                'warehouses' => $this->capabilities->enabled('inventory') && $actor->hasPermission('inventory.view') ? DB::table('inv_warehouses')->where('is_active', true)->get(['id', 'name']) : [],
                'inventory_items' => $this->capabilities->enabled('inventory') && $actor->hasPermission('inventory.view') ? DB::table('inv_items')->where('is_active', true)->limit(2000)->get(['id', 'name', 'unit']) : [],
                'organizations' => $this->capabilities->enabled('accounting-tax') ? DB::table('acct_organizations')->where('status', 'active')->get(['id', 'name'])->filter(fn ($row) => $actor->canAccess('accounting.document.create', 'organization', (string) $row->id))->values() : [],
                'accounting_items' => $this->capabilities->enabled('accounting-tax') ? DB::table('acct_items')->where('status', 'active')->limit(2000)->get(['id', 'name', 'unit', 'organization_id'])->filter(fn ($row) => $actor->canAccess('accounting.document.create', 'organization', (string) $row->organization_id))->values() : [],
            ]];
    }

    public function mapping(FnbContext $ctx, Admin $actor, array $input, string $key, bool $item = false): array
    {
        $data = validator($input, ['destination' => ['required', Rule::in(array_keys(self::CAPABILITIES))],
            'enabled' => [$item ? 'sometimes' : 'required', 'boolean'], 'config' => [$item ? 'sometimes' : 'required', 'array'],
            'automatic' => ['sometimes', 'boolean'], 'source_type' => [$item ? 'required' : 'sometimes', Rule::in(['ingredient', 'variant'])],
            'source_id' => [$item ? 'required' : 'sometimes', 'integer', 'min:1'], 'destination_id' => [$item ? 'required' : 'sometimes', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:30'], 'factor' => ['sometimes', 'decimal:0,6', 'gt:0', 'max:1000000'], 'expected_version' => ['nullable', 'integer', 'min:1']])->validate();

        return $this->commands->run($ctx, $item ? 'integration.item.save' : 'integration.mapping.save', $data, $key, function () use ($ctx, $actor, $data, $item): array {
            $this->authorizeActor($ctx, $actor);
            $destination = $data['destination'];
            if (! $item) {
                $config = $destination === 'inventory' ? validator($data['config'], ['warehouse_id' => ['required', 'integer', 'min:1']])->validate()
                    : validator($data['config'], ['organization_id' => ['required', 'integer', 'min:1']])->validate();
                if ((bool) $data['enabled']) {
                    $this->validateDestination($ctx, $actor, $destination, $config);
                }
                $identity = ['destination' => $destination];
                $table = 'fnb_integration_mappings';
                $automatic = (bool) ($data['automatic'] ?? false) && (bool) $data['enabled'];
                $values = ['enabled' => $data['enabled'], 'config' => json_encode($config, JSON_THROW_ON_ERROR), 'automatic' => $automatic,
                    'authorized_by' => $automatic ? $actor->id : null, 'authorized_auth_version' => $automatic ? $actor->auth_version : null, 'automatic_paused_reason' => null];
            } else {
                $this->assertCapability($destination);
                $source = DB::table($data['source_type'] === 'ingredient' ? 'fnb_ingredients' : 'fnb_item_variants')->where('website_key', $ctx->websiteKey)->where('id', $data['source_id'])->first();
                abort_unless($source, 422, 'Mặt hàng nguồn không thuộc website.');
                abort_unless(($destination === 'inventory') === ($data['source_type'] === 'ingredient'), 422, 'Mapping kho dùng nguyên liệu; mapping kế toán dùng size món.');
                if ($destination === 'inventory') {
                    $this->inventoryPermission($actor);
                    $target = InvItem::query()->findOrFail($data['destination_id']);
                    $this->validateInventoryItem($target, (string) ($data['unit'] ?? ''));
                } else {
                    $target = AcctItem::query()->where('status', 'active')->findOrFail($data['destination_id']);
                    $this->authorizeOrganization($actor, $ctx, (int) $target->organization_id);
                    abort_unless(bccomp((string) ($data['factor'] ?? 1), '1', 6) === 0, 422, 'Mapping kế toán giữ nguyên bill nguồn.');
                }
                $table = 'fnb_integration_item_mappings';
                $identity = array_intersect_key($data, array_flip(['destination', 'source_type', 'source_id']));
                $values = ['destination_id' => $data['destination_id'], 'unit' => $data['unit'] ?? null, 'factor' => $data['factor'] ?? '1.000000'];
                // A changed item mapping requires a fresh explicit automation opt-in.
                $this->scope('fnb_integration_mappings', $ctx)->where('destination', $destination)->update(['automatic' => false, 'authorized_by' => null, 'authorized_auth_version' => null, 'automatic_paused_reason' => 'item_mapping_changed', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            }
            $query = $this->scope($table, $ctx)->where($identity);
            $existing = (clone $query)->lockForUpdate()->first();
            if ($existing && (int) ($data['expected_version'] ?? 0) !== (int) $existing->version) {
                throw new FnbConflictException('Mapping đã thay đổi.');
            }
            $values += ['version' => ($existing->version ?? 0) + 1, 'updated_at' => now()];
            if ($existing) {
                $query->update($values);
            } else {
                DB::table($table)->insert($identity + $values + ['website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'created_at' => now()]);
            }

            $record = (array) $this->scope($table, $ctx)->where($identity)->first();
            app(FnbAuditLogger::class)->record('fnb.integration.mapping.saved', $ctx->websiteKey, $actor, $table.':'.$record['id'],
                before: $existing === null ? null : (array) $existing, after: $record, outletIds: [$ctx->outletId]);

            return ['resource' => $record, 'meta' => ['resource_type' => 'integration_mapping', 'resource_id' => $record['id'], 'version' => $record['version']]];
        });
    }

    /** Called inside the authorized command transaction so an explicit reset happens once. */
    public function prepare(FnbContext $ctx, Admin $actor, string $destination, bool $retryDeadLetter = false): array
    {
        $this->authorizeActor($ctx, $actor);
        $this->destinationMapping($ctx, $actor, $destination);
        $this->enqueue($ctx, $destination);
        if ($retryDeadLetter) {
            $this->scope('fnb_outbox_deliveries', $ctx)->where('destination', $destination)->where('status', 'dead_letter')->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'last_error' => null, 'updated_at' => now()]);
        }

        return ['accepted' => true, 'destination' => $destination];
    }

    public function reconcile(FnbContext $ctx, Admin $actor, string $destination, ?int $automaticMappingId = null): array
    {
        $this->prepare($ctx, $actor, $destination);
        $ids = $this->scope('fnb_outbox_deliveries', $ctx)->where('destination', $destination)
            ->where(fn ($q) => $q->whereIn('status', ['pending', 'failed'])->where('available_at', '<=', now())->orWhere(fn ($q) => $q->where('status', 'processing')->where('lease_expires_at', '<=', now())))
            ->orderBy('id')->limit(100)->pluck('id');
        $result = ['delivered' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            $state = $this->deliver($ctx, $actor, (int) $id, $automaticMappingId);
            if (isset($result[$state])) {
                $result[$state]++;
            }
        }

        return $result;
    }

    /** A standing grant is explicit, tied to the exact admin identity/auth version and revalidated live. */
    public function runAutomatic(int $mappingId): array
    {
        $mapping = DB::table('fnb_integration_mappings')->where('id', $mappingId)->where('enabled', true)->where('automatic', true)->first();
        if (! $mapping) {
            return ['delivered' => 0, 'failed' => 0];
        }
        $actor = Admin::query()->find($mapping->authorized_by);
        if (! $actor || ! $actor->isAvailable() || (int) $actor->auth_version !== (int) $mapping->authorized_auth_version) {
            $this->pauseGrant($mapping, 'authorizing_identity_changed');

            return ['delivered' => 0, 'failed' => 0];
        }
        if (! isset(self::CAPABILITIES[$mapping->destination]) || ! $this->capabilities->has($mapping->destination, self::CAPABILITIES[$mapping->destination])) {
            return ['delivered' => 0, 'failed' => 0];
        }
        $ctx = new FnbContext($mapping->website_key, (int) $mapping->outlet_id, (int) $actor->id);

        try {
            return $this->reconcile($ctx, $actor, $mapping->destination, automaticMappingId: $mappingId);
        } catch (AuthorizationException $e) {
            $this->pauseGrant($mapping, 'authority_revoked');
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 403) {
                $this->pauseGrant($mapping, 'authority_revoked');
            } elseif ($e->getStatusCode() !== 422) {
                throw $e;
            }
        }

        return ['delivered' => 0, 'failed' => 0];
    }

    private function pauseGrant(object $mapping, string $reason): void
    {
        DB::table('fnb_integration_mappings')->where('id', $mapping->id)->where('version', $mapping->version)
            ->where('authorized_by', $mapping->authorized_by)->update(['automatic' => false, 'automatic_paused_reason' => $reason, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
    }

    private function enqueue(FnbContext $ctx, string $destination): void
    {
        if ($destination === 'inventory') {
            // The development adapter used bill events. Consumption events
            // replace those pending deliveries without replaying their effects.
            $legacyIds = $this->scope('fnb_outbox_events', $ctx)->where('event_type', 'fnb.check.closed')->select('id');
            $this->scope('fnb_outbox_deliveries', $ctx)->where('destination', 'inventory')->whereIn('outbox_event_id', $legacyIds)
                ->whereIn('status', ['pending', 'failed'])->update(['status' => 'superseded', 'updated_at' => now()]);
            $records = $this->scope('fnb_stock_consumptions', $ctx)->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('fnb_outbox_events as events')->whereColumn('events.website_key', 'fnb_stock_consumptions.website_key')
                    ->whereColumn('events.outlet_id', 'fnb_stock_consumptions.outlet_id')->where('events.aggregate_type', 'stock_consumption')
                    ->whereRaw('events.aggregate_id = CAST(fnb_stock_consumptions.id AS CHAR)')->where('events.aggregate_version', 1)->where('events.event_type', 'fnb.stock_consumption.recorded');
            })->orderBy('id')->limit(500)->get();
            foreach ($records as $record) {
                $this->outbox->emit($ctx, 'stock_consumption', $record->id, 1, 'fnb.stock_consumption.recorded', ['consumption_id' => (int) $record->id, 'kind' => $record->kind, 'payload_hash' => $record->payload_hash]);
            }
        }
        $events = $this->scope('fnb_outbox_events', $ctx)->where('event_type', $destination === 'inventory' ? 'fnb.stock_consumption.recorded' : 'fnb.check.closed')
            ->whereNotExists(function ($q) use ($destination): void {
                $q->selectRaw('1')->from('fnb_outbox_deliveries as deliveries')->whereColumn('deliveries.outbox_event_id', 'fnb_outbox_events.id')
                    ->whereColumn('deliveries.website_key', 'fnb_outbox_events.website_key')->whereColumn('deliveries.outlet_id', 'fnb_outbox_events.outlet_id')->where('deliveries.destination', $destination);
            })->orderBy('id')->limit(500)->get();
        foreach ($events as $event) {
            DB::table('fnb_outbox_deliveries')->insertOrIgnore(['website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId,
                'outbox_event_id' => $event->id, 'destination' => $destination, 'idempotency_key' => 'fnb:'.$destination.':'.$event->event_id,
                'status' => 'pending', 'attempts' => 0, 'max_attempts' => 10, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function deliver(FnbContext $ctx, Admin $actor, int $id, ?int $automaticMappingId): string
    {
        $token = (string) Str::uuid();
        $claimed = DB::transaction(function () use ($ctx, $id, $token): bool {
            $row = $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->lockForUpdate()->firstOrFail();
            if (in_array($row->status, ['delivered', 'succeeded', 'dead_letter', 'superseded'], true) || ($row->status === 'processing' && $row->lease_expires_at !== null && now()->lt($row->lease_expires_at))) {
                return false;
            }
            if ((int) $row->attempts >= (int) $row->max_attempts) {
                $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->update(['status' => 'dead_letter', 'lock_token' => null, 'lease_expires_at' => null, 'updated_at' => now()]);

                return false;
            }
            if ($row->status !== 'processing' && now()->lt($row->available_at)) {
                return false;
            }
            $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->update(['status' => 'processing', 'attempts' => $row->attempts + 1, 'lock_token' => $token, 'lease_expires_at' => now()->addMinutes(2), 'updated_at' => now()]);

            return true;
        }, 3);
        if (! $claimed) {
            return 'skipped';
        }
        try {
            $trusted = new FnbContext($ctx->websiteKey, $ctx->outletId, $ctx->actorId, authorizeMutation: function () use ($ctx, $actor, $automaticMappingId): void {
                $this->authorizeActor($ctx, $actor);
                if ($automaticMappingId !== null) {
                    $this->validateAutomaticGrant($ctx, $actor, $automaticMappingId);
                }
            });
            $this->commands->run($trusted, 'integration.deliver', ['delivery_id' => $id], 'integration-delivery:'.$id, function () use ($ctx, $actor, $id, $token): array {
                $delivery = $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->lockForUpdate()->firstOrFail();
                if ($delivery->lock_token !== $token || $delivery->status !== 'processing') {
                    throw new FnbConflictException('Delivery lease ownership changed.');
                }
                $mapping = $this->destinationMapping($ctx, $actor, $delivery->destination, true);
                $config = json_decode($mapping->config, true, 512, JSON_THROW_ON_ERROR);
                $event = $this->scope('fnb_outbox_events', $ctx)->where('id', $delivery->outbox_event_id)->firstOrFail();
                abort_unless(($delivery->destination === 'inventory' && $event->event_type === 'fnb.stock_consumption.recorded' && $event->aggregate_type === 'stock_consumption') || ($delivery->destination === 'accounting-tax' && $event->event_type === 'fnb.check.closed'), 422, 'Loại outbox event không phù hợp adapter.');
                $reference = $delivery->destination === 'inventory'
                    ? $this->postStock($ctx, (int) $event->aggregate_id, $config, $actor, (string) data_get(json_decode($event->payload, true), 'payload_hash'))
                    : $this->accountingDraft($ctx, (int) $event->aggregate_id, $config, $actor);
                $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->where('lock_token', $token)->update(['status' => 'delivered', 'external_reference' => $reference,
                    'published_at' => now(), 'lease_expires_at' => null, 'lock_token' => null, 'last_error' => null, 'updated_at' => now()]);

                return ['resource' => ['delivery_id' => $id, 'status' => 'delivered'], 'meta' => ['resource_type' => 'integration_delivery', 'resource_id' => $id, 'version' => 1]];
            });

            return 'delivered';
        } catch (Throwable $e) {
            DB::transaction(function () use ($ctx, $id, $token): void {
                $row = $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->where('lock_token', $token)->lockForUpdate()->first();
                if (! $row) {
                    return;
                }
                $this->scope('fnb_outbox_deliveries', $ctx)->where('id', $id)->update(['status' => $row->attempts >= $row->max_attempts ? 'dead_letter' : 'failed',
                    'available_at' => now()->addSeconds(min(3600, 5 * (2 ** min(10, $row->attempts - 1)))), 'lease_expires_at' => null, 'lock_token' => null,
                    'last_error' => 'Giao nhận chưa hoàn tất: kiểm tra quyền, module, mapping, độ chính xác, tồn và snapshot nguồn.', 'updated_at' => now()]);
            }, 3);
            report($e);

            return 'failed';
        }
    }

    private function postStock(FnbContext $ctx, int $consumptionId, array $config, Admin $actor, string $expectedHash): string
    {
        $this->validateDestination($ctx, $actor, 'inventory', $config);
        $record = $this->scope('fnb_stock_consumptions', $ctx)->where('id', $consumptionId)->lockForUpdate()->firstOrFail();
        abort_unless(hash_equals((string) $record->payload_hash, $expectedHash), 422, 'Snapshot identity khác outbox.');
        if ($record->status === 'posted') {
            return (string) $record->external_document_reference;
        }
        $this->verifySnapshot($record);
        $lines = $this->scope('fnb_stock_consumption_lines', $ctx)->where('consumption_id', $record->id)->orderBy('id')->get();
        $payload = [];
        $lineage = [];
        foreach ($lines as $line) {
            $source = $line->source_consumption_line_id ? DB::table('fnb_integration_stock_lines')->where('consumption_line_id', $line->source_consumption_line_id)->first() : null;
            if ($line->source_consumption_line_id) {
                abort_unless($source, 422, 'Tiêu hao nguồn chưa được giao nhận; chưa thể đảo hoặc phân loại hao hụt.');
            }
            $map = $source ?? $this->scope('fnb_integration_item_mappings', $ctx)->where('destination', 'inventory')->where('source_type', 'ingredient')->where('source_id', $line->ingredient_id)->sharedLock()->first();
            abort_unless($map, 422, 'Nguyên liệu chưa có mapping.');
            $item = InvItem::query()->sharedLock()->findOrFail($source ? $source->inventory_item_id : $map->destination_id);
            $this->validateInventoryItem($item, (string) ($source ? $source->unit : $map->unit));
            $factor = (string) $map->factor;
            $quantity = bcmul((string) $line->quantity, $factor, 6);
            abort_unless(bccomp($quantity, '0', 6) >= 0 && bccomp($quantity, '99999999999.999', 6) <= 0
                && bccomp($quantity, bcadd($quantity, '0', 3), 6) === 0, 422, 'Kho v1 không thể lưu chính xác số lượng sau quy đổi.');
            if (bccomp($quantity, '0', 6) === 0) {
                continue;
            }
            if ($source) {
                abort_unless((int) $source->warehouse_id === (int) $config['warehouse_id'], 422, 'Không được đảo tiêu hao vào kho khác kho nguồn.');
            }
            $payload[] = ['item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => $source ? $source->unit_cost : '0.00'];
            $lineage[] = ['consumption_line_id' => $line->id, 'inventory_item_id' => $item->id, 'warehouse_id' => $config['warehouse_id'], 'unit' => $item->unit, 'factor' => $factor];
        }
        $type = $record->kind === 'reversal' ? 'receipt' : 'issue';
        abort_unless(in_array($record->kind, ['sale', 'waste', 'reversal'], true), 422, 'Loại tiêu hao không được hỗ trợ.');
        if ($type === 'issue') {
            $payload = $this->quoteIssueCosts($payload, (int) $config['warehouse_id']);
        }
        $reference = 'no-consumption';
        if ($payload !== []) {
            $document = app(InventoryStockService::class)->createDocument(['code' => 'FNB-'.$ctx->outletId.'-'.$record->id, 'type' => $type,
                $type === 'issue' ? 'source_warehouse_id' : 'destination_warehouse_id' => (int) $config['warehouse_id'],
                'reference' => 'fnb-consumption:'.$record->id, 'note' => 'Giao nhận tiêu hao F&B cục bộ.', 'lines' => $payload], $actor->id);
            $documentLines = $document->lines->sortBy('id')->values();
            foreach ($lineage as $index => $row) {
                DB::table('fnb_integration_stock_lines')->insert($row + ['inventory_document_id' => $document->id,
                    'inventory_document_line_id' => $documentLines[$index]->id, 'quantity' => $payload[$index]['quantity'], 'unit_cost' => $payload[$index]['unit_cost'], 'created_at' => now()]);
            }
            $reference = (string) $document->id;
        }
        $this->scope('fnb_stock_consumptions', $ctx)->where('id', $record->id)->update(['status' => 'posted', 'external_document_reference' => $reference, 'posted_at' => now(), 'updated_at' => now()]);

        return $reference;
    }

    private function quoteIssueCosts(array $payload, int $warehouseId): array
    {
        $layers = DB::table('inv_cost_layers')->where('warehouse_id', $warehouseId)->whereIn('item_id', array_column($payload, 'item_id'))->where('quantity_remaining', '>', 0)->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($payload as &$line) {
            $left = $line['quantity'];
            $cost = '0';
            foreach ($layers->where('item_id', $line['item_id']) as $layer) {
                $take = bccomp($left, (string) $layer->quantity_remaining, 6) > 0 ? (string) $layer->quantity_remaining : $left;
                $cost = bcadd($cost, bcmul($take, (string) $layer->unit_cost, 8), 8);
                $layer->quantity_remaining = bcsub((string) $layer->quantity_remaining, $take, 6);
                $left = bcsub($left, $take, 6);
                if (bccomp($left, '0', 6) === 0) {
                    break;
                }
            }
            abort_unless(bccomp($left, '0', 6) === 0, 422, 'Kho thiếu lớp giá vốn cho lượng xuất.');
            $line['unit_cost'] = bcadd(bcdiv($cost, $line['quantity'], 8), '0.005', 2);
        }
        unset($line);

        return $payload;
    }

    private function accountingDraft(FnbContext $ctx, int $checkId, array $config, Admin $actor): string
    {
        $this->validateDestination($ctx, $actor, 'accounting-tax', $config);
        $organization = (int) $config['organization_id'];
        $check = $this->scope('fnb_checks', $ctx)->where('id', $checkId)->where('status', 'closed')->lockForUpdate()->firstOrFail();
        $existing = DB::table('acct_documents')->where('organization_id', $organization)->where('source_module', 'fnb-pos')->where('source_type', 'check')->where('source_id', (string) $checkId)->first();
        $divisor = $check->currency === 'VND' ? '1' : '100';
        if ($existing) {
            abort_unless($existing->website_key === $ctx->websiteKey && $existing->currency === $check->currency && bccomp((string) $existing->grand_total, bcdiv((string) $check->grand_total_minor, $divisor, 2), 2) === 0, 422, 'Chứng từ nguồn đã có nhưng không khớp bill.');

            return (string) $existing->id;
        }
        $lines = DB::table('fnb_check_lines as c')->join('fnb_order_lines as o', 'o.id', '=', 'c.order_line_id')->where('c.website_key', $ctx->websiteKey)->where('c.outlet_id', $ctx->outletId)->where('c.check_id', $checkId)
            ->get(['c.*', 'o.variant_id', 'o.item_name_snapshot', 'o.variant_name_snapshot', 'o.tax_rate_bps_snapshot']);
        $payload = [];
        foreach ($lines as $line) {
            $map = $this->scope('fnb_integration_item_mappings', $ctx)->where('destination', 'accounting-tax')->where('source_type', 'variant')->where('source_id', $line->variant_id)->sharedLock()->first();
            abort_unless($map && bccomp((string) $map->factor, '1', 6) === 0, 422, 'Size món chưa map hợp lệ sang mặt hàng kế toán.');
            $item = AcctItem::query()->where('organization_id', $organization)->where('status', 'active')->sharedLock()->findOrFail($map->destination_id);
            // Preserve exact gross bill value without inventing a tax assessment
            // or rounding six-place POS quantity into a four-place legal invoice.
            $payload[] = ['accounting_item_id' => $item->id, 'line_type' => 'item', 'quantity' => '1.0000',
                'unit_price' => bcdiv((string) $line->allocated_total_minor, $divisor, 2), 'discount_amount' => '0.00', 'tax_category' => 'not_declared', 'tax_rate' => '0.00',
                'snapshot' => ['fnb_check_line_id' => $line->id, 'source_quantity' => (string) $line->allocated_quantity,
                    'source_name' => $line->item_name_snapshot.' / '.$line->variant_name_snapshot,
                    'source_subtotal_minor' => $line->allocated_subtotal_minor, 'source_discount_minor' => $line->allocated_discount_minor,
                    'source_tax_minor' => $line->allocated_tax_minor, 'source_service_charge_minor' => $line->allocated_service_charge_minor,
                    'source_pricing_rounding_minor' => $line->allocated_pricing_rounding_minor, 'source_total_minor' => $line->allocated_total_minor,
                    'requires_tax_classification' => true]];
        }
        $document = app(AccountingDocumentService::class)->create(['organization_id' => $organization, 'direction' => 'outbound', 'document_type' => 'internal_invoice',
            'currency' => $check->currency, 'website_key' => $ctx->websiteKey, 'source_module' => 'fnb-pos', 'source_type' => 'check', 'source_id' => (string) $checkId,
            'idempotency_key' => 'fnb:check:'.$checkId.':draft', 'lines' => $payload,
            'notes' => 'Bản nháp tổng tiền bill '.$check->check_no.'. Chưa phân loại thuế, chưa phải hóa đơn thuế; cần rà soát trước khi duyệt.',
            'metadata' => ['fnb_check_id' => $checkId, 'outlet_id' => $ctx->outletId, 'requires_tax_classification' => true]], $actor->id);
        abort_unless($document->workflow_status === 'draft' && bccomp((string) $document->grand_total, bcdiv((string) $check->grand_total_minor, $divisor, 2), 2) === 0, 422, 'Tổng bản nháp kế toán không khớp bill.');

        return (string) $document->id;
    }

    private function verifySnapshot(object $record): void
    {
        $decoded = json_decode($record->payload_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $hashes = [hash('sha256', $record->payload_snapshot), $this->commands->fingerprint($decoded), hash('sha256', json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
        // MySQL JSON reorders object keys. Reconstruct only known legacy shapes;
        // never accept a failed checksum just because JSON is structurally valid.
        if ($record->source_type === 'check') {
            $legacy = $this->ordered($decoded, ['check_id', 'check_no', 'check_version', 'lines']);
            $legacy['lines'] = array_map(function ($line) {
                $line = $this->ordered($line, ['check_line_id', 'order_line_id', 'quantity', 'recipe_snapshot_hash', 'modifier_recipe_snapshots']);
                $line['modifier_recipe_snapshots'] = array_map(fn ($row) => $this->ordered($row, ['modifier_option_id', 'quantity', 'recipe_snapshot_hash']), $line['modifier_recipe_snapshots'] ?? []);

                return $line;
            }, $legacy['lines'] ?? []);
            $hashes[] = hash('sha256', json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        if ($record->source_type === 'order_line') {
            $legacy = $this->ordered($decoded, ['order_id', 'order_line_id', 'quantity', 'prior_status', 'reason', 'recipe', 'modifier_recipes']);
            if (is_array($legacy['recipe'] ?? null)) {
                $legacy['recipe'] = $this->ordered($legacy['recipe'], ['recipe_id', 'code', 'recipe_version', 'yield_quantity', 'yield_unit', 'binding_multiplier', 'lines']);
                $legacy['recipe']['lines'] = array_map(fn ($line) => $this->ordered($line, ['ingredient_id', 'ingredient_code', 'ingredient_name', 'quantity', 'unit', 'base_quantity', 'base_unit', 'loss_rate']), $legacy['recipe']['lines'] ?? []);
            }
            $legacy['modifier_recipes'] = array_map(fn ($row) => $this->ordered($row, ['modifier_option_id', 'quantity', 'recipe_snapshot_hash']), $legacy['modifier_recipes'] ?? []);
            $hashes[] = hash('sha256', json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        abort_unless(in_array((string) $record->payload_hash, $hashes, true), 422, 'Snapshot tiêu hao không khớp.');
    }

    private function ordered(array $value, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $value)) {
                $result[$key] = $value[$key];
            }
        }
        foreach ($value as $key => $entry) {
            if (! array_key_exists($key, $result)) {
                $result[$key] = $entry;
            }
        }

        return $result;
    }

    private function destinationMapping(FnbContext $ctx, Admin $actor, string $destination, bool $lock = false): object
    {
        abort_unless(isset(self::CAPABILITIES[$destination]), 422);
        $query = $this->scope('fnb_integration_mappings', $ctx)->where('destination', $destination)->where('enabled', true);
        $mapping = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($mapping, 422, 'Chưa bật mapping.');
        $this->validateDestination($ctx, $actor, $destination, json_decode($mapping->config, true, 512, JSON_THROW_ON_ERROR));

        return $mapping;
    }

    private function validateDestination(FnbContext $ctx, Admin $actor, string $destination, array $config): void
    {
        $this->assertCapability($destination);
        if ($destination === 'inventory') {
            $this->inventoryPermission($actor);
            abort_unless(DB::table('inv_warehouses')->where('id', $config['warehouse_id'] ?? 0)->where('is_active', true)->sharedLock()->exists(), 422, 'Kho không khả dụng.');
        } else {
            $this->authorizeOrganization($actor, $ctx, (int) ($config['organization_id'] ?? 0));
            $currency = DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->where('id', $ctx->outletId)->value('currency');
            $organizationCurrency = DB::table('acct_organizations')->where('id', $config['organization_id'])->value('default_currency');
            abort_unless($currency === 'VND' && $organizationCurrency === 'VND', 422, 'Adapter kế toán hiện chỉ hỗ trợ VND; cần contract đơn vị tiền và tỷ giá cho ngoại tệ.');
        }
    }

    private function assertCapability(string $destination): void
    {
        DB::table('module_installations')->where('key', $destination)->sharedLock()->first();
        abort_unless(isset(self::CAPABILITIES[$destination]) && $this->capabilities->has($destination, self::CAPABILITIES[$destination]), 422, 'Module đích chưa bật, đang drain hoặc thiếu capability.');
    }

    private function validateAutomaticGrant(FnbContext $ctx, Admin $actor, int $id): void
    {
        $grant = $this->scope('fnb_integration_mappings', $ctx)->where('id', $id)->where('enabled', true)->where('automatic', true)->sharedLock()->first();
        abort_unless($grant && (int) $grant->authorized_by === (int) $actor->id && (int) $grant->authorized_auth_version === (int) $actor->auth_version, 403, 'Quyền tự động đã bị thu hồi.');
    }

    private function authorizeActor(FnbContext $ctx, Admin $actor, string $permission = 'fnb.settings.manage'): void
    {
        $actor->refresh();
        abort_unless((int) $actor->id === $ctx->actorId && $actor->isAvailable(), 403);
        $this->access->authorizeHq($actor, $ctx->websiteKey, 'fnb.chain.manage');
        $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, $permission);
    }

    private function inventoryPermission(Admin $actor): void
    {
        abort_unless($actor->hasPermission('inventory.issue.manage') && $actor->hasPermission('inventory.receipt.manage'), 403);
    }

    private function validateInventoryItem(InvItem $item, string $unit): void
    {
        abort_unless($item->is_active && ! $item->track_batch && ! $item->track_serial && $item->unit === $unit, 422, 'Đơn vị kho không khớp hoặc hàng cần lô/serial.');
    }

    private function authorizeOrganization(Admin $actor, FnbContext $ctx, int $id): void
    {
        abort_unless($actor->canAccess('accounting.document.create', 'organization', (string) $id), 403);
        abort_unless(DB::table('acct_organizations')->where('id', $id)->where('status', 'active')->sharedLock()->exists() && DB::table('acct_organization_websites')->where('organization_id', $id)->where('website_key', $ctx->websiteKey)->sharedLock()->exists(), 422, 'Pháp nhân không hoạt động hoặc chưa gắn website.');
    }

    private function scope(string $table, FnbContext $ctx): Builder
    {
        return DB::table($table)->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId);
    }
}
