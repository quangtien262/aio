<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'kind',
    'name',
    'sku',
    'unit',
    'default_price',
    'tax_rate',
    'tax_category',
    'revenue_account',
    'expense_account',
    'is_stock_tracked',
    'status',
    'metadata',
])]
class AcctItem extends Model
{
    use HasFactory;

    protected $table = 'acct_items';

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_stock_tracked' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(AcctItemSource::class, 'accounting_item_id');
    }
}
