<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbMenuItem extends FnbModel
{
    protected $table = 'fnb_menu_items';

    protected function casts(): array
    {
        return ['tax_rate_bps' => 'integer', 'tax_inclusive' => 'boolean', 'archived_at' => 'datetime', 'version' => 'integer'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(FnbItemVariant::class, 'item_id');
    }
}
