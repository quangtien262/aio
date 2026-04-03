<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_id', 'role_id', 'scope_type', 'scope_value'])]
class AdminRoleScope extends Model
{
    use HasFactory;

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
