<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\Admin;
use App\Models\AdminReauthProof;
use App\Support\Totp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class FnbReauthService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function __construct(
        private readonly Totp $totp,
        private readonly FnbOutletAccessService $access,
        private readonly FnbSecurityPolicyRegistry $policies,
        private readonly FnbAuditLogger $audit,
        private readonly FnbSessionBinding $sessions,
    ) {}

    public function issue(
        Admin $actor,
        string $sessionId,
        ?string $ip,
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectId,
        string $payloadHash,
        string $password,
        ?string $twoFactorCode = null,
        int $ttlSeconds = 300,
    ): IssuedReauthProof {
        $actor = $actor->fresh();
        if (! $actor instanceof Admin || ! $actor->isAvailable()) {
            throw new AuthorizationException('Tài khoản quản trị không còn hoạt động.');
        }

        $this->validateBinding($websiteKey, $outletId, $action, $subjectId, $payloadHash);
        $this->authorizeAction($actor, $websiteKey, $outletId, $action, $subjectId, $payloadHash);

        $sessionKey = $this->rateKey('session', $actor->id, $sessionId);
        $ipKey = $this->rateKey('ip', $actor->id, (string) $ip);
        $this->assertNotRateLimited($actor, $websiteKey, $outletId, $sessionKey, $ipKey);

        $factorSet = ['password'];
        $valid = Hash::check($password, (string) $actor->password);
        if ($actor->two_factor_confirmed_at !== null) {
            $valid = $valid
                && filled($actor->two_factor_secret)
                && $this->totp->verify((string) $actor->two_factor_secret, (string) $twoFactorCode);
            $factorSet[] = 'totp';
        }

        if (! $valid) {
            RateLimiter::hit($sessionKey, self::DECAY_SECONDS);
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);
            $this->assertNotRateLimited($actor, $websiteKey, $outletId, $sessionKey, $ipKey);

            throw ValidationException::withMessages([
                'credential' => ['Không thể xác thực lại với thông tin đã cung cấp.'],
            ]);
        }

        RateLimiter::clear($sessionKey);
        RateLimiter::clear($ipKey);

        $configuredTtl = max(1, min(600, (int) config('fnb.security.reauth_ttl_seconds', 300)));
        $ttl = max(1, min($ttlSeconds, $configuredTtl));
        $plainToken = $this->token();
        $expiresAt = now()->addSeconds($ttl);

        AdminReauthProof::query()->create([
            'admin_id' => $actor->id,
            'module_key' => 'fnb-pos',
            'token_hash' => $this->tokenHash($plainToken),
            'nonce' => (string) Str::uuid(),
            'session_id_hash' => $this->sessions->hash($sessionId),
            'auth_version' => (int) $actor->auth_version,
            'factor_set' => $factorSet,
            'scope_type' => $outletId === 0 ? 'website' : 'website_outlet',
            'scope_value' => $this->scopeValue($websiteKey, $outletId),
            'action' => $action,
            'subject_type' => 'resource',
            'subject_id' => $subjectId,
            'payload_hash' => strtolower($payloadHash),
            'ip_hash' => filled($ip) ? $this->valueHash((string) $ip) : null,
            'expires_at' => $expiresAt,
        ]);

        return new IssuedReauthProof($plainToken, $expiresAt);
    }

    public function consume(
        string $opaqueProof,
        Admin $actor,
        string $sessionId,
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectId,
        string $payloadHash,
    ): AdminReauthProof {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('A re-auth proof must be consumed inside the protected mutation transaction.');
        }

        $this->validateBinding($websiteKey, $outletId, $action, $subjectId, $payloadHash);
        $actor = Admin::query()->whereKey($actor->id)->lockForUpdate()->first();
        if (! $actor instanceof Admin || ! $actor->isAvailable()) {
            throw new AuthorizationException('Tài khoản quản trị không còn hoạt động.');
        }
        $proof = AdminReauthProof::query()
            ->where('token_hash', $this->tokenHash($opaqueProof))
            ->lockForUpdate()
            ->first();

        if ($proof === null
            || (int) $proof->admin_id !== (int) $actor->id
            || $proof->module_key !== 'fnb-pos'
            || ! hash_equals((string) $proof->session_id_hash, $this->sessions->hash($sessionId))
            || ! hash_equals((string) $proof->scope_type, $outletId === 0 ? 'website' : 'website_outlet')
            || ! hash_equals((string) $proof->scope_value, $this->scopeValue($websiteKey, $outletId))
            || ! hash_equals((string) $proof->action, $action)
            || ! hash_equals((string) $proof->subject_id, $subjectId)
            || ! hash_equals((string) $proof->payload_hash, strtolower($payloadHash))) {
            throw new AuthorizationException('Re-auth proof không hợp lệ cho thao tác này.');
        }

        if ($proof->consumed_at !== null) {
            throw new ConflictHttpException('Re-auth proof đã được sử dụng.');
        }
        if ($proof->revoked_at !== null || $proof->expires_at->isPast()) {
            throw new AuthorizationException('Re-auth proof đã hết hạn hoặc bị thu hồi.');
        }

        if ((int) $proof->auth_version !== (int) $actor->auth_version) {
            throw new AuthorizationException('Quyền hoặc phiên xác thực đã thay đổi.');
        }

        $this->authorizeAction($actor, $websiteKey, $outletId, $action, $subjectId, $payloadHash);

        $updated = AdminReauthProof::query()
            ->whereKey($proof->id)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new ConflictHttpException('Re-auth proof đã được sử dụng.');
        }

        return $proof->fresh();
    }

    private function authorizeAction(
        Admin $actor,
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectId,
        string $payloadHash,
    ): void {
        if (in_array($action, ['approval.approve', 'approval.reject'], true)) {
            $approval = DB::table('fnb_approvals')
                ->where('website_key', $websiteKey)
                ->where('outlet_id', $outletId)
                ->where(fn ($query) => $query->where('public_id', $subjectId)->orWhere('id', $subjectId))
                ->first();
            if ($approval === null || ! hash_equals((string) $approval->payload_hash, strtolower($payloadHash))) {
                throw new AuthorizationException('Approval scope không hợp lệ.');
            }
            $permissions = json_decode((string) $approval->required_permissions, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $policy = $this->policies->forAction($action);
            if (! $policy['reauth']) {
                throw new AuthorizationException('Thao tác này không phát hành re-auth proof.');
            }
            $permissions = $policy['executor'];
        }

        foreach ($permissions as $permission) {
            if ($outletId === 0) {
                if (! $this->access->canBootstrapWebsite($actor, $websiteKey, $permission)) {
                    throw new AuthorizationException('Không có quyền F&B tại website hiện tại.');
                }
            } else {
                $this->access->authorize($actor, $websiteKey, $outletId, $permission);
            }
        }
    }

    private function validateBinding(
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectId,
        string $payloadHash,
    ): void {
        if ($websiteKey === '' || ! preg_match('/\A[a-f0-9]{64}\z/i', $payloadHash)) {
            throw ValidationException::withMessages(['payload_hash' => ['Payload hash phải là SHA-256 hợp lệ.']]);
        }
        if ($outletId === 0 && ! ($action === 'onboard' && $subjectId === 'new')) {
            throw new AuthorizationException('Pre-outlet scope chỉ dành cho onboarding mới.');
        }
        if ($outletId < 0 || $subjectId === '') {
            throw new AuthorizationException('Re-auth scope không hợp lệ.');
        }
    }

    private function assertNotRateLimited(
        Admin $actor,
        string $websiteKey,
        int $outletId,
        string $sessionKey,
        string $ipKey,
    ): void {
        if (! RateLimiter::tooManyAttempts($sessionKey, self::MAX_ATTEMPTS)
            && ! RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS)) {
            return;
        }

        $retryAfter = max(RateLimiter::availableIn($sessionKey), RateLimiter::availableIn($ipKey));
        $this->audit->record(
            'fnb.security.reauth_locked',
            $websiteKey,
            $actor,
            $actor,
            after: ['retry_after_seconds' => $retryAfter],
            outletIds: $outletId > 0 ? [$outletId] : [],
        );

        throw new TooManyRequestsHttpException($retryAfter, 'Tạm khóa re-auth vì có quá nhiều lần thất bại.');
    }

    private function rateKey(string $dimension, int $adminId, string $value): string
    {
        return 'fnb:reauth:'.$dimension.':'.$adminId.':'.$this->valueHash($value);
    }

    private function valueHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function scopeValue(string $websiteKey, int $outletId): string
    {
        return json_encode(
            ['website_key' => $websiteKey, 'outlet_id' => $outletId],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
