<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'key', 'description', 'is_system', 'is_assignable', 'status'])]
class Role extends Model
{
    use HasFactory;

    public const PLATFORM_OWNER_KEY = 'platform-owner';

    public const SUPER_ADMIN_KEY = 'super-admin';

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if ($role->key === self::SUPER_ADMIN_KEY || ($role->exists && $role->getOriginal('key') === self::SUPER_ADMIN_KEY)) {
                $role->key = self::SUPER_ADMIN_KEY;
                $role->is_system = true;
                $role->is_assignable = false;
                $role->status = 'active';
            }

            if ($role->key === self::PLATFORM_OWNER_KEY || ($role->exists && $role->getOriginal('key') === self::PLATFORM_OWNER_KEY)) {
                $role->key = self::PLATFORM_OWNER_KEY;
                $role->is_system = false;
                $role->is_assignable = true;
                $role->status = 'active';
            }
        });

        static::deleting(fn (Role $role): bool => ! in_array($role->key, [self::SUPER_ADMIN_KEY, self::PLATFORM_OWNER_KEY], true)
            && ! $role->is_system);
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
