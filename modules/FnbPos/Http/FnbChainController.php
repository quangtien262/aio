<?php

namespace Modules\FnbPos\Http;

use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbChainService;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\FnbMenuReleaseService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use Modules\FnbPos\Services\Security\FnbScopedStaffService;

final class FnbChainController
{
    public function __construct(private readonly SiteContext $site, private readonly FnbOutletAccessService $access, private readonly FnbCommandSecurity $security) {}

    public function bootstrap(Request $request, FnbChainService $chain)
    {
        return response()->json(['data' => $chain->bootstrap($request->user('admin'), $this->site->websiteKey())]);
    }

    public function save(Request $request, FnbChainService $chain)
    {
        $kind = (string) $request->route('fnb_chain_kind');
        $data = $request->except(['website_key', 'actor_id', 'reauth_proof', 'approval_token']);
        $request->route()->setParameter('fnb_permission', 'fnb.chain.manage');
        $ctx = $this->context($request, 'chain.'.$kind.'.save', $data, 'fnb.chain.manage');
        $result = $chain->save($ctx, $kind, $request->route('resource') ? (int) $request->route('resource') : null, $data, $this->key($request));

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }

    public function assignHq(Request $request, FnbCommandRunner $commands, FnbScopedStaffService $staff)
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:254'], 'role_key' => ['required', 'in:fnb-owner,fnb-hq-manager'], 'expected_version' => ['nullable', 'integer', 'min:1']]);
        $request->route()->setParameter('fnb_permission', 'fnb.chain.staff.manage');
        $ctx = $this->context($request, 'chain.hq.assign', $data, 'fnb.chain.staff.manage');
        $result = $commands->run($ctx, 'chain.hq.assign', $data, $this->key($request), function () use ($ctx, $request, $data, $staff): array {
            $target = Admin::query()->where(fn ($q) => $q->where('email', $data['identifier'])->orWhere('username', $data['identifier']))->first();
            abort_unless($target, 422, 'Không tìm thấy tài khoản có thể phân công.');

            return ['resource' => $staff->assignHq($request->user('admin'), $ctx->websiteKey, $target, $data['role_key'], $data['expected_version'] ?? null)];
        });

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }

    public function revokeHq(Request $request, FnbCommandRunner $commands, FnbScopedStaffService $staff)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $request->route()->setParameter('fnb_permission', 'fnb.chain.staff.manage');
        $ctx = $this->context($request, 'chain.hq.revoke', $data, 'fnb.chain.staff.manage');
        $id = (int) $request->route('resource');
        $result = $commands->run($ctx, 'chain.hq.revoke', $data + ['id' => $id], $this->key($request), fn () => ['resource' => $staff->revokeHq($request->user('admin'), $ctx->websiteKey, $id, $data['expected_version'])]);

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }

    public function audit(Request $request)
    {
        $website = $this->site->websiteKey();
        $actor = $request->user('admin');
        $request->validate(['outlet_id' => ['nullable', 'integer', 'min:1'], 'cursor' => ['nullable', 'integer', 'min:1']]);
        $outlet = $request->integer('outlet_id');
        if ($outlet) {
            $this->access->authorize($actor, $website, $outlet, 'fnb.audit.view');
        } else {
            $this->access->authorizeHq($actor, $website, 'fnb.audit.view');
        }
        $query = DB::table('audit_logs')->where('module_key', 'fnb-pos')->where('website_key', $website)->when($request->integer('cursor'), fn ($q, $cursor) => $q->where('id', '<', $cursor));
        if ($outlet) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('fnb_audit_scopes')->whereColumn('fnb_audit_scopes.audit_log_id', 'audit_logs.id')->where('fnb_audit_scopes.website_key', $website)->where('scope_type', 'outlet')->where('scope_value', (string) $outlet));
        }
        $rows = $query->orderByDesc('id')->limit(51)->get(['id', 'actor_admin_id', 'action', 'target_type', 'target_id', 'before', 'after', 'request_id', 'created_at']);
        $more = $rows->count() > 50;
        $items = $rows->take(50)->map(function ($row): array {
            $value = (array) $row;
            foreach (['before', 'after'] as $key) {
                $value[$key] = is_string($value[$key]) ? json_decode($value[$key], true) : $value[$key];
            }

            return FnbPublicProjection::operational($value);
        })->values();

        return response()->json(['data' => ['items' => $items, 'next_cursor' => $more ? $items->last()['id'] : null]]);
    }

    public function menu(Request $request, FnbMenuReleaseService $menus)
    {
        return response()->json(['data' => $menus->overview($request->user('admin'), $this->site->websiteKey())]);
    }

    public function menuCommand(Request $request, FnbMenuReleaseService $menus)
    {
        $action = (string) $request->route('fnb_chain_action');
        $data = $request->except(['website_key', 'actor_id', 'reauth_proof', 'approval_token']);
        $request->route()->setParameter('fnb_permission', 'fnb.menu.master.manage');
        $ctx = $this->context($request, $action, $data, 'fnb.menu.master.manage');
        $result = match ($action) {
            'chain.menu.create' => $menus->create($ctx, $data, $this->key($request)),
            'chain.menu.publish' => $menus->publish($ctx, $data, $this->key($request)),
            'chain.menu.rollback' => $menus->rollback($ctx, (int) $request->route('resource'), $data, $this->key($request)),
            'chain.pricebook.save' => $menus->priceBook($ctx, $data, $this->key($request)),
        };

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]]);
    }

    private function context(Request $request, string $action, array $data, string $permission): FnbContext
    {
        $website = $this->site->websiteKey();
        $this->access->authorizeHq($request->user('admin'), $website, $permission);

        return new FnbContext($website, 0, $request->user('admin')->id, null, $this->security->authorization($request, $action, $data));
    }

    private function key(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();

        return $key;
    }
}
