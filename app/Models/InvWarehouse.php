<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'phone', 'email', 'address', 'description', 'is_default', 'is_active'])]
class InvWarehouse extends Model
{
    protected $table = 'inv_warehouses';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(InvLocation::class, 'warehouse_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InvStockBalance::class, 'warehouse_id');
    }
}
