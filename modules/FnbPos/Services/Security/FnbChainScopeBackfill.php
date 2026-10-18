<?php

namespace Modules\FnbPos\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FnbChainScopeBackfill
{
    public function run(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('fnb_staff_role_scopes') || ! Schema::hasTable('fnb_staff_role_bindings')) {
            return;
        }
        DB::table('fnb_staff_role_bindings as bindings')->join('roles', 'roles.id', '=', 'bindings.preset_role_id')
            ->where('bindings.status', 'active')->whereNull('bindings.revoked_at')
            ->select('bindings.*', 'roles.key as role_key')->orderBy('bindings.id')->get()->each(function ($binding): void {
                $base = ['website_key' => $binding->website_key, 'admin_id' => $binding->admin_id,
                    'preset_role_id' => $binding->preset_role_id, 'core_assignment_id' => $binding->core_assignment_id,
                    'status' => 'active', 'version' => $binding->version, 'assigned_by' => $binding->assigned_by,
                    'created_at' => now(), 'updated_at' => now()];
                if ($binding->role_key === 'fnb-owner') {
                    DB::table('fnb_staff_role_scopes')->insertOrIgnore($base + ['scope_type' => 'hq', 'scope_key' => 0]);
                }
                DB::table('fnb_outlet_staff')->where('website_key', $binding->website_key)->where('admin_id', $binding->admin_id)
                    ->where('is_active', true)->whereNull('revoked_at')->get()->each(fn ($membership) => DB::table('fnb_staff_role_scopes')
                    ->insertOrIgnore($base + ['scope_type' => 'outlet', 'scope_key' => $membership->outlet_id]));
            });
    }
}
