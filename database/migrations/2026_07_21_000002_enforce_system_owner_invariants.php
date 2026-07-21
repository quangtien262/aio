<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins') || ! DB::table('admins')->where('id', 1)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('admins')->where('id', '<>', 1)->update(['is_system_owner' => false]);
            DB::table('admins')->where('id', 1)->update([
                'is_system_owner' => true,
                'is_active' => true,
                'status' => 'active',
                'locked_at' => null,
                'locked_reason' => null,
                'must_change_password' => DB::raw('CASE WHEN password_changed_at IS NULL THEN 1 ELSE must_change_password END'),
                'auth_version' => DB::raw('auth_version + 1'),
                'updated_at' => now(),
            ]);

            $superAdminRoleId = DB::table('roles')->where('key', 'super-admin')->value('id');

            if ($superAdminRoleId === null || ! Schema::hasTable('admin_role_assignments')) {
                return;
            }

            DB::table('admin_role_assignments')
                ->where('role_id', $superAdminRoleId)
                ->where('admin_id', '<>', 1)
                ->delete();

            DB::table('admin_role_assignments')->updateOrInsert(
                [
                    'admin_id' => 1,
                    'role_id' => $superAdminRoleId,
                    'scope_type' => 'global',
                    'scope_value' => null,
                ],
                ['expires_at' => null, 'updated_at' => now(), 'created_at' => now()],
            );
        });
    }

    public function down(): void
    {
        // System Owner invariants are intentionally irreversible.
    }
};
