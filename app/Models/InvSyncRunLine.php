<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sync_run_id', 'catalog_product_id', 'item_id', 'sku', 'name', 'action', 'message', 'payload'])]
class InvSyncRunLine extends Model
{
    protected $table = 'inv_sync_run_lines';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
