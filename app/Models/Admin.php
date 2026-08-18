<?php

namespace App\Models;

use App\Support\SiteContext;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(HrmEmployee::class, 'admin_id');
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

    /** Permissions exposed to the admin UI across every currently assigned scope. */
    public function visiblePermissions(?string $websiteKey = null): array
    {
        if ($this->isSuperAdmin()) {
            return $this->permissions($websiteKey);
        }

        $organizationPermissions = Permission::query()
            ->where('is_active', true)
            ->whereIn('module_key', config('aio.organization_scope_module_keys', []))
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->activeScopedRoleIds('organization')))
            ->pluck('key');

        return collect($this->permissions($websiteKey))
            ->merge($organizationPermissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function scopeMatrix(): array
    {
        return $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['role_id', 'scope_type', 'scope_value'])
            ->groupBy('scope_type')
            ->map(fn ($items): array => $items->pluck('scope_value')->filter()->unique()->values()->all())
            ->all();
    }

    public function canAccessWebsite(string $websiteKey): bool
    {
        return $this->hasGlobalAssignmentScope() || $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where('scope_type', 'website')
            ->where('scope_value', $websiteKey)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function hasGlobalAssignmentScope(): bool
    {
        return $this->isSystemOwner() || $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where('scope_type', 'global')
            ->whereNull('scope_value')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function hasOrganizationAssignmentScope(): bool
    {
        return $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where('scope_type', 'organization')
            ->whereNotNull('scope_value')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function canAccessOrganization(string|int $organizationId): bool
    {
        if ($this->hasGlobalAssignmentScope()) {
            return true;
        }

        return $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where('scope_type', 'organization')
            ->where('scope_value', (string) $organizationId)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    /**
     * Return the organization ids available for a permission. A null result
     * means the permission is granted globally and therefore is not filtered.
     *
     * @return list<int>|null
     */
    public function organizationIdsForPermission(string $permission): ?array
    {
        if ($this->hasGlobalPermission($permission)) {
            return null;
        }

        $roleIds = Permission::query()
            ->where('key', $permission)
            ->where('is_active', true)
            ->first()?->roles()
            ->pluck('roles.id')
            ->all() ?? [];

        return $this->roleAssignments()
            ->whereIn('role_id', $roleIds)
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where('scope_type', 'organization')
            ->whereNotNull('scope_value')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('scope_value')
            ->filter(fn (mixed $value): bool => ctype_digit((string) $value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    public function canAccess(string $permission, ?string $scopeType = null, ?string $scopeValue = null): bool
    {
        if ($scopeType === 'global') {
            return $this->hasGlobalPermission($permission);
        }

        if ($scopeType === 'organization') {
            if ($this->hasGlobalPermission($permission)) {
                return true;
            }

            $assignments = $this->roleAssignments()
                ->whereHas('role', fn ($query) => $query->where('status', 'active'))
                ->where('scope_type', 'organization')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

            if ($scopeValue !== '*') {
                if (blank($scopeValue)) {
                    return false;
                }

                $assignments->where('scope_value', (string) $scopeValue);
            }

            $roleIds = $assignments->pluck('role_id');

            return Permission::query()
                ->where('key', $permission)
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
                ->exists();
        }

        if (! $this->hasPermission($permission, $scopeType === 'website' ? $scopeValue : null)) {
            return false;
        }

        if ($scopeType === null) {
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
            ->where('scope_type', 'global')
            ->whereNull('scope_value')
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
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
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

    private function hasGlobalPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $roleIds = $this->roleAssignments()
            ->where('scope_type', 'global')
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('role_id');

        return Permission::query()
            ->where('key', $permission)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->exists();
    }

    /** @return array<int, int> */
    private function activeScopedRoleIds(string $scopeType): array
    {
        return $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->where('scope_type', $scopeType)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('role_id')
            ->unique()
            ->values()
            ->all();
    }
}
