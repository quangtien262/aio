<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'username', 'email', 'password', 'is_active', 'status', 'is_system_owner',
    'must_change_password', 'auth_version', 'password_changed_at', 'locked_at',
    'locked_reason', 'last_login_at', 'last_login_ip', 'two_factor_secret',
    'two_factor_recovery_codes', 'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable;

    public const SYSTEM_OWNER_ID = 1;

    protected static function booted(): void
    {
        static::saving(function (Admin $admin): void {
            if ((int) $admin->getKey() === self::SYSTEM_OWNER_ID) {
                $admin->is_system_owner = true;
                $admin->is_active = true;
                $admin->status = 'active';
                $admin->locked_at = null;
                $admin->locked_reason = null;
            }
        });

        static::deleting(fn (Admin $admin): bool => (int) $admin->getKey() !== self::SYSTEM_OWNER_ID);
    }

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
            'is_system_owner' => 'boolean',
            'must_change_password' => 'boolean',
            'auth_version' => 'integer',
            'password_changed_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role_assignments')
            ->withPivot(['id', 'scope_type', 'scope_value', 'assigned_by', 'expires_at'])
            ->withTimestamps();
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(AdminRoleAssignment::class);
    }

    public function permissions(?string $websiteKey = null): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()
                ->where('is_active', true)
                ->orderBy('key')
                ->pluck('key')
                ->all();
        }

        $roleIds = $this->effectiveRoleIds($websiteKey);

        return Permission::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->orderBy('key')
            ->pluck('key')
            ->values()
            ->all();
    }

    public function hasPermission(string $permission, ?string $websiteKey = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return Permission::query()
            ->where('key', $permission)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->effectiveRoleIds($websiteKey)))
            ->exists();
    }

    public function scopeMatrix(): array
    {
        return $this->roleAssignments()
            ->get(['role_id', 'scope_type', 'scope_value'])
            ->groupBy('scope_type')
            ->map(fn ($items): array => $items->pluck('scope_value')->filter()->unique()->values()->all())
            ->all();
    }

    public function canAccessWebsite(string $websiteKey): bool
    {
        return $this->isSystemOwner() || $this->roleAssignments()
            ->where(fn ($query) => $query
                ->where('scope_type', 'global')
                ->orWhere(fn ($websiteQuery) => $websiteQuery
                    ->where('scope_type', 'website')
                    ->where('scope_value', $websiteKey)))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function canAccess(string $permission, ?string $scopeType = null, ?string $scopeValue = null): bool
    {
        if (! $this->hasPermission($permission, $scopeType === 'website' ? $scopeValue : null)) {
            return false;
        }

        if ($scopeType === null || $scopeType === 'global') {
            return true;
        }

        return $scopeType === 'website' && filled($scopeValue) && $this->canAccessWebsite($scopeValue);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isSystemOwner(): bool
    {
        return (int) $this->getKey() === self::SYSTEM_OWNER_ID || (bool) $this->is_system_owner;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSystemOwner() || $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query
                ->where('key', 'super-admin')
                ->where('is_system', true)
                ->where('status', 'active'))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === 'active' && ! $this->isLocked();
    }

    /**
     * @return array<int, int>
     */
    private function effectiveRoleIds(?string $websiteKey = null): array
    {
        $websiteKey = $websiteKey ?: (app()->bound(SiteContext::class) ? app(SiteContext::class)->websiteKey() : null);

        return $this->roleAssignments()
            ->where(fn ($query) => $query
                ->where('scope_type', 'global')
                ->when($websiteKey, fn ($scopeQuery) => $scopeQuery->orWhere(fn ($websiteQuery) => $websiteQuery
                    ->where('scope_type', 'website')
                    ->where('scope_value', $websiteKey))))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('role_id')
            ->unique()
            ->values()
            ->all();
    }
}
