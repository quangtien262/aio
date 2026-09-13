<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbOutlet extends FnbModel
{
    protected $table = 'fnb_outlets';

    protected function casts(): array
    {
        return ['settings' => 'array', 'version' => 'integer'];
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(FnbTerminal::class, 'outlet_id');
    }

    public function stations(): HasMany
    {
        return $this->hasMany(FnbPrepStation::class, 'outlet_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(FnbPaymentMethod::class, 'outlet_id');
    }
}
