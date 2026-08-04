<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        DB::table('roles')->updateOrInsert(
            ['key' => 'platform-owner'],
            [
                'name' => 'Administrator',
                'description' => 'Quyền quản trị cao nhất.',
                'is_system' => false,
                'is_assignable' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $roleId = DB::table('roles')->where('key', 'platform-owner')->value('id');

        if (! $roleId || ! Schema::hasTable('permission_role')) {
            return;
        }

        DB::table('permissions')
            ->when(
                Schema::hasColumn('permissions', 'is_active'),
                fn ($query) => $query->where('is_active', true),
            )
            ->pluck('id')
            ->each(fn ($permissionId) => DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now],
            ));
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('key', 'platform-owner')->value('id');

        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('admin_role_assignments')) {
            DB::table('admin_role_assignments')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }
};
