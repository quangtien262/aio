<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Role;
use App\Models\Site;
use App\Support\AdminPrivilegeGuard;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminAccountController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AdminPrivilegeGuard $privilegeGuard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);

        $admins = Admin::query()
            ->where('id', '!=', Admin::SYSTEM_OWNER_ID)
            ->with(['roles:id,name,key,is_system', 'roleAssignments:id,admin_id,role_id,scope_type,scope_value,expires_at'])
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'status', 'is_active', 'is_system_owner', 'must_change_password', 'locked_at', 'locked_reason', 'last_login_at', 'last_login_ip'])
            ->map(fn (Admin $admin): array => $this->serializeAdmin($admin))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'admins' => $admins,
                'roles' => Role::query()
                    ->where('status', 'active')
                    ->where('is_assignable', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'key'])
                    ->toArray(),
                'scope_types' => config('aio.scope_types', []),
                'websites' => Schema::hasTable('sites')
                    ? Site::query()->where('status', 'active')->orderBy('name')->get(['website_key', 'name', 'domain'])->toArray()
                    : [],
                'organizations' => Schema::hasTable('acct_organizations')
                    ? AcctOrganization::query()
                        ->when(
                            ! $actor->hasGlobalAssignmentScope(),
                            fn ($query) => $query->whereIn(
                                'id',
                                $actor->roleAssignments()
                                    ->whereHas('role', fn ($roleQuery) => $roleQuery->where('status', 'active'))
                                    ->where('scope_type', 'organization')
                                    ->where(fn ($assignmentQuery) => $assignmentQuery->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                                    ->pluck('scope_value'),
                            ),
                        )
                        ->where('status', 'active')
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->get(['id', 'name', 'legal_name', 'tax_code'])
                        ->toArray()
                    : [],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);

        $validated = $this->validatePayload($request, requirePassword: true);
        $this->privilegeGuard->assertCanDelegateAssignments($actor, $validated['assignments'] ?? []);

        $admin = DB::transaction(function () use ($actor, $validated): Admin {
            $admin = Admin::query()->create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => $validated['status'] ?? 'active',
                'is_active' => ($validated['status'] ?? 'active') === 'active',
                'must_change_password' => true,
            ]);

            $this->syncAssignments($admin, $validated['assignments'] ?? [], $actor);

            return $admin;
        });

        $this->auditLogger->record('admin.account.created', $admin, null, $this->serializeAdmin($admin->fresh()));

        return response()->json(['message' => 'Tạo tài khoản quản trị thành công.'], 201);
    }

    public function update(Request $request, Admin $admin): JsonResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);
        abort_if($admin->isSystemOwner(), 422, 'Tài khoản System Owner là tài khoản hệ thống và không thể chỉnh sửa.');
        $this->privilegeGuard->assertCanManageProtectedAccount($actor, $admin);

        $before = $this->serializeAdmin($admin->load(['roles', 'roleAssignments']));
        $validated = $this->validatePayload($request, $admin);
        $this->privilegeGuard->assertCanReplaceAssignments($actor, $admin, $validated['assignments'] ?? []);

        if ($admin->isSystemOwner()) {
            $validated['status'] = 'active';
            $validated['assignments'] = $before['assignments'];
        }

        abort_if($actor?->is($admin) && ($validated['status'] ?? 'active') !== 'active', 422, 'Không thể tự vô hiệu hóa tài khoản đang đăng nhập.');

        DB::transaction(function () use ($actor, $admin, $validated): void {
            $admin->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'status' => $validated['status'] ?? 'active',
                'is_active' => ($validated['status'] ?? 'active') === 'active',
            ]);

            if (! $admin->isSystemOwner()) {
                $this->syncAssignments($admin, $validated['assignments'] ?? [], $actor);
                $admin->increment('auth_version');
            }
        });

        $this->auditLogger->record('admin.account.updated', $admin, $before, $this->serializeAdmin($admin->fresh()->load(['roles', 'roleAssignments'])));

        return response()->json(['message' => 'Cập nhật tài khoản quản trị thành công.']);
    }

    public function resetPassword(Request $request, Admin $admin): JsonResponse
    {
        abort_if($admin->isSystemOwner(), 422, 'System Owner chỉ có thể tự đổi mật khẩu sau khi xác nhận mật khẩu hiện tại.');
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);
        $this->privilegeGuard->assertCanManageProtectedAccount($actor, $admin);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $admin->update([
            'password' => $validated['password'],
            'must_change_password' => true,
            'password_changed_at' => now(),
            'auth_version' => $admin->auth_version + 1,
        ]);

        $this->auditLogger->record('admin.password.reset', $admin);

        return response()->json(['message' => 'Đã đặt lại mật khẩu và thu hồi các phiên đăng nhập cũ.']);
    }

    public function changeOwnPassword(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', $this->passwordRule(), 'different:current_password'],
        ]);

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mật khẩu hiện tại không đúng.']]);
        }

        $admin->update([
            'password' => $validated['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
            'auth_version' => $admin->auth_version + 1,
        ]);
        $request->session()->put('admin_auth_version', $admin->auth_version);
        $this->auditLogger->record('admin.password.changed', $admin);

        return response()->json(['message' => 'Đã đổi mật khẩu.']);
    }

    public function revokeSessions(Request $request, Admin $admin): JsonResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);
        $this->privilegeGuard->assertCanManageProtectedAccount($actor, $admin);

        $admin->increment('auth_version');

        if ($actor->is($admin)) {
            $request->session()->put('admin_auth_version', $admin->auth_version);
        }

        $this->auditLogger->record('admin.sessions.revoked', $admin);

        return response()->json(['message' => 'Đã thu hồi các phiên đăng nhập khác.']);
    }

    public function lock(Request $request, Admin $admin): JsonResponse
    {
        abort_if($admin->isSystemOwner(), 422, 'System Owner không thể bị khóa.');
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);
        abort_if($actor->is($admin), 422, 'Không thể tự khóa tài khoản đang sử dụng.');
        $this->privilegeGuard->assertCanManageProtectedAccount($actor, $admin);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $admin->update([
            'status' => 'suspended',
            'is_active' => false,
            'locked_at' => now(),
            'locked_reason' => $validated['reason'] ?? 'Khóa bởi quản trị viên.',
            'auth_version' => $admin->auth_version + 1,
        ]);
        $this->auditLogger->record('admin.account.locked', $admin, null, ['reason' => $admin->locked_reason]);

        return response()->json(['message' => 'Đã khóa tài khoản quản trị.']);
    }

    public function unlock(Request $request, Admin $admin): JsonResponse
    {
        abort_if($admin->isSystemOwner(), 422, 'System Owner luôn ở trạng thái hoạt động.');
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);
        $this->privilegeGuard->assertCanManageProtectedAccount($actor, $admin);
        $admin->update([
            'status' => 'active',
            'is_active' => true,
            'locked_at' => null,
            'locked_reason' => null,
            'auth_version' => $admin->auth_version + 1,
        ]);
        $this->auditLogger->record('admin.account.unlocked', $admin);

        return response()->json(['message' => 'Đã mở khóa tài khoản quản trị.']);
    }

    private function validatePayload(Request $request, ?Admin $admin = null, bool $requirePassword = false): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('admins', 'username')->ignore($admin?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin?->id)],
            'password' => [$requirePassword ? 'required' : 'nullable', 'confirmed', $this->passwordRule()],
            'status' => ['nullable', 'string', Rule::in(['active', 'suspended', 'archived'])],
            'assignments' => ['nullable', 'array'],
            'assignments.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'assignments.*.scope_type' => ['required', 'string', Rule::in(array_keys(config('aio.scope_types', [])))],
            'assignments.*.scope_value' => ['nullable', 'string', 'max:255'],
        ]);

        $assignments = collect($validated['assignments'] ?? [])->map(function (array $assignment): array {
            $scopeType = $assignment['scope_type'];
            $scopeValue = in_array($scopeType, ['website', 'organization'], true)
                ? trim((string) ($assignment['scope_value'] ?? ''))
                : null;

            if ($scopeType === 'website' && $scopeValue === '') {
                throw ValidationException::withMessages(['assignments' => ['Phạm vi website phải chọn một website cụ thể.']]);
            }

            if ($scopeType === 'website' && Schema::hasTable('sites') && ! Site::query()->where('status', 'active')->where('website_key', $scopeValue)->exists()) {
                throw ValidationException::withMessages(['assignments' => ['Website được phân quyền không tồn tại hoặc đã ngừng hoạt động.']]);
            }

            if ($scopeType === 'organization' && ($scopeValue === '' || ! ctype_digit($scopeValue) || (int) $scopeValue < 1)) {
                throw ValidationException::withMessages(['assignments' => ['Phạm vi pháp nhân phải chọn một pháp nhân kế toán cụ thể.']]);
            }

            if ($scopeType === 'organization' && (! Schema::hasTable('acct_organizations') || ! AcctOrganization::query()
                ->whereKey((int) $scopeValue)
                ->where('status', 'active')
                ->exists())) {
                throw ValidationException::withMessages(['assignments' => ['Pháp nhân kế toán không tồn tại hoặc đã ngừng hoạt động.']]);
            }

            return [
                'role_id' => (int) $assignment['role_id'],
                'scope_type' => $scopeType,
                'scope_value' => $scopeValue,
            ];
        })->unique(fn (array $item): string => implode(':', [$item['role_id'], $item['scope_type'], $item['scope_value']]))->values();

        $assignableRoleCount = Role::query()
            ->whereIn('id', $assignments->pluck('role_id'))
            ->where('is_assignable', true)
            ->where('status', 'active')
            ->count();
        abort_unless($assignableRoleCount === $assignments->pluck('role_id')->unique()->count(), 422, 'Không thể gán vai trò hệ thống hoặc vai trò ngừng hoạt động.');

        $validated['assignments'] = $assignments->all();

        return $validated;
    }

    private function syncAssignments(Admin $admin, array $assignments, ?Admin $actor): void
    {
        $admin->roleAssignments()->delete();

        foreach ($assignments as $assignment) {
            $admin->roleAssignments()->create([
                ...$assignment,
                'assigned_by' => $actor?->id,
            ]);
        }
    }

    private function serializeAdmin(Admin $admin): array
    {
        $admin->loadMissing(['roles:id,name,key,is_system', 'roleAssignments:id,admin_id,role_id,scope_type,scope_value,expires_at']);

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => $admin->status,
            'is_active' => $admin->isAvailable(),
            'is_locked' => $admin->isLocked(),
            'is_system_owner' => $admin->isSystemOwner(),
            'must_change_password' => (bool) $admin->must_change_password,
            'locked_reason' => $admin->locked_reason,
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
            'last_login_ip' => $admin->last_login_ip,
            'role_ids' => $admin->roles->pluck('id')->unique()->values()->all(),
            'roles' => $admin->roles->unique('id')->map(fn ($role): array => ['id' => $role->id, 'name' => $role->name, 'key' => $role->key])->values()->all(),
            'assignments' => $admin->roleAssignments->map(fn (AdminRoleAssignment $assignment): array => [
                'id' => $assignment->id,
                'role_id' => $assignment->role_id,
                'scope_type' => $assignment->scope_type,
                'scope_value' => $assignment->scope_value,
                'expires_at' => $assignment->expires_at?->toIso8601String(),
            ])->values()->all(),
            'permissions' => $admin->visiblePermissions(),
        ];
    }

    private function passwordRule(): Password
    {
        return Password::min(12)->mixedCase()->numbers()->symbols();
    }
}
