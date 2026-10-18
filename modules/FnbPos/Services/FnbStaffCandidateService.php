<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbSessionBinding;

class FnbStaffCandidateService
{
    public function __construct(
        private readonly FnbOutletAccessService $access,
        private readonly FnbSessionBinding $sessions,
    ) {}

    public function resolve(Admin $actor, FnbContext $ctx, string $sessionId, string $identifier, string $ip): array
    {
        $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, 'fnb.staff.assign');
        $identifier = mb_strtolower(trim($identifier));
        validator(['identifier' => $identifier], ['identifier' => ['required', 'string', 'min:3', 'max:254']])->validate();
        $identifierHash = $this->hash($identifier);
        foreach (['actor:'.$actor->id.':'.$ip, 'identifier:'.$identifierHash] as $limitKey) {
            $limitKey = 'fnb:staff-resolve:'.$this->hash($limitKey);
            abort_if(RateLimiter::tooManyAttempts($limitKey, 5), 429, 'Vui lòng thử lại sau một phút.');
            RateLimiter::hit($limitKey, 60);
        }
        $target = Admin::query()->where(function ($query) use ($identifier): void {
            $query->whereRaw('LOWER(email) = ?', [$identifier])->orWhereRaw('LOWER(username) = ?', [$identifier]);
        })->first();
        if (! $target || ! $target->isAvailable() || $this->protected($target) || $this->hqProtected($actor, $ctx, $target)) {
            return ['candidate' => null, 'message' => 'Không tìm thấy tài khoản có thể phân công.'];
        }
        $token = bin2hex(random_bytes(32));
        $expires = now()->addMinutes(2);
        DB::table('fnb_staff_candidate_grants')->insert([
            'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId,
            'requester_admin_id' => $actor->id, 'request_session_hash' => $this->sessions->hash($sessionId),
            'target_admin_id' => $target->id, 'identifier_hmac' => $identifierHash,
            'token_hash' => hash('sha256', $token), 'nonce' => (string) Str::uuid(),
            'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $membership = DB::table('fnb_outlet_staff')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('admin_id', $target->id)->first();
        $binding = $this->access->chainScopesEnabled()
            ? DB::table('fnb_staff_role_scopes')->where('website_key', $ctx->websiteKey)->where('scope_type', 'outlet')->where('scope_key', $ctx->outletId)->where('admin_id', $target->id)->first()
            : DB::table('fnb_staff_role_bindings')->where('website_key', $ctx->websiteKey)->where('admin_id', $target->id)->first();

        return ['candidate' => [
            'role_key' => $binding === null ? null : Role::whereKey($binding->preset_role_id)->value('key'),
            'terminal_ids' => $membership === null ? [] : DB::table('fnb_outlet_staff_terminals')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('outlet_staff_id', $membership->id)->pluck('terminal_id')->map(fn ($id) => (int) $id)->all(),
            'expected_binding_version' => $binding === null ? null : (int) $binding->version,
            'expected_membership_version' => $membership === null ? null : (int) $membership->version,
            'display_name' => $target->name,
            'masked_identifier' => mb_substr($identifier, 0, 2).'***',
            'candidate_token' => $token, 'expires_at' => $expires->toIso8601String(),
        ]];
    }

    public function consume(Admin $actor, FnbContext $ctx, string $sessionId, string $token): Admin
    {
        abort_unless(DB::transactionLevel() > 0, 500);
        $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, 'fnb.staff.assign');
        $grant = DB::table('fnb_staff_candidate_grants')->where('website_key', $ctx->websiteKey)
            ->where('outlet_id', $ctx->outletId)->where('requester_admin_id', $actor->id)
            ->where('token_hash', hash('sha256', $token))->where('request_session_hash', $this->sessions->hash($sessionId))
            ->whereNull('consumed_at')->where('expires_at', '>', now())->lockForUpdate()->first();
        abort_unless($grant, 403, 'Mã phân công không hợp lệ hoặc đã hết hạn.');
        $target = Admin::query()->lockForUpdate()->find($grant->target_admin_id);
        abort_unless($target && $target->isAvailable() && ! $this->protected($target) && ! $this->hqProtected($actor, $ctx, $target), 403);
        DB::table('fnb_staff_candidate_grants')->where('id', $grant->id)->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);

        return $target;
    }

    private function hqProtected(Admin $actor, FnbContext $ctx, Admin $target): bool
    {
        return $this->access->chainScopesEnabled()
            && DB::table('fnb_staff_role_scopes')->where('website_key', $ctx->websiteKey)->where('admin_id', $target->id)->where('scope_type', 'hq')->where('status', 'active')->exists()
            && ! in_array('fnb.chain.staff.manage', $this->access->hqPermissions($actor, $ctx->websiteKey), true);
    }

    private function protected(Admin $admin): bool
    {
        return $admin->isSuperAdmin() || $admin->roleAssignments()->whereHas('role', fn ($q) => $q->whereIn('key', ['super-admin', 'platform-owner']))->exists();
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
