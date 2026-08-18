<?php

use App\Support\PermissionLabel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile installations created from legacy Catalog data with the same
     * permission state produced by ModuleManager::install().
     */
    public function up(): void
    {
        if (
            ! Schema::hasTable('module_installations')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('permission_role')
        ) {
            return;
        }

        $status = DB::table('module_installations')->where('key', 'catalog')->value('status');

        if (! in_array($status, ['installed', 'enabled'], true)) {
            return;
        }

        $now = now();
        $permissionKeys = ['catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete'];

        foreach ($permissionKeys as $permissionKey) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permissionKey],
                [
                    'name' => PermissionLabel::make($permissionKey),
                    'description' => null,
                    'module_key' => 'catalog',
                    'risk_level' => 'normal',
                    'is_active' => true,
                    'deprecated_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('key', $permissionKeys)->pluck('id');

        DB::table('roles')
            ->whereIn('key', ['super-admin', 'platform-owner'])
            ->pluck('id')
            ->each(function ($roleId) use ($permissionIds, $now): void {
                $permissionIds->each(fn ($permissionId) => DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now],
                ));
            });
    }

    public function down(): void
    {
        // Data reconciliation only: rolling back must not revoke live access.
    }
};
