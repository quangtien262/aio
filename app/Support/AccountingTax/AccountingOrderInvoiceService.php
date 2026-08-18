<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctItemSource;
use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Models\AcctParty;
use App\Models\AcctPartySource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingOrderInvoiceService
{
    public function __construct(private readonly AccountingDocumentService $documents) {}

    public function createDraft(Order $order, AcctOrganization $organization, ?int $adminId): AcctDocument
    {
        return DB::transaction(function () use ($order, $organization, $adminId): AcctDocument {
            $existing = AcctDocument::query()
                ->where('organization_id', $organization->id)
                ->where('source_module', 'orders')
                ->where('source_type', 'order')
                ->where('source_id', (string) $order->id)
                ->first();
            if ($existing !== null) {
                return $existing->load(['party', 'lines']);
            }

            $mapped = AcctOrganizationWebsite::query()
                ->where('organization_id', $organization->id)
                ->where('website_key', $order->website_key)
                ->exists();
            if (! $mapped) {
                throw ValidationException::withMessages([
                    'organization_id' => ['Website của đơn hàng chưa được gán cho pháp nhân đã chọn.'],
                ]);
            }

            $party = $this->resolveParty($order, $organization);
            $requiresClassification = false;
            $lines = $order->items->map(function (OrderItem $orderItem) use ($organization, &$requiresClassification): array {
                $source = $orderItem->catalog_product_id
                    ? AcctItemSource::query()
                        ->where('organization_id', $organization->id)
                        ->where('source_module', 'catalog')
                        ->where('source_type', 'catalog.product')
                        ->where('source_id', (string) $orderItem->catalog_product_id)
                        ->with('item')
                        ->first()
                    : null;
                $item = $source?->item;
                $lineRequiresClassification = $item === null || $item->tax_rate === null;
                if ($lineRequiresClassification) {
                    $requiresClassification = true;
                }

                return [
                    'accounting_item_id' => $item?->id,
                    'line_type' => 'item',
                    'item_kind' => $item?->kind ?? 'goods',
                    'name' => $orderItem->product_name,
                    'sku' => $orderItem->sku,
                    'unit' => $item?->unit ?? 'pcs',
                    'quantity' => (string) $orderItem->quantity,
                    'unit_price' => (string) $orderItem->unit_price,
                    'discount_amount' => '0.00',
                    // Keep the generated draft valid while making the missing
                    // classification explicit for the reviewer. "not_declared"
                    // is a supported semantic category; the metadata below
                    // prevents this fallback from being mistaken for a tax
                    // determination made by the source order.
                    'tax_category' => $item?->tax_category ?? 'not_declared',
                    'tax_rate' => $item?->tax_rate,
                    'snapshot' => [
                        'source_module' => 'orders',
                        'source_type' => 'order_item',
                        'source_id' => (string) $orderItem->id,
                        'catalog_product_id' => $orderItem->catalog_product_id,
                        'original_price' => (string) $orderItem->original_price,
                        'line_total' => (string) $orderItem->line_total,
                        'requires_tax_classification' => $lineRequiresClassification,
                    ],
                ];
            })->all();

            return $this->documents->create([
                'organization_id' => $organization->id,
                'party_id' => $party->id,
                'direction' => 'outbound',
                'document_type' => 'internal_invoice',
                'document_no' => null,
                'document_date' => null,
                'currency' => $organization->default_currency ?: 'VND',
                'base_currency' => $organization->default_currency ?: 'VND',
                'exchange_rate' => '1.000000',
                'website_key' => $order->website_key,
                'source_module' => 'orders',
                'source_type' => 'order',
                'source_id' => (string) $order->id,
                'idempotency_key' => "order:{$organization->id}:{$order->id}:invoice-draft:v1",
                'created_by' => $adminId,
                'notes' => "Tạo từ đơn hàng {$order->order_code}. Cần xác nhận thời điểm lập hóa đơn trước khi duyệt.",
                'metadata' => [
                    'order_code' => $order->order_code,
                    'order_placed_at' => $order->placed_at?->toIso8601String(),
                    'payment_method' => $order->payment_method,
                    'requires_tax_classification' => $requiresClassification,
                    'issuance_event_required' => true,
                ],
                'lines' => $lines,
            ], $adminId);
        }, 3);
    }

    private function resolveParty(Order $order, AcctOrganization $organization): AcctParty
    {
        $source = AcctPartySource::query()
            ->where('organization_id', $organization->id)
            ->where('source_module', 'orders')
            ->where('source_type', 'customer')
            ->where('source_id', (string) ($order->customer_id ?: 'order-'.$order->id))
            ->with('party')
            ->first();
        $party = $source?->party ?? AcctParty::query()->create([
            'organization_id' => $organization->id,
            'type' => 'customer',
            'name' => $order->customer_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
            'address' => $order->delivery_address,
            'metadata' => ['source' => 'order'],
        ]);

        if ($source !== null) {
            $party->forceFill([
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'address' => $order->delivery_address,
            ])->save();
        }

        AcctPartySource::query()->updateOrCreate([
            'organization_id' => $organization->id,
            'source_module' => 'orders',
            'source_type' => 'customer',
            'source_id' => (string) ($order->customer_id ?: 'order-'.$order->id),
        ], [
            'party_id' => $party->id,
            'source_key' => $order->customer_email ?: $order->customer_phone,
            'snapshot' => [
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
            ],
            'synced_at' => now(),
        ]);

        return $party;
    }
}
