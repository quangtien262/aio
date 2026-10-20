<?php

namespace Modules\RetailPos\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\AcctDocument;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Support\AccountingTax\AccountingDocumentService;
use App\Support\AuditLogger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Opt-in projection only: Retail sales never auto-approve, post or issue tax invoices. */
class RetailAccountingBridge
{
    private const CONTRACT = 'retail.internal_draft.v1';

    public function __construct(private readonly RetailService $retail, private readonly ModuleCapabilityChecker $capabilities, private readonly AccountingDocumentService $documents) {}

    public function status(Admin $actor, string $branchId): array
    {
        $branch = $this->retail->branch($actor, $branchId, 'retail.integration.manage');
        $mapping = DB::table('retail_accounting_mappings')->where('branch_id', $branch->id)->first();
        $available = $this->available();
        $organizations = [];
        $items = [];
        if ($available) {
            $organizations = AcctOrganization::query()->where('status', 'active')->get()->filter(fn ($org) => $this->nativeAllowed($actor, $org->id))->map(fn ($org) => ['id' => $org->id, 'name' => $org->name])->values()->all();
            if ($mapping && $this->nativeAllowed($actor, $mapping->organization_id)) {
                $items = AcctItem::query()->where('organization_id', $mapping->organization_id)->where('status', 'active')->limit(1000)->get(['id', 'organization_id', 'name', 'sku', 'kind'])->toArray();
            } elseif ($organizations !== []) {
                $items = AcctItem::query()->whereIn('organization_id', array_column($organizations, 'id'))->where('status', 'active')->limit(1000)->get(['id', 'organization_id', 'name', 'sku', 'kind'])->toArray();
            }
        }
        $skuMappings = $mapping ? DB::table('retail_accounting_item_links as l')->join('merch_skus as s', 's.id', '=', 'l.sku_id')->where('mapping_id', $mapping->id)->get(['s.public_id as sku_id', 'l.accounting_item_id'])->map(fn ($r) => (array) $r)->all() : [];
        $deliveries = DB::table('retail_integration_links')->where('branch_id', $branch->id)->orderByDesc('id')->limit(100)->get(['public_id', 'source_id', 'organization_id', 'destination_id', 'contract_version', 'created_at'])->map(fn ($r) => (array) $r)->all();

        return ['accounting' => [
            'available' => $available, 'enabled' => (bool) $mapping?->enabled, 'mapping' => $mapping ? (array) $mapping : null,
            'organizations' => $organizations, 'items' => $items, 'sku_mappings' => $skuMappings, 'deliveries' => $deliveries,
            'pending_sales' => DB::table('retail_outbox')->where('branch_id', $branch->id)->where('event_type', 'sale.completed')->where('status', '!=', 'processed')->count(),
            'deferred_events' => DB::table('retail_outbox')->where('branch_id', $branch->id)->where('event_type', '!=', 'sale.completed')->where('status', '!=', 'processed')->count(),
            'mode' => 'internal_draft_only', 'projection_basis' => 'retail_line_net_total', 'automatic_dispatch' => false,
        ], 'offline' => ['enabled' => false], 'payment_provider_connected' => false];
    }

    public function configure(Admin $actor, array $input): array
    {
        $data = validator($input, ['branch_id' => 'required|uuid', 'organization_id' => 'required|integer|min:1', 'enabled' => 'required|boolean', 'expected_version' => 'required|integer|min:0', 'sku_mappings' => 'sometimes|array|max:1000', 'sku_mappings.*.sku_id' => 'required|uuid|distinct', 'sku_mappings.*.accounting_item_id' => 'required|integer|min:1'])->validate();

        return DB::transaction(function () use ($actor, $data): array {
            $live = $this->liveActor($actor);
            $branch = $this->retail->branch($live, $data['branch_id'], 'retail.integration.manage', true);
            $old = DB::table('retail_accounting_mappings')->where('branch_id', $branch->id)->lockForUpdate()->first();
            abort_unless((int) ($old?->version ?? 0) === $data['expected_version'], 409, 'Mapping đã thay đổi.');
            if (! $data['enabled']) {
                abort_unless($old && (int) $old->organization_id === $data['organization_id'], 422, 'Chỉ tắt mapping đã cấu hình.');
                DB::table('retail_accounting_mappings')->where('id', $old->id)->update(['enabled' => false, 'version' => $old->version + 1, 'updated_at' => now()]);
                $this->audit($live, 'disabled', $branch->public_id, ['organization_id' => $old->organization_id]);

                return $this->status($live, $branch->public_id);
            }
            abort_unless($this->available(), 503, 'Accounting chưa được cài/bật đúng capability.');
            $this->assertNative($live, $data['organization_id']);
            $organization = AcctOrganization::query()->whereKey($data['organization_id'])->where('status', 'active')->lockForUpdate()->first();
            abort_unless($organization && strtoupper((string) $organization->default_currency) === 'VND', 422, 'Pháp nhân cần hoạt động và dùng VND.');
            $legal = DB::table('retail_legal_entities')->where('id', $branch->legal_entity_id)->lockForUpdate()->first();
            $legalLink = DB::table('retail_legal_accounting_links')->where('legal_entity_id', $legal->id)->first();
            abort_if($legalLink && (int) $legalLink->organization_id !== (int) $organization->id, 409, 'Pháp nhân Retail đã gắn với pháp nhân kế toán khác.');
            $organizationLink = DB::table('retail_legal_accounting_links')->where('organization_id', $organization->id)->first();
            abort_if($organizationLink && (int) $organizationLink->legal_entity_id !== (int) $legal->id, 409, 'Pháp nhân kế toán đã gắn với pháp nhân Retail khác.');
            if (! $legalLink) {
                DB::table('retail_legal_accounting_links')->insert(['legal_entity_id' => $legal->id, 'organization_id' => $organization->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $catalog = DB::table('retail_chains')->where('id', $branch->chain_id)->value('catalog_id');
            $links = [];
            foreach ($data['sku_mappings'] ?? [] as $map) {
                $sku = DB::table('merch_skus')->where('catalog_id', $catalog)->where('public_id', $map['sku_id'])->first();
                $item = AcctItem::query()->whereKey($map['accounting_item_id'])->where('organization_id', $organization->id)->where('status', 'active')->first();
                abort_unless($sku && $item && in_array($item->kind, ['goods', 'service', 'charge', 'asset', 'bundle'], true), 422, 'SKU hoặc item kế toán chưa được phân loại đúng pháp nhân.');
                $links[] = ['sku_id' => $sku->id, 'accounting_item_id' => $item->id];
            }
            $values = ['branch_id' => $branch->id, 'legal_entity_id' => $legal->id, 'organization_id' => $organization->id, 'enabled' => true, 'authorized_by' => $live->id, 'authorized_auth_version' => $live->auth_version, 'version' => $old ? $old->version + 1 : 1, 'updated_at' => now()];
            $mappingId = $old?->id;
            if ($old) {
                DB::table('retail_accounting_mappings')->where('id', $old->id)->update($values);
            } else {
                $mappingId = DB::table('retail_accounting_mappings')->insertGetId($values + ['created_at' => now()]);
            }
            DB::table('retail_accounting_item_links')->where('mapping_id', $mappingId)->delete();
            foreach ($links as $link) {
                DB::table('retail_accounting_item_links')->insert($link + ['mapping_id' => $mappingId, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($live, 'configured', $branch->public_id, ['organization_id' => $organization->id, 'mapping_version' => $values['version'], 'sku_count' => count($links)]);

            return $this->status($live, $branch->public_id);
        }, 3);
    }

    public function assertNative(Admin $actor, int $organizationId): void
    {
        abort_unless($this->nativeAllowed($actor, $organizationId), 403, 'Cần quyền kế toán tạo chứng từ và đồng bộ của đúng pháp nhân.');
    }

    public function reconcile(Admin $actor, string $branchId, int $limit = 20): array
    {
        $branch = $this->retail->branch($actor, $branchId, 'retail.integration.manage');
        $mapping = DB::table('retail_accounting_mappings')->where('branch_id', $branch->id)->first();
        if (! $mapping || ! $mapping->enabled || ! $this->available()) {
            return ['status' => 'pending', 'processed' => 0, 'blocked' => 0, 'errors' => [], 'reason' => 'ACCOUNTING_NOT_READY_OR_NOT_OPTED_IN'];
        }
        $this->assertNative($actor, $mapping->organization_id);
        $ids = DB::table('retail_outbox')->where('branch_id', $branch->id)->where('event_type', 'sale.completed')->where('status', '!=', 'processed')->orderBy('id')->limit(min(100, max(1, $limit)))->pluck('id');
        $processed = 0;
        $errors = [];
        foreach ($ids as $id) {
            try {
                if ($this->deliver($actor, $branchId, $id)) {
                    $processed++;
                }
            } catch (\Throwable $e) {
                $reason = $e instanceof HttpException ? 'ACCOUNTING_BLOCKED_'.$e->getStatusCode() : ($e instanceof ValidationException ? 'ACCOUNTING_VALIDATION_REQUIRED' : 'ACCOUNTING_DELIVERY_FAILED');
                DB::table('retail_outbox')->where('id', $id)->where('status', '!=', 'processed')->update(['last_error' => $reason, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
                $errors[] = ['event_id' => $id, 'code' => $reason];
            }
        }

        return ['status' => $errors === [] ? 'processed' : 'pending', 'processed' => $processed, 'blocked' => count($errors), 'errors' => $errors, 'pending' => DB::table('retail_outbox')->where('branch_id', $branch->id)->where('event_type', 'sale.completed')->where('status', '!=', 'processed')->count()];
    }

    private function deliver(Admin $actor, string $branchId, int $eventId): bool
    {
        return DB::transaction(function () use ($actor, $branchId, $eventId): bool {
            $live = $this->liveActor($actor);
            $branch = $this->retail->branch($live, $branchId, 'retail.integration.manage', true);
            $mapping = DB::table('retail_accounting_mappings')->where('branch_id', $branch->id)->lockForUpdate()->first();
            $installation = DB::table('module_installations')->where('key', 'accounting-tax')->lockForUpdate()->first();
            abort_unless($mapping && $mapping->enabled && $installation?->status === 'enabled' && $this->available(), 503);
            $this->assertNative($live, $mapping->organization_id);
            $grant = Admin::query()->whereKey($mapping->authorized_by)->lockForUpdate()->first();
            abort_unless($grant && $grant->is_active && $grant->status === 'active' && ! $grant->locked_at && (int) $grant->auth_version === (int) $mapping->authorized_auth_version, 403, 'Quyền ủy nhiệm đã thay đổi.');
            $this->retail->branch($grant, $branchId, 'retail.integration.manage');
            $this->assertNative($grant, $mapping->organization_id);
            abort_unless((int) $mapping->legal_entity_id === (int) $branch->legal_entity_id, 409);
            abort_unless(DB::table('retail_legal_accounting_links')->where('legal_entity_id', $branch->legal_entity_id)->where('organization_id', $mapping->organization_id)->exists(), 409);
            $event = DB::table('retail_outbox')->where('id', $eventId)->where('branch_id', $branch->id)->where('event_type', 'sale.completed')->lockForUpdate()->first();
            abort_unless($event, 404);
            if ($event->status === 'processed') {
                return false;
            }
            $sale = DB::table('retail_sales')->where('public_id', $event->source_id)->where('branch_id', $branch->id)->where('status', 'completed')->lockForUpdate()->first();
            abort_unless($sale && (int) $sale->legal_entity_id === (int) $mapping->legal_entity_id, 409);
            $link = DB::table('retail_integration_links')->where('source_id', $sale->public_id)->where('destination_module', 'accounting-tax')->where('operation_type', 'sale.internal_draft')->first();
            if (! $link) {
                $payload = $this->payload($sale, $mapping, $branch);
                $document = $this->documents->create($payload, $live->id);
                abort_unless($document->workflow_status === 'draft' && $document->document_type === 'internal_invoice', 409);
                abort_unless(BigDecimal::of((string) $document->grand_total)->isEqualTo((string) $sale->total_minor), 409, 'Giá trị chứng từ không khớp nguồn.');
                DB::table('retail_integration_links')->insert(['public_id' => (string) Str::uuid(), 'source_id' => $sale->public_id, 'destination_module' => 'accounting-tax', 'operation_type' => 'sale.internal_draft', 'branch_id' => $branch->id, 'organization_id' => $mapping->organization_id, 'destination_id' => $document->id, 'source_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'contract_version' => self::CONTRACT, 'payload_snapshot' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $document = AcctDocument::query()->whereKey($link->destination_id)->where('organization_id', $link->organization_id)->where('source_module', 'retail-pos')->where('source_id', $sale->public_id)->first();
                abort_unless($document && (int) $link->organization_id === (int) $mapping->organization_id, 409, 'Liên kết chứng từ cần đối chiếu.');
            }
            DB::table('retail_outbox')->where('id', $event->id)->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null, 'attempts' => $event->attempts + 1, 'updated_at' => now()]);
            $this->audit($live, 'draft.created', $branch->public_id, ['sale_id' => $sale->public_id, 'organization_id' => $mapping->organization_id, 'document_id' => $document->id]);

            return true;
        }, 3);
    }

    private function payload(object $sale, object $mapping, object $branch): array
    {
        $organization = AcctOrganization::query()->whereKey($mapping->organization_id)->where('status', 'active')->first();
        abort_unless($organization && strtoupper((string) $organization->default_currency) === 'VND' && $sale->currency === 'VND', 422);
        $lines = DB::table('retail_sale_lines')->where('sale_id', $sale->id)->orderBy('id')->get();
        abort_unless($lines->isNotEmpty(), 422);
        $sourceLines = [];
        $accountingLines = [];
        foreach ($lines as $line) {
            $map = DB::table('retail_accounting_item_links')->where('mapping_id', $mapping->id)->where('sku_id', $line->sku_id)->first();
            $item = $map ? AcctItem::query()->whereKey($map->accounting_item_id)->where('organization_id', $mapping->organization_id)->where('status', 'active')->first() : null;
            abort_unless($item && in_array($item->kind, ['goods', 'service', 'charge', 'asset', 'bundle'], true), 422, 'Cần phân loại và ánh xạ toàn bộ SKU trước khi chuyển.');
            $source = ['sale_line_id' => $line->public_id, 'sku_id' => $line->sku_id, 'sku' => $line->sku, 'name' => $line->name, 'unit' => $line->unit, 'quantity' => (string) $line->quantity, 'unit_price_minor' => (string) $line->price_minor, 'gross_minor' => (string) $line->gross_minor, 'discount_minor' => (string) $line->discount_minor, 'net_minor' => (string) $line->total_minor, 'cost' => $line->cost === null ? null : (string) $line->cost, 'accounting_item_id' => $item->id, 'accounting_item_kind' => $item->kind];
            $sourceLines[] = $source;
            // Aggregated exact net totals preserve Retail's 6-decimal quantity and VND rounding.
            // The accounting source quantity is in snapshot; master name/price never replace it.
            $accountingLines[] = ['line_type' => 'item', 'item_kind' => $item->kind, 'name' => $line->name, 'sku' => $line->sku, 'unit' => 'dòng', 'quantity' => '1', 'unit_price' => (string) $line->total_minor, 'discount_amount' => '0', 'tax_category' => 'not_declared', 'tax_rate' => '0', 'snapshot' => ['requires_tax_classification' => true, 'projection_basis' => 'retail_line_net_total', 'retail_source' => $source]];
        }

        return ['organization_id' => (int) $mapping->organization_id, 'direction' => 'outbound', 'document_type' => 'internal_invoice', 'document_date' => Carbon::parse($sale->completed_at)->timezone($branch->timezone)->toDateString(), 'currency' => 'VND', 'source_module' => 'retail-pos', 'source_type' => 'sale', 'source_id' => $sale->public_id, 'idempotency_key' => 'retail-sale:'.$sale->public_id.':internal-draft', 'notes' => 'Bản nháp nội bộ từ Retail; kế toán cần kiểm tra và phân loại thuế.', 'metadata' => ['requires_tax_classification' => true, 'projection_basis' => 'retail_line_net_total', 'contract_version' => self::CONTRACT, 'branch_id' => $branch->public_id, 'legal_entity_id' => $mapping->legal_entity_id, 'sale_id' => $sale->public_id, 'source_lines' => $sourceLines, 'source_total_minor' => (string) $sale->total_minor, 'source_discount_minor' => (string) $sale->discount_minor, 'mapping_version' => (int) $mapping->version], 'lines' => $accountingLines];
    }

    private function available(): bool
    {
        return $this->capabilities->has('accounting-tax', 'accounting.documents.manage.v1') && Schema::hasTable('acct_organizations') && Schema::hasTable('acct_items') && Schema::hasTable('acct_documents');
    }

    private function nativeAllowed(Admin $actor, int $organizationId): bool
    {
        return $actor->canAccess('accounting.document.create', 'organization', (string) $organizationId) && $actor->canAccess('accounting.integration.sync', 'organization', (string) $organizationId);
    }

    private function liveActor(Admin $actor): Admin
    {
        $live = Admin::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($live->is_active && $live->status === 'active' && ! $live->locked_at && (int) $live->auth_version === (int) $actor->auth_version, 403);

        return $live;
    }

    private function audit(Admin $actor, string $action, string $branch, array $after): void
    {
        app(AuditLogger::class)->record('retail.accounting.'.$action, 'retail-integration', after: ['branch_id' => $branch] + $after, actor: $actor, moduleKey: 'retail-pos');
    }
}
