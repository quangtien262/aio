<?php

namespace Modules\FnbPos\Http;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbApprovalService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbReauthService;

class FnbCommandSecurity
{
    public const POLICIES = [
        'onboard' => ['policy' => 'outlet.change', 'executor' => 'fnb.outlet.manage', 'approvers' => []],
        'line.void' => ['policy' => 'order.void', 'executor' => 'fnb.order.void', 'approvers' => ['fnb.order.void.approve']],
        'check.void' => ['policy' => 'order.void', 'executor' => 'fnb.order.void', 'approvers' => ['fnb.order.void.approve']],
        'check.reopen' => ['policy' => 'order.reopen', 'executor' => 'fnb.order.reopen', 'approvers' => []],
        'order.discount' => ['policy' => 'discount.override', 'executor' => 'fnb.discount.apply', 'approvers' => ['fnb.discount.override']],
        'payment.refund' => ['policy' => 'payment.refund', 'executor' => 'fnb.payment.refund', 'approvers' => ['fnb.payment.refund.approve']],
        'refund.cancel' => ['policy' => 'payment.refund', 'executor' => 'fnb.payment.refund', 'approvers' => []],
        'shift.reconcile' => ['policy' => 'shift.reconcile', 'executor' => 'fnb.shift.close', 'approvers' => ['fnb.shift.reconcile']],
        'fulfillment.compensate' => ['policy' => 'fulfillment.compensate', 'executor' => 'fnb.order.void', 'approvers' => ['fnb.order.void.approve', 'fnb.payment.refund.approve']],
        'cash.record' => ['policy' => 'cash.adjust', 'executor' => 'fnb.cash.adjust', 'approvers' => ['fnb.cash.adjust']],
        'staff.assign' => ['policy' => 'staff.assign', 'executor' => 'fnb.staff.assign', 'approvers' => []],
        'staff.revoke' => ['policy' => 'staff.assign', 'executor' => 'fnb.staff.assign', 'approvers' => []],
        'settings.save' => ['policy' => 'settings.change', 'executor' => 'fnb.settings.manage', 'approvers' => []],
        'outlet.save' => ['policy' => 'outlet.change', 'executor' => 'fnb.outlet.manage', 'approvers' => []],
        'terminal.save' => ['policy' => 'terminal.bind_or_revoke', 'executor' => 'fnb.terminal.manage', 'approvers' => []],
        'payment_method.save' => ['policy' => 'settings.change', 'executor' => 'fnb.settings.manage', 'approvers' => []],
    ];

    public function __construct(
        private readonly FnbOutletAccessService $access,
        private readonly FnbReauthService $reauth,
        private readonly FnbApprovalService $approvals,
    ) {}

    /** Executed by the domain after replay detection, inside the mutation transaction. */
    public function authorization(Request $request, string $action, array $payload): Closure
    {
        $subject = (string) ($request->route('resource') ?? 'new');
        $hash = self::fingerprint($payload);
        $actor = $request->user('admin');
        $permission = (string) $request->route('fnb_permission');

        return function (string $operation, array $authorizationPayload, FnbContext $ctx) use ($request, $action, $subject, $hash, $actor, $permission, $payload): void {
            $actor = Admin::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($ctx->outletId > 0) {
                $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, $permission);
                $this->access->authorizeTerminal($actor, $ctx->websiteKey, $ctx->outletId, $ctx->terminalId, $permission);
                if ($action === 'session.open' && ! empty($payload['customer_profile_id'])) {
                    $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, 'fnb.customer.attach');
                }
                if ($action === 'customer.create' && array_intersect_key($payload, array_flip(['birthday', 'notes', 'privacy_consent', 'marketing_consent'])) !== []) {
                    $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, 'fnb.customer.update');
                }
                if ($action === 'fulfillment.compensate') {
                    $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, 'fnb.payment.refund');
                }
            } else {
                abort_unless($this->access->canBootstrapWebsite($actor, $ctx->websiteKey, $permission), 403);
            }
            $policy = self::POLICIES[$action] ?? null;
            if ($policy === null) {
                return;
            }
            $proof = (string) ($request->header('X-FNB-Reauth-Proof') ?? $request->input('reauth_proof', ''));
            $approval = (string) ($request->header('X-FNB-Approval-Token') ?? $request->input('approval_token', ''));
            if ($proof === '') {
                $this->challenge(423, 'FNB_REAUTH_REQUIRED', 'Vui lòng xác thực lại để thực hiện thao tác này.', $request, $action, $subject, $hash, $policy);
            }
            $this->reauth->consume($proof, $actor, $request->session()->getId(), $ctx->websiteKey, $ctx->outletId, $action, $subject, $hash);
            if ($policy['approvers'] !== []) {
                if ($approval === '') {
                    $this->challenge(403, 'FNB_APPROVAL_REQUIRED', 'Thao tác cần người có quyền phê duyệt.', $request, $action, $subject, $hash, $policy);
                }
                $evidence = $this->approvals->consume($approval, $actor, $request->session()->getId(), $ctx->websiteKey, $ctx->outletId, $action, 'fnb_command', $subject, $hash);
                $ctx->authorizationEvidence->recordApproval((int) $evidence->id, (int) $evidence->approver_id);
            }
        };
    }

    public static function fingerprint(array $payload): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function challenge(int $status, string $code, string $message, Request $request, string $action, string $subject, string $hash, array $policy): never
    {
        throw new HttpResponseException(response()->json([
            'code' => $code, 'message' => $message, 'request_id' => $request->header('X-Request-ID'),
            'details' => ['action' => $action, 'subject' => $subject, 'payload_hash' => $hash,
                'policy_key' => $policy['policy'], 'required_permissions' => $policy['approvers']],
        ], $status)->header('Cache-Control', 'no-store'));
    }
}
