<?php

namespace Modules\FnbPos\Http;

use App\Models\Admin;
use App\Models\Role;
use App\Support\SiteContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Models\FnbApproval;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\FnbStaffCandidateService;
use Modules\FnbPos\Services\Security\FnbApprovalService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbReauthService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;

class FnbSecurityController
{
    public function __construct(
        private readonly FnbApiController $api,
        private readonly FnbCommandSecurity $security,
        private readonly FnbOutletAccessService $access,
        private readonly SiteContext $site,
    ) {}

    public function reauth(Request $request, FnbReauthService $reauth): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:80'], 'subject' => ['required', 'string', 'max:100'],
            'payload_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['required', 'string', 'max:1024'], 'two_factor_code' => ['nullable', 'string', 'max:100'],
        ]);
        $outlet = (int) ($request->header('X-FNB-Outlet') ?? $request->input('outlet_id', 0));
        $proof = $reauth->issue($request->user('admin'), $request->session()->getId(), $request->ip(),
            $this->site->websiteKey(), $outlet, $data['action'], $data['subject'], $data['payload_hash'], $data['password'], $data['two_factor_code'] ?? null);

        return response()->json(['data' => $proof->toArray()]);
    }

    public function requestApproval(Request $request, FnbApprovalService $approvals): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:80'], 'subject' => ['required', 'string', 'max:100'],
            'payload_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $policy = FnbCommandSecurity::POLICIES[$data['action']] ?? null;
        abort_unless($policy && $policy['approvers'] !== [], 422, 'Thao tác không sử dụng quy trình phê duyệt.');
        $ctx = $this->api->context($request, $policy['executor']);
        $result = $approvals->request($request->user('admin'), $request->session()->getId(), $ctx->websiteKey, $ctx->outletId,
            $data['action'], 'fnb_command', $data['subject'], $data['payload_hash'], $data['reason'], $this->key($request), $request->header('X-Request-ID'));

        return response()->json(['data' => $this->safeApproval($result)]);
    }

    public function approvals(Request $request): JsonResponse
    {
        $ctx = $this->api->context($request, 'fnb.outlet.view');
        $actor = $request->user('admin');
        $items = FnbApproval::query()->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)
            ->whereIn('status', ['pending', 'approved'])->where('expires_at', '>', now())->latest()->limit(100)->get()
            ->filter(fn ($approval) => $this->canReadApproval($actor, $approval))->map(fn ($approval) => $this->safeApproval($approval))->values();

        return response()->json(['data' => ['items' => $items]]);
    }

    public function approval(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->safeApproval($this->scopedApproval($request))]);
    }

    public function approve(Request $request, FnbApprovalService $service): JsonResponse
    {
        $approval = $this->scopedApproval($request);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:500']]);
        $result = $service->approve($approval, $request->user('admin'), $request->session()->getId(),
            $this->approvalProof($request, $approval, 'approval.approve'), $data['note'] ?? null, (int) $data['expected_version']);

        return response()->json(['data' => $this->safeApproval($result)]);
    }

    public function reject(Request $request, FnbApprovalService $service): JsonResponse
    {
        $approval = $this->scopedApproval($request);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $result = $service->reject($approval, $request->user('admin'), $request->session()->getId(),
            $this->approvalProof($request, $approval, 'approval.reject'), $data['reason'], (int) $data['expected_version']);

        return response()->json(['data' => $this->safeApproval($result)]);
    }

    public function claim(Request $request, FnbApprovalService $service): JsonResponse
    {
        $approval = $this->scopedApproval($request);
        $token = $service->claimApprovedToken($approval, $request->user('admin'), $request->session()->getId());

        return response()->json(['data' => $token->toArray()]);
    }

    public function staff(Request $request): JsonResponse
    {
        $ctx = $this->api->context($request, 'fnb.staff.view');
        $items = DB::table('fnb_outlet_staff as memberships')
            ->join('admins', 'admins.id', '=', 'memberships.admin_id')
            ->leftJoin('fnb_staff_role_bindings as bindings', function ($join): void {
                $join->on('bindings.admin_id', '=', 'memberships.admin_id')->on('bindings.website_key', '=', 'memberships.website_key');
            })->leftJoin('roles', 'roles.id', '=', 'bindings.preset_role_id')
            ->where('memberships.website_key', $ctx->websiteKey)->where('memberships.outlet_id', $ctx->outletId)
            ->where('memberships.is_active', true)->orderBy('admins.name')
            ->get(['memberships.id', 'admins.name', 'roles.key as role_key', 'roles.name as role_name', 'memberships.expires_at', 'memberships.version', 'bindings.version as binding_version']);
        $actor = $request->user('admin');
        $permissions = $actor->permissions($ctx->websiteKey);
        $presets = [];
        if ($actor->hasPermission('fnb.staff.assign', $ctx->websiteKey)) {
            $roleIds = DB::table('module_role_definitions')->where('module_key', 'fnb-pos')->pluck('role_id');
            $presets = Role::query()->whereIn('id', $roleIds)->where('status', 'active')->with('permissions')->get()
                ->filter(fn ($role) => array_diff($role->permissions->where('is_active', true)->pluck('key')->all(), $permissions) === [])
                ->map(fn ($role) => $role->only(['key', 'name', 'description']))->values()->all();
        }

        return response()->json(['data' => ['items' => $items, 'role_presets' => $presets]]);
    }

    public function candidate(Request $request, FnbStaffCandidateService $candidates): JsonResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'min:3', 'max:254']]);
        $ctx = $this->api->context($request, 'fnb.staff.assign');

        return response()->json(['data' => $candidates->resolve($request->user('admin'), $ctx, $request->session()->getId(), $data['identifier'], (string) $request->ip())]);
    }

    public function assign(Request $request, FnbStaffCandidateService $candidates, FnbStaffAssignmentService $staff, FnbCommandRunner $commands): JsonResponse
    {
        $data = $request->validate([
            'candidate_token' => ['required', 'string', 'size:64'], 'role_key' => ['required', 'string', 'max:80'],
            'expected_binding_version' => ['nullable', 'integer', 'min:1'], 'expected_membership_version' => ['nullable', 'integer', 'min:1'],
            'terminal_ids' => ['sometimes', 'array', 'max:20'], 'terminal_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);
        $request->route()->setParameter('fnb_permission', 'fnb.staff.assign');
        $ctx = $this->api->context($request, 'fnb.staff.assign', false, $this->security->authorization($request, 'staff.assign', $data));
        $result = $commands->run($ctx, 'staff.assign', $data, $this->key($request), function () use ($request, $data, $ctx, $candidates, $staff): array {
            $target = $candidates->consume($request->user('admin'), $ctx, $request->session()->getId(), $data['candidate_token']);

            return ['resource' => $staff->assign($request->user('admin'), $ctx->websiteKey, $ctx->outletId, $target, $data['role_key'],
                $data['expected_binding_version'] ?? null, $data['expected_membership_version'] ?? null, $data['terminal_ids'] ?? [])];
        });

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }

    public function revoke(Request $request, FnbStaffAssignmentService $staff, FnbCommandRunner $commands): JsonResponse
    {
        $data = $request->validate(['expected_binding_version' => ['required', 'integer', 'min:1'], 'expected_membership_version' => ['required', 'integer', 'min:1']]);
        $request->route()->setParameter('fnb_permission', 'fnb.staff.assign');
        $ctx = $this->api->context($request, 'fnb.staff.assign', false, $this->security->authorization($request, 'staff.revoke', $data));
        $id = (int) $request->route('resource');
        $result = $commands->run($ctx, 'staff.revoke', $data + ['id' => $id], $this->key($request), function () use ($request, $data, $ctx, $id, $staff): array {
            $membership = DB::table('fnb_outlet_staff')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('id', $id)->lockForUpdate()->first();
            abort_unless($membership, 404);
            $target = Admin::query()->findOrFail($membership->admin_id);

            return ['resource' => $staff->revoke($request->user('admin'), $ctx->websiteKey, $ctx->outletId, $target, (int) $data['expected_binding_version'], (int) $data['expected_membership_version'])];
        });

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }

    private function scopedApproval(Request $request): FnbApproval
    {
        $approval = FnbApproval::query()->where('website_key', $this->site->websiteKey())->findOrFail((int) $request->route('resource'));
        $this->access->authorize($request->user('admin'), $approval->website_key, (int) $approval->outlet_id, 'fnb.outlet.view');
        abort_unless($this->canReadApproval($request->user('admin'), $approval), 403);

        return $approval;
    }

    private function canReadApproval(Admin $actor, FnbApproval $approval): bool
    {
        if ((int) $approval->requester_id === (int) $actor->id) {
            return true;
        }
        foreach ($approval->required_permissions as $permission) {
            try {
                $this->access->authorize($actor, $approval->website_key, (int) $approval->outlet_id, $permission);
            } catch (AuthorizationException) {
                return false;
            }
        }

        return $approval->required_permissions !== [];
    }

    private function safeApproval(FnbApproval $approval): array
    {
        return $approval->only(['id', 'public_id', 'action', 'subject_type', 'subject_id', 'payload_hash', 'requester_id', 'approver_id', 'required_permissions', 'status', 'reason', 'note', 'version', 'expires_at']);
    }

    private function approvalProof(Request $request, FnbApproval $approval, string $action): string
    {
        $proof = (string) ($request->header('X-FNB-Reauth-Proof') ?? $request->input('reauth_proof', ''));
        if ($proof === '') {
            throw new HttpResponseException(response()->json([
                'code' => 'FNB_REAUTH_REQUIRED', 'message' => 'Vui lòng xác thực lại trước khi phê duyệt.',
                'request_id' => $request->header('X-Request-ID'),
                'details' => ['action' => $action, 'subject' => $approval->public_id,
                    'payload_hash' => $approval->payload_hash, 'policy_key' => $approval->policy_key, 'required_permissions' => []],
            ], 423)->header('Cache-Control', 'no-store'));
        }

        return $proof;
    }

    private function key(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();

        return $key;
    }
}
