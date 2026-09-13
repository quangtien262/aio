<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Models\FnbApproval;
use Modules\FnbPos\Models\FnbApprovalEvent;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class FnbApprovalService
{
    public function __construct(
        private readonly FnbSecurityPolicyRegistry $policies,
        private readonly FnbAuthoritySnapshotService $authority,
        private readonly FnbReauthService $reauth,
        private readonly FnbAuditLogger $audit,
        private readonly FnbSessionBinding $sessions,
    ) {}

    public function request(
        Admin $requester,
        string $requesterSessionId,
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectType,
        string $subjectId,
        string $payloadHash,
        string $reason,
        string $idempotencyKey,
        ?string $requestId = null,
    ): FnbApproval {
        $this->validateRequest($requesterSessionId, $outletId, $subjectType, $subjectId, $payloadHash, $reason, $idempotencyKey);
        $policy = $this->policies->forAction($action);
        if ($policy['approver'] === []) {
            throw ValidationException::withMessages(['action' => ['Thao tác này không yêu cầu approval token.']]);
        }

        $fingerprint = $this->requestFingerprint(
            $requester->id,
            $requesterSessionId,
            $websiteKey,
            $outletId,
            $action,
            $subjectType,
            $subjectId,
            $payloadHash,
            $reason,
            $policy,
        );

        return DB::transaction(function () use (
            $requester,
            $requesterSessionId,
            $websiteKey,
            $outletId,
            $action,
            $subjectType,
            $subjectId,
            $payloadHash,
            $reason,
            $idempotencyKey,
            $requestId,
            $policy,
            $fingerprint,
        ): FnbApproval {
            $outlet = DB::table('fnb_outlets')
                ->where('website_key', $websiteKey)
                ->where('id', $outletId)
                ->lockForUpdate()
                ->first();
            if ($outlet === null) {
                throw new AuthorizationException('Approval outlet scope không còn hợp lệ.');
            }

            $requester = Admin::query()->whereKey($requester->id)->lockForUpdate()->first();
            if (! $requester instanceof Admin || ! $requester->isAvailable()) {
                throw new AuthorizationException('Tài khoản requester không còn hoạt động.');
            }

            $existing = FnbApproval::query()
                ->where('website_key', $websiteKey)
                ->where('outlet_id', $outletId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if (! hash_equals((string) ($existing->policy_snapshot['request_fingerprint'] ?? ''), $fingerprint)) {
                    throw new ConflictHttpException('Idempotency-Key của approval đã được dùng với payload khác.');
                }

                return $existing;
            }

            $rateKey = $this->rateKey($requester->id, $outletId, $action);
            if (RateLimiter::tooManyAttempts($rateKey, 10)) {
                throw new TooManyRequestsHttpException(RateLimiter::availableIn($rateKey), 'Tạo approval quá thường xuyên.');
            }
            RateLimiter::hit($rateKey, 300);

            $snapshot = $this->authority->capture($requester, $websiteKey, $outletId, $policy['executor']);
            $configuredTtl = max(1, min(300, (int) config('fnb.security.approval_ttl_seconds', 90)));
            $approval = FnbApproval::query()->create([
                'website_key' => $websiteKey,
                'outlet_id' => $outletId,
                'public_id' => (string) Str::uuid(),
                'policy_key' => $policy['key'],
                'policy_version' => $policy['version'],
                'installed_module_version' => $policy['module_version'],
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'requester_id' => $requester->id,
                'requester_session_hash' => $this->sessions->hash($requesterSessionId),
                'requester_authority_hash' => $snapshot->hash,
                'requester_authority_revision' => $snapshot->revision,
                'required_permissions' => $policy['approver'],
                'status' => 'pending',
                'reason' => $reason,
                'policy_snapshot' => [
                    'policy_key' => $policy['key'],
                    'policy_version' => $policy['version'],
                    'module_version' => $policy['module_version'],
                    'executor_permissions' => $policy['executor'],
                    'approver_permissions' => $policy['approver'],
                    'dual' => $policy['dual'],
                    'conditional_dual' => $policy['conditional_dual'],
                    'rule' => $policy['raw'],
                    'request_fingerprint' => $fingerprint,
                ],
                'payload_hash' => strtolower($payloadHash),
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addSeconds($configuredTtl),
                'version' => 1,
            ]);

            $this->event($approval, 'requested', null, 'pending', $requester, ['policy_key' => $policy['key']]);
            $this->audit->record(
                'fnb.approval.requested',
                $websiteKey,
                $requester,
                $approval,
                after: ['action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId],
                outletIds: [$outletId],
            );

            return $approval;
        }, 3);
    }

    public function approve(
        FnbApproval $approval,
        Admin $approver,
        string $approverSessionId,
        string $reauthProof,
        ?string $note,
        int $expectedVersion,
    ): FnbApproval {
        return DB::transaction(function () use ($approval, $approver, $approverSessionId, $reauthProof, $note, $expectedVersion): FnbApproval {
            $approval = FnbApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertPendingVersion($approval, $expectedVersion);

            $approver = Admin::query()->whereKey($approver->id)->lockForUpdate()->first();
            if (! $approver instanceof Admin || ! $approver->isAvailable()) {
                throw new AuthorizationException('Tài khoản approver không còn hoạt động.');
            }
            if ((int) $approval->requester_id === (int) $approver->id) {
                throw new AuthorizationException('Approval này yêu cầu một approver khác requester.');
            }

            $policy = $this->assertCurrentPolicy($approval);
            $permissions = array_values($approval->required_permissions ?? []);
            if ($permissions !== $policy['approver']) {
                throw new AuthorizationException('Approval permission snapshot không còn hợp lệ.');
            }

            $snapshot = $this->authority->capture(
                $approver,
                $approval->website_key,
                (int) $approval->outlet_id,
                $permissions,
            );
            $this->reauth->consume(
                $reauthProof,
                $approver,
                $approverSessionId,
                $approval->website_key,
                (int) $approval->outlet_id,
                'approval.approve',
                (string) $approval->public_id,
                (string) $approval->payload_hash,
            );

            $from = $approval->status;
            $approval->forceFill([
                'approver_id' => $approver->id,
                'approver_authority_hash' => $snapshot->hash,
                'approver_authority_revision' => $snapshot->revision,
                'status' => 'approved',
                'note' => $note,
                'approved_at' => now(),
                'version' => (int) $approval->version + 1,
            ])->save();
            $this->event($approval, 'approved', $from, 'approved', $approver);
            $this->audit->record(
                'fnb.approval.approved',
                $approval->website_key,
                $approver,
                $approval,
                after: ['action' => $approval->action, 'requester_id' => (int) $approval->requester_id],
                outletIds: [(int) $approval->outlet_id],
            );

            return $approval->fresh();
        }, 3);
    }

    public function reject(
        FnbApproval $approval,
        Admin $approver,
        string $approverSessionId,
        string $reauthProof,
        string $reason,
        int $expectedVersion,
    ): FnbApproval {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => ['Lý do từ chối phải có ít nhất 3 ký tự.']]);
        }

        return DB::transaction(function () use ($approval, $approver, $approverSessionId, $reauthProof, $reason, $expectedVersion): FnbApproval {
            $approval = FnbApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertPendingVersion($approval, $expectedVersion);
            $approver = Admin::query()->whereKey($approver->id)->lockForUpdate()->first();
            if (! $approver instanceof Admin || ! $approver->isAvailable()) {
                throw new AuthorizationException('Tài khoản approver không còn hoạt động.');
            }
            $policy = $this->assertCurrentPolicy($approval);
            $permissions = array_values($approval->required_permissions ?? []);
            if ($permissions !== $policy['approver']) {
                throw new AuthorizationException('Approval permission snapshot không còn hợp lệ.');
            }
            $this->authority->capture($approver, $approval->website_key, (int) $approval->outlet_id, $permissions);
            $this->reauth->consume(
                $reauthProof,
                $approver,
                $approverSessionId,
                $approval->website_key,
                (int) $approval->outlet_id,
                'approval.reject',
                (string) $approval->public_id,
                (string) $approval->payload_hash,
            );

            $from = $approval->status;
            $approval->forceFill([
                'approver_id' => $approver->id,
                'status' => 'rejected',
                'note' => $reason,
                'rejected_at' => now(),
                'version' => (int) $approval->version + 1,
            ])->save();
            $this->event($approval, 'rejected', $from, 'rejected', $approver, ['reason' => $reason]);
            $this->audit->record(
                'fnb.approval.rejected',
                $approval->website_key,
                $approver,
                $approval,
                after: ['action' => $approval->action, 'reason' => $reason],
                outletIds: [(int) $approval->outlet_id],
            );

            return $approval->fresh();
        }, 3);
    }

    public function claimApprovedToken(
        FnbApproval $approval,
        Admin $requester,
        string $requesterSessionId,
    ): IssuedApprovalToken {
        return DB::transaction(function () use ($approval, $requester, $requesterSessionId): IssuedApprovalToken {
            $approval = FnbApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertClaimable($approval, $requester, $requesterSessionId);
            $this->assertLiveAuthorities($approval);

            $plainToken = $this->token();
            $approval->forceFill([
                'token_hash' => $this->tokenHash($plainToken),
                'version' => (int) $approval->version + 1,
            ])->save();
            $this->event($approval, 'token_claimed', 'approved', 'approved', $requester);

            return new IssuedApprovalToken($plainToken, $approval->expires_at);
        }, 3);
    }

    public function consume(
        string $opaqueToken,
        Admin $requester,
        string $requesterSessionId,
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectType,
        string $subjectId,
        string $payloadHash,
    ): FnbApproval {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('An approval token must be consumed inside the protected mutation transaction.');
        }

        $approval = FnbApproval::query()
            ->where('token_hash', $this->tokenHash($opaqueToken))
            ->lockForUpdate()
            ->first();
        if ($approval === null
            || (int) $approval->requester_id !== (int) $requester->id
            || ! hash_equals((string) $approval->requester_session_hash, $this->sessions->hash($requesterSessionId))
            || ! hash_equals((string) $approval->website_key, $websiteKey)
            || (int) $approval->outlet_id !== $outletId
            || ! hash_equals((string) $approval->action, $action)
            || ! hash_equals((string) $approval->subject_type, $subjectType)
            || ! hash_equals((string) $approval->subject_id, $subjectId)
            || ! hash_equals((string) $approval->payload_hash, strtolower($payloadHash))) {
            throw new AuthorizationException('Approval token không hợp lệ cho thao tác này.');
        }
        if ($approval->consumed_at !== null) {
            throw new ConflictHttpException('Approval token đã được sử dụng.');
        }
        if ($approval->status !== 'approved' || $approval->expires_at->isPast() || $approval->cancelled_at !== null) {
            throw new AuthorizationException('Approval token không còn hiệu lực.');
        }

        $this->assertLiveAuthorities($approval);
        $updated = FnbApproval::query()->whereKey($approval->id)
            ->where('status', 'approved')
            ->whereNull('consumed_at')
            ->whereNull('cancelled_at')
            ->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'version' => (int) $approval->version + 1,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new ConflictHttpException('Approval token đã được sử dụng.');
        }

        $approval = $approval->fresh();
        $this->event($approval, 'consumed', 'approved', 'consumed', $requester);
        $this->audit->record(
            'fnb.approval.consumed',
            $websiteKey,
            $requester,
            $approval,
            after: ['action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId],
            outletIds: [$outletId],
        );

        return $approval;
    }

    private function assertPendingVersion(FnbApproval $approval, int $expectedVersion): void
    {
        if ((int) $approval->version !== $expectedVersion) {
            throw new ConflictHttpException("Approval version conflict; current version is {$approval->version}.");
        }
        if ($approval->status !== 'pending' || $approval->expires_at->isPast() || $approval->cancelled_at !== null) {
            throw new ConflictHttpException('Approval không còn ở trạng thái pending hợp lệ.');
        }
    }

    private function assertClaimable(FnbApproval $approval, Admin $requester, string $sessionId): void
    {
        if ((int) $approval->requester_id !== (int) $requester->id
            || ! hash_equals((string) $approval->requester_session_hash, $this->sessions->hash($sessionId))) {
            throw new AuthorizationException('Chỉ requester trong phiên gốc mới được nhận approval token.');
        }
        if ($approval->status !== 'approved' || $approval->consumed_at !== null
            || $approval->cancelled_at !== null || $approval->expires_at->isPast()) {
            throw new ConflictHttpException('Approval không còn token có thể nhận.');
        }
    }

    /** @return array<string,mixed> */
    private function assertCurrentPolicy(FnbApproval $approval): array
    {
        $policy = $this->policies->forAction((string) $approval->action);
        if ($policy['key'] !== $approval->policy_key
            || $policy['version'] !== (int) $approval->policy_version
            || $policy['module_version'] !== $approval->installed_module_version) {
            throw new AuthorizationException('Approval policy hoặc module version đã thay đổi.');
        }

        return $policy;
    }

    private function assertLiveAuthorities(FnbApproval $approval): void
    {
        $policy = $this->assertCurrentPolicy($approval);
        $actors = Admin::query()
            ->whereIn('id', [$approval->requester_id, $approval->approver_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $requester = $actors->get((int) $approval->requester_id);
        $approver = $actors->get((int) $approval->approver_id);
        if (! $requester instanceof Admin || ! $approver instanceof Admin
            || ! $requester->isAvailable() || ! $approver->isAvailable()) {
            throw new AuthorizationException('Requester hoặc approver không còn hoạt động.');
        }

        $requesterSnapshot = $this->authority->capture(
            $requester,
            $approval->website_key,
            (int) $approval->outlet_id,
            $policy['executor'],
        );
        $approverSnapshot = $this->authority->capture(
            $approver,
            $approval->website_key,
            (int) $approval->outlet_id,
            $policy['approver'],
        );

        if (! hash_equals((string) $approval->requester_authority_hash, $requesterSnapshot->hash)
            || (int) $approval->requester_authority_revision !== $requesterSnapshot->revision
            || ! hash_equals((string) $approval->approver_authority_hash, $approverSnapshot->hash)
            || (int) $approval->approver_authority_revision !== $approverSnapshot->revision) {
            throw new AuthorizationException('Authority của requester hoặc approver đã thay đổi.');
        }
    }

    /** @param array<string,mixed> $payload */
    private function event(
        FnbApproval $approval,
        string $type,
        ?string $from,
        string $to,
        Admin $actor,
        array $payload = [],
    ): void {
        FnbApprovalEvent::query()->create([
            'website_key' => $approval->website_key,
            'outlet_id' => $approval->outlet_id,
            'approval_id' => $approval->id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'payload' => $payload === [] ? null : $payload,
            'idempotency_key' => $type.':'.$approval->version,
            'occurred_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $policy */
    private function requestFingerprint(
        int $requesterId,
        string $sessionId,
        string $websiteKey,
        int $outletId,
        string $action,
        string $subjectType,
        string $subjectId,
        string $payloadHash,
        string $reason,
        array $policy,
    ): string {
        return hash('sha256', json_encode([
            'requester_id' => $requesterId,
            'session_hash' => $this->sessions->hash($sessionId),
            'website_key' => $websiteKey,
            'outlet_id' => $outletId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload_hash' => strtolower($payloadHash),
            'reason' => trim($reason),
            'policy_key' => $policy['key'],
            'policy_version' => $policy['version'],
            'module_version' => $policy['module_version'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function validateRequest(
        string $sessionId,
        int $outletId,
        string $subjectType,
        string $subjectId,
        string $payloadHash,
        string $reason,
        string $idempotencyKey,
    ): void {
        $errors = [];
        if ($sessionId === '') {
            $errors['session'] = ['Requester session is required.'];
        }
        if ($outletId < 1) {
            $errors['outlet_id'] = ['Approval requires an outlet scope.'];
        }
        if ($subjectType === '' || $subjectId === '') {
            $errors['subject'] = ['Approval subject is required.'];
        }
        if (! preg_match('/\A[a-f0-9]{64}\z/i', $payloadHash)) {
            $errors['payload_hash'] = ['Payload hash phải là SHA-256 hợp lệ.'];
        }
        if (mb_strlen(trim($reason)) < 3 || mb_strlen($reason) > 1000) {
            $errors['reason'] = ['Lý do phải từ 3 đến 1000 ký tự.'];
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 120) {
            $errors['idempotency_key'] = ['Idempotency-Key không hợp lệ.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function rateKey(int $adminId, int $outletId, string $action): string
    {
        return 'fnb:approval:'.$adminId.':'.$outletId.':'.hash('sha256', $action);
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
