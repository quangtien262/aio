<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'inventory_warehouse_id',
    'is_default',
    'default_slot',
    'warehouse_snapshot',
    'created_by',
])]
class AcctInventoryWarehouseMapping extends Model
{
    protected $table = 'acct_inventory_warehouse_mappings';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'warehouse_snapshot' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $mapping): void {
            $mapping->default_slot = $mapping->is_default ? 'default' : null;
        });
    }
}
