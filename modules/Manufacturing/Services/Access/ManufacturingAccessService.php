<?php

namespace Modules\Manufacturing\Services\Access;

use App\Core\Modules\ModuleRegistry;
use App\Models\Admin;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\ManufacturingData;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ManufacturingAccessService
{
    public const PERMISSIONS = ['manufacturing.view', 'manufacturing.settings', 'manufacturing.engineering', 'manufacturing.plan', 'manufacturing.execute', 'manufacturing.quality', 'manufacturing.fulfill', 'manufacturing.cost.view', 'manufacturing.cost.manage', 'manufacturing.documents', 'manufacturing.integrations'];

    public function actor(Admin $actor, bool $lock = false, bool $allowPasswordIdentity = false): Admin
    {
        $query = Admin::query()->whereKey($actor->id);
        $live = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($live && $live->isAvailable() && ($allowPasswordIdentity || ! $live->must_change_password) && (int) $live->auth_version === (int) $actor->auth_version, 403, 'Tài khoản hoặc phiên xác thực đã thay đổi.');

        return $live;
    }

    public function workspace(Admin $actor, bool $allowPasswordIdentity = false): void
    {
        $actor = $this->actor($actor, allowPasswordIdentity: $allowPasswordIdentity);
        $this->permission($actor, 'manufacturing.view');
        if ($actor->isSuperAdmin()) {
            return;
        }
        foreach ($this->allowedCompanyIds($actor) as $companyId) {
            try {
                $this->company($actor, (int) $companyId, 'manufacturing.view');

                return;
            } catch (HttpException $exception) {
                if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                    throw $exception;
                }
            }
        }
        abort(403, 'Không được cấp quyền xem doanh nghiệp sản xuất.');
    }

    public function permission(Admin $actor, string $permission): void
    {
        abort_unless(in_array($permission, self::PERMISSIONS, true) && $actor->canAccess($permission, 'global'), 403, 'Không đủ quyền sản xuất toàn doanh nghiệp.');
    }

    public function allowedCompanyIds(Admin $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return DB::table('mfg_companies')->where('status', 'active')->pluck('id')->all();
        }

        return $this->grants($actor)->distinct()->pluck('company_id')->all();
    }

    public function allowedFactoryIds(Admin $actor): array
    {
        $query = DB::table('mfg_factories')->where('status', 'active');
        if (! $actor->isSuperAdmin()) {
            $companyWide = $this->grants($actor)->whereNull('factory_id')->pluck('company_id');
            $factoryIds = $this->grants($actor)->whereNotNull('factory_id')->pluck('factory_id');
            $query->where(fn (Builder $q) => $q->whereIn('company_id', $companyWide)->orWhereIn('id', $factoryIds));
        }

        return $query->pluck('id')->all();
    }

    public function company(Admin $actor, int|string $id, string $permission = 'manufacturing.view', bool $lock = false): object
    {
        $this->permission($actor, $permission);
        app(ManufacturingRuntimeState::class)->guard(false);
        $query = DB::table('mfg_companies')->where(is_int($id) ? 'id' : 'public_id', $id)->where('status', 'active');
        $row = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($row, 404);
        $grants = $this->grants($actor)->where('company_id', $row->id);
        if ($permission !== 'manufacturing.view') {
            $grants->whereNull('factory_id');
        }
        abort_unless($actor->isSuperAdmin() || ($lock ? $grants->sharedLock() : $grants)->get()->contains(fn ($grant) => $this->grantAllows($grant, $permission)), 403, 'Không được cấp quyền doanh nghiệp sản xuất này.');

        return $row;
    }

    public function factory(Admin $actor, int|string $id, string $permission = 'manufacturing.view', bool $write = false): object
    {
        $this->permission($actor, $permission);
        app(ManufacturingRuntimeState::class)->guard($write);
        $query = DB::table('mfg_factories')->where(is_int($id) ? 'id' : 'public_id', $id)->where('status', 'active');
        $row = ($write ? $query->sharedLock() : $query)->first();
        abort_unless($row && DB::table('mfg_companies')->where('id', $row->company_id)->where('status', 'active')->exists(), 404);
        $grants = $this->grants($actor)->where('company_id', $row->company_id)->where(fn (Builder $q) => $q->whereNull('factory_id')->orWhere('factory_id', $row->id));
        abort_unless($actor->isSuperAdmin() || ($write ? $grants->sharedLock() : $grants)->get()->contains(fn ($grant) => $this->grantAllows($grant, $permission)), 403, 'Không được cấp quyền phân xưởng này.');

        return $row;
    }

    public function scope(Admin $actor, int|string $companyId, int|string|null $factoryId = null, string $permission = 'manufacturing.view'): array
    {
        if ($factoryId !== null) {
            $factory = $this->factory($actor, $factoryId, $permission);
            $company = DB::table('mfg_companies')->where('id', $factory->company_id)->first();
            abort_unless((is_int($companyId) ? $company->id : $company->public_id) == $companyId, 403, 'Phân xưởng không thuộc doanh nghiệp đã chọn.');
        } else {
            $company = $this->company($actor, $companyId, $permission);
            $factory = null;
        }

        return ['company' => $company, 'factory' => $factory, 'permission' => $permission];
    }

    public function assertCanDelegate(Admin $actor, object $company, ?object $factory, array $permissions): void
    {
        if ($factory) {
            $this->factory($actor, (int) $factory->id, 'manufacturing.settings');
        } else {
            $this->company($actor, (int) $company->id, 'manufacturing.settings');
        }
        foreach ($permissions as $permission) {
            $this->permission($actor, $permission);
            if ($factory) {
                $this->factory($actor, (int) $factory->id, $permission);
            } else {
                $this->company($actor, (int) $company->id, $permission);
            }
        }
    }

    public function permissions(Admin $actor): array
    {
        return array_values(array_filter(self::PERMISSIONS, fn ($p) => $actor->canAccess($p, 'global')));
    }

    public function bootstrap(Admin $actor, ?string $factoryId = null): array
    {
        $identity = ['id' => $actor->id, 'name' => $actor->name, 'username' => $actor->username, 'email' => $actor->email,
            'is_system_owner' => $actor->isSystemOwner(), 'is_super_admin' => $actor->isSuperAdmin(), 'can_pause' => false, 'company_permissions' => [], 'must_change_password' => (bool) $actor->must_change_password,
            'two_factor_enabled' => $actor->two_factor_confirmed_at !== null, 'is_active' => $actor->is_active, 'is_locked' => $actor->isLocked(),
            'workspace_kind' => 'enterprise', 'current_website' => null, 'site_options' => [], 'organization_options' => [],
            'site_profile' => ['site_name' => 'AIO Sản xuất', 'branding' => []], 'permissions' => [], 'navigation_permissions' => [], 'module_navigation' => [],
            'companies' => [], 'factories' => [], 'workshops' => [], 'lines' => [], 'shifts' => [], 'memberships' => [], 'current_factory_id' => null];
        if ($actor->must_change_password) {
            return $identity;
        }
        $actor = $this->actor($actor);
        $this->permission($actor, 'manufacturing.view');
        app(ManufacturingRuntimeState::class)->guard(false);
        $nativeGrants = $this->grants($actor)->get();
        $companyQuery = DB::table('mfg_companies')->where('status', 'active');
        if (! $actor->isSuperAdmin()) {
            $companyQuery->whereIn('id', $nativeGrants->pluck('company_id'));
        }
        $companies = $companyQuery->orderBy('name')->get();
        $factoryQuery = DB::table('mfg_factories')->where('status', 'active')->whereIn('company_id', $companies->pluck('id'));
        if (! $actor->isSuperAdmin()) {
            $companyWideIds = $nativeGrants->whereNull('factory_id')->pluck('company_id');
            $factoryIds = $nativeGrants->whereNotNull('factory_id')->pluck('factory_id');
            $factoryQuery->where(fn (Builder $q) => $q->whereIn('company_id', $companyWideIds)->orWhereIn('id', $factoryIds));
        }
        $factories = $factoryQuery->orderBy('name')->get();
        $current = $factoryId !== null ? $this->factory($actor, $factoryId) : $factories->first();
        $globalPermissions = $this->permissions($actor);
        $permissions = $globalPermissions;
        $memberships = $nativeGrants->whereIn('company_id', $companies->pluck('id')->all())->values();
        $companyPermissions = [];
        if ($current !== null) {
            $permissions = array_values(array_filter($permissions, function ($permission) use ($actor, $current): bool {
                try {
                    $this->factory($actor, (int) $current->id, $permission);

                    return true;
                } catch (HttpException $e) {
                    if ($e->getStatusCode() === 403) {
                        return false;
                    } throw $e;
                }
            }));
            $companyPermissions = array_values(array_filter($globalPermissions, function ($permission) use ($actor, $current): bool {
                try {
                    $this->company($actor, (int) $current->company_id, $permission);

                    return true;
                } catch (HttpException $e) {
                    if ($e->getStatusCode() === 403) {
                        return false;
                    } throw $e;
                }
            }));
            if (! in_array('manufacturing.engineering', $companyPermissions, true)) {
                $permissions = array_values(array_diff($permissions, ['manufacturing.engineering']));
            }
        } elseif (! $actor->isSuperAdmin()) {
            $permissions = array_values(array_filter($permissions, fn ($p) => $p === 'manufacturing.settings' || $p === 'manufacturing.view'));
        }
        // The shell may restore another saved factory after bootstrap. Menu
        // visibility is a union, while every business action keeps the selected
        // factory's permissions above. Native grants are read once, not per tab.
        $navigationPermissions = $actor->isSuperAdmin() ? $globalPermissions : [];
        if (! $actor->isSuperAdmin()) {
            foreach ($memberships as $grant) {
                $hasActiveFactory = $factories->contains(fn ($factory) => (int) $factory->company_id === (int) $grant->company_id
                    && ($grant->factory_id === null || (int) $factory->id === (int) $grant->factory_id));
                if (! $hasActiveFactory) {
                    continue;
                }
                foreach ($globalPermissions as $permission) {
                    if ($permission === 'manufacturing.engineering' && $grant->factory_id !== null) {
                        continue;
                    }
                    if ($this->grantAllows($grant, $permission)) {
                        $navigationPermissions[$permission] = $permission;
                    }
                }
            }
            $navigationPermissions = $factories->isEmpty() ? $permissions : array_values($navigationPermissions);
        }
        $companyKeys = $companies->pluck('public_id', 'id')->all();
        $factoryKeys = $factories->pluck('public_id', 'id')->all();
        $serialize = function ($row) use ($companyKeys, $factoryKeys): array {
            $data = ManufacturingData::resource($row);
            if (isset($data['company_id'])) {
                $data['company_id'] = $companyKeys[$data['company_id']] ?? null;
            }
            if (isset($data['factory_id'])) {
                $data['factory_id'] = $factoryKeys[$data['factory_id']] ?? null;
            }

            return $data;
        };
        $workshops = DB::table('mfg_workshops')->whereIn('factory_id', $factories->pluck('id'))->where('status', 'active')->get();
        $workshopKeys = $workshops->pluck('public_id', 'id')->all();
        $lines = DB::table('mfg_lines')->whereIn('factory_id', $factories->pluck('id'))->where('status', 'active')->get()->map(function ($row) use ($serialize, $workshopKeys): array {
            $data = $serialize($row);
            $data['workshop_id'] = $row->workshop_id ? ($workshopKeys[$row->workshop_id] ?? null) : null;

            return $data;
        })->all();

        return array_replace($identity, [
            'permissions' => $permissions, 'navigation_permissions' => $navigationPermissions, 'module_navigation' => app(ModuleRegistry::class)->navigationForPermissions($navigationPermissions),
            'company_permissions' => $companyPermissions, 'can_pause' => $actor->isSuperAdmin() && $actor->canAccess('manufacturing.settings', 'global'),
            'companies' => $companies->map($serialize)->all(), 'factories' => $factories->map($serialize)->all(), 'workshops' => $workshops->map($serialize)->all(), 'lines' => $lines,
            'shifts' => DB::table('mfg_shifts')->whereIn('factory_id', $factories->pluck('id'))->where('status', 'active')->get()->map($serialize)->all(),
            'memberships' => $memberships->map($serialize)->all(),
            'current_factory_id' => $current?->public_id, 'runtime' => app(ManufacturingRuntimeState::class)->state(),
        ]);
    }

    private function grants(Admin $actor): Builder
    {
        return DB::table('mfg_memberships')->where('admin_id', $actor->id)->where('status', 'active')->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    private function grantAllows(object $grant, string $permission): bool
    {
        $permissions = $grant->permissions !== null ? json_decode($grant->permissions, true, flags: JSON_THROW_ON_ERROR) : match ($grant->role) {
            'manager' => self::PERMISSIONS,
            'operator' => ['manufacturing.view', 'manufacturing.execute', 'manufacturing.quality', 'manufacturing.fulfill', 'manufacturing.documents'],
            default => ['manufacturing.view'],
        };

        return in_array($permission, $permissions, true);
    }
}
