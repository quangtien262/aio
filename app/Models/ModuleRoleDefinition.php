<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'module_key', 'role_id', 'role_key', 'version', 'introduced_version',
    'retired_version', 'assignment_channel', 'permission_keys', 'definition_hash',
    'custom_role_policy', 'is_active',
])]
class ModuleRoleDefinition extends Model
{
    use HasFactory;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected function casts(): array
    {
        return [
            'permission_keys' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
