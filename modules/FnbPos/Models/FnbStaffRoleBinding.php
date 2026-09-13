<?php

namespace Modules\FnbPos\Models;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FnbStaffRoleBinding extends FnbModel
{
    protected $table = 'fnb_staff_role_bindings';

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'replaced_at' => 'datetime',
            'revoked_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function presetRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'preset_role_id');
    }

    public function coreAssignment(): BelongsTo
    {
        return $this->belongsTo(AdminRoleAssignment::class, 'core_assignment_id');
    }
}
