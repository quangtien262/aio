<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use App\Models\AdminRoleScope;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active', 'locked_at', 'locked_reason', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable;

    protected static function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function roleScopes(): HasMany
    {
        return $this->hasMany(AdminRoleScope::class);
    }

    public function permissions(): array
    {
        return $this->roles()
            ->with('permissions:id,key')
            ->get()
            ->flatMap(fn (Role $role): array => $role->permissions->pluck('key')->all())
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function scopeMatrix(): array
    {
        return $this->roleScopes()
            ->get(['role_id', 'scope_type', 'scope_value'])
            ->groupBy('scope_type')
            ->map(fn ($items): array => $items->pluck('scope_value')->unique()->values()->all())
            ->all();
    }

    public function hasScope(string $scopeType, ?string $scopeValue = null): bool
    {
        $query = $this->roleScopes()->where('scope_type', $scopeType);

        if ($scopeValue !== null) {
            $query->where('scope_value', $scopeValue);
        }

        return $query->exists();
    }

    public function canAccess(string $permission, ?string $scopeType = null, ?string $scopeValue = null): bool
    {
        if (! $this->hasPermission($permission)) {
            return false;
        }

        if ($scopeType === null) {
            return true;
        }

        return $this->hasScope($scopeType, $scopeValue);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
