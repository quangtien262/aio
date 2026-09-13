<?php

namespace Modules\FnbPos\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbOutletStaff extends FnbModel
{
    protected $table = 'fnb_outlet_staff';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(FnbOutlet::class, 'outlet_id');
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(FnbOutletStaffTerminal::class, 'outlet_staff_id');
    }
}
