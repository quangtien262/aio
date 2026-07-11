<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'batch_id', 'warehouse_id', 'serial_number', 'status', 'received_at', 'issued_at', 'note'])]
class InvSerialNumber extends Model
{
    protected $table = 'inv_serial_numbers';

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InvItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvBatch::class, 'batch_id');
    }
}
