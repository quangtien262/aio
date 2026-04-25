<?php

use App\Support\PermissionLabel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'cms.order.view'],
            [
                'name' => PermissionLabel::make('cms.order.view'),
                'module_key' => 'cms',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')->where('key', 'cms.order.view')->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('key', ['super-admin', 'cms.publisher'])
            ->pluck('id')
            ->all();

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
    }
};
