<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctDocumentLine;
use App\Models\AcctDocumentPayment;
use App\Models\AcctItem;
use App\Models\AcctItemSource;
use App\Models\AcctOrganization;
use App\Models\AcctParty;

class AccountingTaxSerializer
{
    public static function organization(AcctOrganization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'tax_code' => $organization->tax_code,
            'legal_name' => $organization->legal_name,
            'email' => $organization->email,
            'phone' => $organization->phone,
            'address' => $organization->address,
            'default_currency' => $organization->default_currency,
            'settings' => $organization->settings ?? [],
            'is_default' => (bool) $organization->is_default,
            'status' => $organization->status,
            'website_keys' => $organization->relationLoaded('websites')
                ? $organization->websites->pluck('website_key')->values()->all()
                : [],
        ];
    }

    public static function party(AcctParty $party): array
    {
        return [
            'id' => $party->id,
            'organization_id' => $party->organization_id,
            'type' => $party->type,
            'name' => $party->name,
            'tax_code' => $party->tax_code,
            'email' => $party->email,
            'phone' => $party->phone,
            'address' => $party->address,
            'metadata' => $party->metadata ?? [],
        ];
    }

    public static function item(AcctItem $item): array
    {
        return [
            'id' => $item->id,
            'organization_id' => $item->organization_id,
            'kind' => $item->kind,
            'name' => $item->name,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'default_price' => (float) $item->default_price,
            'tax_rate' => $item->tax_rate !== null ? (float) $item->tax_rate : null,
            'tax_category' => $item->tax_category,
            'revenue_account' => $item->revenue_account,
            'expense_account' => $item->expense_account,
            'is_stock_tracked' => (bool) $item->is_stock_tracked,
            'status' => $item->status,
            'metadata' => $item->metadata ?? [],
            'sources' => $item->relationLoaded('sources')
                ? $item->sources->map(fn (AcctItemSource $source): array => self::source($source))->values()->all()
                : [],
        ];
    }

    public static function document(AcctDocument $document): array
    {
        return [
            'id' => $document->id,
            'organization_id' => $document->organization_id,
            'party' => $document->relationLoaded('party') && $document->party ? self::party($document->party) : null,
            'direction' => $document->direction,
            'document_type' => $document->document_type,
            'document_no' => $document->document_no,
            'document_date' => $document->document_date?->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'currency' => $document->currency,
            'workflow_status' => $document->workflow_status,
            'version' => (int) $document->version,
            'payment_status' => $document->payment_status,
            'legal_status' => $document->legal_status,
            'inventory_status' => $document->inventory_status,
            'mail_status' => $document->mail_status,
            'subtotal' => (float) $document->subtotal,
            'discount_total' => (float) $document->discount_total,
            'tax_total' => (float) $document->tax_total,
            'grand_total' => (float) $document->grand_total,
            'base_currency' => $document->base_currency,
            'exchange_rate' => (string) $document->exchange_rate,
            'base_subtotal' => (float) $document->base_subtotal,
            'base_discount_total' => (float) $document->base_discount_total,
            'base_tax_total' => (float) $document->base_tax_total,
            'base_grand_total' => (float) $document->base_grand_total,
            'paid_amount' => (float) $document->paid_amount,
            'tax_period' => $document->tax_period,
            'tax_eligibility' => $document->tax_eligibility,
            'tax_breakdown' => $document->tax_breakdown ?? [],
            'seller_snapshot' => $document->seller_snapshot,
            'buyer_snapshot' => $document->buyer_snapshot,
            'snapshot_hash' => $document->snapshot_hash,
            'original_document_id' => $document->original_document_id,
            'correction_type' => $document->correction_type,
            'effect_sign' => (int) $document->effect_sign,
            'reversal_status' => $document->reversal_status,
            'website_key' => $document->website_key,
            'source_module' => $document->source_module,
            'source_type' => $document->source_type,
            'source_id' => $document->source_id,
            'notes' => $document->notes,
            'metadata' => $document->metadata ?? [],
            'approved_at' => $document->approved_at?->toIso8601String(),
            'approved_by' => $document->approved_by,
            'posted_at' => $document->posted_at?->toIso8601String(),
            'posted_by' => $document->posted_by,
            'created_by' => $document->created_by,
            'voided_at' => $document->voided_at?->toIso8601String(),
            'voided_by' => $document->voided_by,
            'void_reason' => $document->void_reason,
            'lines' => $document->relationLoaded('lines')
                ? $document->lines->map(fn (AcctDocumentLine $line): array => self::line($line))->values()->all()
                : [],
            'payments' => $document->relationLoaded('payments')
                ? $document->payments->map(fn (AcctDocumentPayment $payment): array => self::payment($payment))->values()->all()
                : [],
        ];
    }

    public static function payment(AcctDocumentPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'document_id' => $payment->document_id,
            'kind' => $payment->kind,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'reference' => $payment->reference,
            'status' => $payment->status,
            'created_by' => $payment->created_by,
            'metadata' => $payment->metadata ?? [],
        ];
    }

    private static function source(AcctItemSource $source): array
    {
        return [
            'source_module' => $source->source_module,
            'source_type' => $source->source_type,
            'source_id' => $source->source_id,
            'source_key' => $source->source_key,
            'source_hash' => $source->source_hash,
            'synced_at' => $source->synced_at?->toIso8601String(),
            'sync_status' => $source->sync_status,
            'snapshot' => $source->snapshot ?? [],
        ];
    }

    private static function line(AcctDocumentLine $line): array
    {
        return [
            'id' => $line->id,
            'accounting_item_id' => $line->accounting_item_id,
            'line_type' => $line->line_type,
            'sort_order' => $line->sort_order,
            'item_kind' => $line->item_kind,
            'name' => $line->name,
            'sku' => $line->sku,
            'unit' => $line->unit,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'discount_amount' => (float) $line->discount_amount,
            'line_subtotal' => (float) $line->line_subtotal,
            'tax_category' => $line->tax_category,
            'tax_rate' => $line->tax_rate !== null ? (float) $line->tax_rate : null,
            'tax_base' => (float) $line->tax_base,
            'tax_amount' => (float) $line->tax_amount,
            'line_total' => (float) $line->line_total,
            'snapshot' => $line->snapshot ?? [],
        ];
    }
}
