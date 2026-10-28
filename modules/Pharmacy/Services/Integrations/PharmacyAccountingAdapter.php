<?php

namespace Modules\Pharmacy\Services\Integrations;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\AcctDocument;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Support\AccountingTax\AccountingDocumentService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;

/** Opt-in internal drafts only, with an explicit legal and item mapping. */
final class PharmacyAccountingAdapter
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        abort_unless(in_array($action, ['accounting.mapping.save', 'accounting.draft'], true), 422);

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key, function (Admin $live) use ($input, $action): array {
            abort_unless(app(ModuleCapabilityChecker::class)->has('accounting-tax', 'accounting.documents.manage.v1'), 503, 'Cần cài đặt hoặc cập nhật và bật ứng dụng “Kế toán & Thuế” để tạo chứng từ.');
            $access = app(PharmacyAccessService::class);
            $p = $access->pharmacy($live, $input['pharmacy_id'] ?? '', 'pharmacy.integrations.manage', true);
            if ($action === 'accounting.mapping.save') {
                $access->chain($live, (int) $p->chain_id, 'pharmacy.integrations.manage', true);
            }
            $organization = (int) ($input['organization_id'] ?? 0);
            $this->authority($live, $organization);
            if ($action === 'accounting.draft') {
                $access->pharmacy($live, (int) $p->id, 'pharmacy.sale.view', true);
            }

            return ['pharmacy' => $p, 'permission' => 'pharmacy.integrations.manage'];
        }, fn (Admin $live, array $scope) => $action === 'accounting.mapping.save' ? $this->mapping($live, $scope['pharmacy'], $input) : $this->draft($live, $scope['pharmacy'], $input));
    }

    private function authority(Admin $actor, int $organization): void
    {
        abort_unless($organization > 0 && $actor->canAccess('accounting.document.create', 'organization', (string) $organization) && $actor->canAccess('accounting.integration.sync', 'organization', (string) $organization), 403, 'Cần quyền tạo/đồng bộ kế toán của đúng pháp nhân.');
        abort_unless(AcctOrganization::query()->whereKey($organization)->where('status', 'active')->where('default_currency', 'VND')->exists(), 422, 'Pháp nhân kế toán phải hoạt động và dùng VND.');
    }

    private function mapping(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['organization_id' => 'required|integer|min:1', 'item_mappings' => 'required|array|max:1000', 'item_mappings.*.product_id' => 'required|uuid|distinct', 'item_mappings.*.accounting_item_id' => 'required|integer|min:1', 'expected_version' => 'sometimes|integer|min:1'])->validate();
        $map = DB::table('ph_accounting_mappings')->where('legal_entity_ref', $p->legal_entity_ref)->lockForUpdate()->first();
        if ($map) {
            abort_unless((int) $map->organization_id === (int) $v['organization_id'] && (int) ($v['expected_version'] ?? 0) === (int) $map->version, 409, 'Không đổi ngầm pháp nhân hoặc mapping stale.');
        }
        abort_if(DB::table('ph_accounting_mappings')->where('organization_id', $v['organization_id'])->where('legal_entity_ref', '!=', $p->legal_entity_ref)->exists(), 409, 'Pháp nhân đã gắn chủ sở hữu kho khác.');
        $items = [];
        foreach ($v['item_mappings'] as $item) {
            D::row('ph_products', $item['product_id'], $p->chain_id);
            abort_unless(AcctItem::query()->whereKey($item['accounting_item_id'])->where('organization_id', $v['organization_id'])->where('status', 'active')->exists(), 422, 'Item kế toán sai pháp nhân.');
            $items[$item['product_id']] = $item['accounting_item_id'];
        }
        $values = ['chain_id' => $p->chain_id, 'legal_entity_ref' => $p->legal_entity_ref, 'organization_id' => $v['organization_id'], 'item_mappings' => D::encode($items), 'authorized_by' => $actor->id, 'authorized_auth_version' => $actor->auth_version, 'status' => 'active'];
        $row = $map ? D::update('ph_accounting_mappings', $map, $values, $v['expected_version']) : D::insert('ph_accounting_mappings', $values);

        return D::resource($row);
    }

    private function draft(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['source_type' => 'required|in:sale,refund', 'source_id' => 'required|uuid', 'organization_id' => 'required|integer|min:1'])->validate();
        $map = DB::table('ph_accounting_mappings')->where('chain_id', $p->chain_id)->where('legal_entity_ref', $p->legal_entity_ref)->where('organization_id', $v['organization_id'])->where('status', 'active')->sharedLock()->first();
        abort_unless($map, 409, 'Cần mapping pháp nhân hiện hành.');
        $grant = Admin::query()->find($map->authorized_by);
        abort_unless($grant && $grant->isAvailable() && (int) $grant->auth_version === (int) $map->authorized_auth_version, 403, 'Ủy nhiệm mapping đã thay đổi.');
        app(PharmacyAccessService::class)->chain($grant, (int) $p->chain_id, 'pharmacy.integrations.manage');
        $this->authority($grant, $v['organization_id']);
        $source = D::row($v['source_type'] === 'sale' ? 'ph_sales' : 'ph_refunds', $v['source_id'], $p->chain_id, $p->id, true);
        abort_unless(in_array($source->status, ['paid', 'partially_refunded', 'refunded'], true), 409, 'Nguồn chưa chốt.');
        if ($v['source_type'] === 'refund') {
            app(PharmacyAccessService::class)->pharmacy($actor, (int) $p->id, 'pharmacy.return.manage');
        }
        $amount = $v['source_type'] === 'sale' ? (int) $source->total_minor : (int) $source->amount_minor;
        $existing = DB::table('ph_accounting_source_links')->where('source_type', $v['source_type'])->where('source_id', $source->public_id)->first();
        if ($existing) {
            abort_unless((int) $existing->organization_id === (int) $v['organization_id'], 409);
            abort_unless(AcctDocument::query()->whereKey($existing->document_id)->where('organization_id', $v['organization_id'])->where('source_module', 'pharmacy')->where('source_type', $v['source_type'])->where('source_id', $source->public_id)->where('grand_total', $amount)->exists(), 409, 'Liên kết nguồn kế toán không còn hợp lệ.');

            return ['id' => $existing->public_id, 'status' => 'linked', 'document_id' => $existing->document_id];
        }
        $lines = [];
        $amount = $v['source_type'] === 'sale' ? (int) $source->total_minor : (int) $source->amount_minor;
        if ($v['source_type'] === 'sale') {
            $mappings = D::json($map->item_mappings);
            foreach (DB::table('ph_dispensing_lines')->where('dispensing_id', $source->dispensing_id)->where('status', 'active')->orderBy('id')->get() as $line) {
                $product = D::row('ph_products', (int) $line->product_id, $p->chain_id);
                $itemId = $mappings[$product->public_id] ?? 0;
                $item = AcctItem::query()->whereKey($itemId)->where('organization_id', $v['organization_id'])->where('status', 'active')->first();
                abort_unless($item, 422, 'Cần map/phân loại tất cả thuốc sang item kế toán.');
                $snapshot = D::json(Crypt::decryptString($line->encrypted_snapshot));
                $lines[] = ['line_type' => 'item', 'item_kind' => $item->kind, 'name' => $snapshot['name'], 'sku' => $product->code, 'unit' => 'dòng', 'quantity' => '1', 'unit_price' => (string) $line->total_minor, 'discount_amount' => '0', 'tax_category' => 'not_declared', 'tax_rate' => '0', 'snapshot' => ['requires_tax_classification' => true, 'source_line_id' => $line->public_id, 'source_quantity' => (string) $line->quantity, 'source_unit' => $line->unit, 'accounting_item_id' => $item->id]];
            }
        } else {
            $lines[] = ['line_type' => 'adjustment', 'item_kind' => 'charge', 'name' => 'Hoàn tiền từ Nhà thuốc — chứng từ nội bộ', 'unit' => 'lần', 'quantity' => '1', 'unit_price' => (string) $amount, 'discount_amount' => '0', 'tax_category' => 'not_declared', 'tax_rate' => '0', 'snapshot' => ['requires_tax_classification' => true, 'sale_id' => $source->sale_id, 'return_id' => $source->return_id]];
        }
        abort_unless($lines && $amount > 0, 422);
        $payload = ['organization_id' => $v['organization_id'], 'direction' => $v['source_type'] === 'sale' ? 'outbound' : 'inbound', 'document_type' => 'internal_invoice', 'document_date' => substr($source->created_at, 0, 10), 'currency' => 'VND', 'source_module' => 'pharmacy', 'source_type' => $v['source_type'], 'source_id' => $source->public_id, 'idempotency_key' => 'pharmacy:'.$v['source_type'].':'.$source->public_id, 'metadata' => ['requires_tax_classification' => true, 'contract_version' => 'pharmacy.internal_draft.v1', 'legal_entity_ref' => $p->legal_entity_ref, 'pharmacy_id' => $p->public_id, 'mapping_version' => $map->version], 'lines' => $lines];
        $document = app(AccountingDocumentService::class)->create($payload, $actor->id);
        abort_unless($document->workflow_status === 'draft' && $document->document_type === 'internal_invoice' && BigDecimal::of((string) $document->grand_total)->isEqualTo((string) $amount), 409, 'Draft kế toán không khớp nguồn.');
        $link = D::insert('ph_accounting_source_links', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'source_type' => $v['source_type'], 'source_id' => $source->public_id, 'organization_id' => $v['organization_id'], 'document_id' => $document->id, 'source_hash' => hash('sha256', D::encode($payload)), 'actor_id' => $actor->id]);

        return ['id' => $link->public_id, 'status' => 'linked', 'document_id' => $document->id];
    }
}
