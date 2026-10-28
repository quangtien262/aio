<?php

namespace Modules\Pharmacy\Services\Access;

use App\Models\Admin;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Runtime\PharmacyRuntimeState;

final class PharmacyAccessService
{
    public const PERMISSIONS = ['pharmacy.view', 'pharmacy.settings', 'pharmacy.professional.manage', 'pharmacy.product.manage', 'pharmacy.price.manage', 'pharmacy.patient.view', 'pharmacy.patient.manage', 'pharmacy.patient.sensitive', 'pharmacy.prescription.view', 'pharmacy.prescription.manage', 'pharmacy.prescription.transfer', 'pharmacy.dispensing.view', 'pharmacy.dispensing.manage', 'pharmacy.dispensing.review', 'pharmacy.dispensing.handover', 'pharmacy.sale.create', 'pharmacy.sale.view', 'pharmacy.payment.collect', 'pharmacy.shift.manage', 'pharmacy.return.manage', 'pharmacy.inventory.view', 'pharmacy.inventory.manage', 'pharmacy.receipt.manage', 'pharmacy.purchasing.manage', 'pharmacy.quality.manage', 'pharmacy.recall.manage', 'pharmacy.transfer.manage', 'pharmacy.stocktake.manage', 'pharmacy.report.view', 'pharmacy.cost.view', 'pharmacy.integrations.manage', 'pharmacy.documents.manage', 'pharmacy.export', 'pharmacy.controlled.manage', 'pharmacy.online.manage', 'pharmacy.knowledge.manage'];

    public function actor(Admin $actor, bool $lock = false, bool $allowPasswordIdentity = false): Admin
    {
        $query = Admin::query()->whereKey($actor->id);
        $live = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($live && $live->isAvailable() && ($allowPasswordIdentity || ! $live->must_change_password) && (int) $live->auth_version === (int) $actor->auth_version, 403, 'Tài khoản hoặc phiên xác thực đã thay đổi.');

        return $live;
    }

    public function permission(Admin $actor, string $permission): void
    {
        abort_unless(in_array($permission, self::PERMISSIONS, true) && $actor->canAccess($permission, 'global'), 403, 'Không đủ quyền Nhà thuốc toàn doanh nghiệp.');
    }

    public function permissions(Admin $actor): array
    {
        return array_values(array_filter(self::PERMISSIONS, fn ($p) => $actor->canAccess($p, 'global')));
    }

    public function workspace(Admin $actor, bool $allowPasswordIdentity = false): void
    {
        $actor = $this->actor($actor, allowPasswordIdentity: $allowPasswordIdentity);
        $this->permission($actor, 'pharmacy.view');
        app(PharmacyRuntimeState::class)->guard(false);
        abort_unless($actor->isSuperAdmin() || $this->grants($actor)->exists(), 403, 'Chưa được phân công nhà thuốc.');
    }

    public function chain(Admin $actor, int|string $id, string $permission = 'pharmacy.view', bool $lock = false): object
    {
        $actor = $this->actor($actor, $lock);
        $this->permission($actor, $permission);
        app(PharmacyRuntimeState::class)->guard($lock);
        $query = DB::table('ph_chains')->where(is_int($id) ? 'id' : 'public_id', $id)->where('status', 'active');
        $chain = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($chain, 404);
        $grants = $this->grants($actor)->where('chain_id', $chain->id);
        if ($permission !== 'pharmacy.view') {
            $grants->whereNull('pharmacy_id');
        }
        abort_unless($actor->isSuperAdmin() || ($lock ? $grants->sharedLock() : $grants)->get()->contains(fn ($r) => $this->allows($r, $permission)), 403, 'Không có quyền với chuỗi nhà thuốc.');

        return $chain;
    }

    public function pharmacy(Admin $actor, int|string $id, string $permission = 'pharmacy.view', bool $lock = false): object
    {
        $actor = $this->actor($actor, $lock);
        $this->permission($actor, $permission);
        app(PharmacyRuntimeState::class)->guard($lock);
        $query = DB::table('ph_pharmacies')->where(is_int($id) ? 'id' : 'public_id', $id)->where('status', 'active');
        $pharmacy = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($pharmacy, 404);
        $chainQuery = DB::table('ph_chains')->where('id', $pharmacy->chain_id)->where('status', 'active');
        abort_unless(($lock ? $chainQuery->sharedLock() : $chainQuery)->exists(), 404, 'Chuỗi nhà thuốc không hoạt động.');
        $grants = $this->grants($actor)->where('chain_id', $pharmacy->chain_id)->where(fn ($q) => $q->whereNull('pharmacy_id')->orWhere('pharmacy_id', $pharmacy->id));
        abort_unless($actor->isSuperAdmin() || ($lock ? $grants->sharedLock() : $grants)->get()->contains(fn ($r) => $this->allows($r, $permission)), 403, 'Không được thao tác nhà thuốc này.');

        return $pharmacy;
    }

    public function professional(Admin $actor, object $pharmacy, string $ability = 'review', bool $lock = false): object
    {
        $this->actor($actor, $lock);
        $q = DB::table('ph_credentials')->where('admin_id', $actor->id)->where('chain_id', $pharmacy->chain_id)->where('status', 'active')
            ->whereNotNull('verified_at')->whereNotNull('verified_by')->where('valid_from', '<=', now())->where('valid_until', '>', now())
            ->where(fn ($q) => $q->whereNull('pharmacy_id')->orWhere('pharmacy_id', $pharmacy->id));
        $credential = ($lock ? $q->sharedLock() : $q)->get()->first(fn ($c) => in_array($ability, D::json($c->abilities), true));
        abort_unless($credential, 403, 'Cần người có hồ sơ chuyên môn đã xác minh, còn hiệu lực và đúng phạm vi.');

        return $credential;
    }

    public function allowedPharmacyIds(Admin $actor, string $permission = 'pharmacy.view'): array
    {
        if (! $actor->canAccess($permission, 'global')) {
            return [];
        }
        $query = DB::table('ph_pharmacies')->where('status', 'active')->whereIn('chain_id', DB::table('ph_chains')->where('status', 'active')->select('id'));
        if ($actor->isSuperAdmin()) {
            return $query->pluck('id')->all();
        }
        $grants = $this->grants($actor)->get()->filter(fn ($grant) => $this->allows($grant, $permission));
        $chains = $grants->whereNull('pharmacy_id')->pluck('chain_id');
        $ids = $grants->whereNotNull('pharmacy_id')->pluck('pharmacy_id');

        return $query->where(fn ($q) => $q->whereIn('chain_id', $chains)->orWhereIn('id', $ids))->pluck('id')->all();
    }

    public function allowedChainIds(Admin $actor): array
    {
        return $actor->isSuperAdmin() ? DB::table('ph_chains')->where('status', 'active')->pluck('id')->all() : $this->grants($actor)->distinct()->pluck('chain_id')->all();
    }

    /** Request-local projection for navigation; command authorization always reads live grants. */
    public function permissionProjection(Admin $actor, array $pharmacies): array
    {
        $global = $this->permissions($actor);
        $grants = $this->grants($actor)->get();
        $scopes = [];
        $navigation = [];
        foreach ($pharmacies as $pharmacy) {
            $native = [];
            foreach ($grants as $grant) {
                if ((int) $grant->chain_id === (int) $pharmacy->chain_id && ($grant->pharmacy_id === null || (int) $grant->pharmacy_id === (int) $pharmacy->id)) {
                    $native = array_merge($native, D::json($grant->permissions));
                }
            }
            $scopes[$pharmacy->id] = $actor->isSuperAdmin() ? $global : array_values(array_intersect($global, array_unique($native)));
            $navigation = array_merge($navigation, $scopes[$pharmacy->id]);
        }

        return ['global' => $global, 'scopes' => $scopes, 'navigation' => $actor->isSuperAdmin() ? $global : array_values(array_unique($navigation))];
    }

    private function grants(Admin $actor): Builder
    {
        return DB::table('ph_memberships')->where('admin_id', $actor->id)->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    private function allows(object $grant, string $permission): bool
    {
        return in_array($permission, D::json($grant->permissions), true);
    }
}
