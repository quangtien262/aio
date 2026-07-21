<?php

namespace App\Models;

use App\Models\AdminRoleAssignment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'key', 'description', 'is_system', 'is_assignable', 'status'])]
class Role extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if ($role->key === 'super-admin' || ($role->exists && $role->getOriginal('key') === 'super-admin')) {
                $role->key = 'super-admin';
                $role->is_system = true;
                $role->is_assignable = false;
                $role->status = 'active';
            }
        });

        static::deleting(fn (Role $role): bool => $role->key !== 'super-admin' && ! $role->is_system);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_role_assignments')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AdminRoleAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_assignable' => 'boolean',
        ];
    }
}
