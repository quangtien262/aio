<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'accounting_item_id',
    'line_type',
    'sort_order',
    'item_kind',
    'name',
    'sku',
    'unit',
    'quantity',
    'unit_price',
    'discount_amount',
    'tax_category',
    'line_subtotal',
    'tax_rate',
    'tax_base',
    'tax_amount',
    'line_total',
    'snapshot',
])]
class AcctDocumentLine extends Model
{
    use HasFactory;

    protected $table = 'acct_document_lines';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_base' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AcctItem::class, 'accounting_item_id');
    }

    protected static function booted(): void
    {
        $assertDraft = function (self $line): void {
            $document = AcctDocument::query()->find($line->document_id);

            if ($document !== null && $document->workflow_status !== 'draft') {
                throw new \LogicException('Lines of a non-draft accounting document are immutable.');
            }
        };

        static::creating($assertDraft);
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }
}
