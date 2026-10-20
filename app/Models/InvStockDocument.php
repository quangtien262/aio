<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'type', 'status', 'source_warehouse_id', 'destination_warehouse_id', 'reference', 'note', 'created_by_admin_id', 'posted_at'])]
class InvStockDocument extends Model
{
    protected $table = 'inv_stock_documents';

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            if (! Schema::hasColumn('inv_warehouses', 'v2_managed')) {
                return;
            }
            $warehouseIds = array_filter([$document->source_warehouse_id, $document->destination_warehouse_id]);
            if (DB::table('inv_warehouses')->whereIn('id', $warehouseIds)->where('v2_managed', true)->exists()) {
                throw ValidationException::withMessages(['warehouse' => 'Kho Retail phải dùng Inventory v2 để giữ reservation và quota nhất quán.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'destination_warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvStockDocumentLine::class, 'document_id');
    }
}
