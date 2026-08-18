<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'party_id',
    'direction',
    'document_type',
    'document_no',
    'document_date',
    'due_date',
    'currency',
    'workflow_status',
    'payment_status',
    'legal_status',
    'inventory_status',
    'mail_status',
    'subtotal',
    'discount_total',
    'tax_total',
    'grand_total',
    'website_key',
    'source_module',
    'source_type',
    'source_id',
    'idempotency_key',
    'notes',
    'metadata',
    'seller_snapshot',
    'buyer_snapshot',
    'snapshot_hash',
    'tax_breakdown',
    'tax_period',
    'tax_eligibility',
    'base_currency',
    'exchange_rate',
    'base_subtotal',
    'base_discount_total',
    'base_tax_total',
    'base_grand_total',
    'paid_amount',
    'original_document_id',
    'correction_type',
    'effect_sign',
    'reversal_status',
    'created_by',
    'version',
    'request_fingerprint',
    'approved_at',
    'approved_by',
    'posted_at',
    'posted_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'reversed_at',
    'reversed_by',
])]
class AcctDocument extends Model
{
    use HasFactory;

    private static int $trustedMutationDepth = 0;

    protected $table = 'acct_documents';

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'base_subtotal' => 'decimal:2',
            'base_discount_total' => 'decimal:2',
            'base_tax_total' => 'decimal:2',
            'base_grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'seller_snapshot' => 'array',
            'buyer_snapshot' => 'array',
            'tax_breakdown' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(AcctParty::class, 'party_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AcctDocumentLine::class, 'document_id')->orderBy('sort_order')->orderBy('id');
    }

    public function originalDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_document_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'original_document_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AcctDocumentEvent::class, 'document_id')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AcctDocumentPayment::class, 'document_id')->orderBy('paid_at')->orderBy('id');
    }

    /** Persist a mutation already validated by the accounting domain service. */
    public function saveTrusted(array $options = []): bool
    {
        self::$trustedMutationDepth++;

        try {
            return $this->save($options);
        } finally {
            self::$trustedMutationDepth--;
        }
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->getOriginal('workflow_status') !== 'draft' && self::$trustedMutationDepth === 0) {
                throw new \LogicException('Non-draft accounting documents may only be changed by the domain service.');
            }
        });

        static::deleting(function (self $document): void {
            if ($document->workflow_status !== 'draft') {
                throw new \LogicException('Approved, posted, voided, and reversed accounting documents are retained.');
            }
        });
    }
}
