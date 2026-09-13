<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FnbItemVariant extends FnbModel
{
    protected $table = 'fnb_item_variants';

    protected function casts(): array
    {
        return ['base_price_minor' => 'integer', 'is_default' => 'boolean', 'sort_order' => 'integer', 'version' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FnbMenuItem::class, 'item_id');
    }
}
