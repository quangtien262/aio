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
        $permissionKeys = [
            'cms.product.view',
            'cms.product.create',
            'cms.product.update',
            'cms.product.delete',
        ];

        foreach ($permissionKeys as $permissionKey) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permissionKey],
                [
                    'name' => PermissionLabel::make($permissionKey),
                    'module_key' => 'cms',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $cmsRoleExists = ! Schema::hasTable('module_installations')
            || DB::table('module_installations')->where('key', 'cms')->exists();

        if ($cmsRoleExists) {
            DB::table('roles')->updateOrInsert(
                ['key' => 'cms.publisher'],
                [
                    'name' => 'CMS Publisher',
                    'description' => 'Quản trị nội dung, sản phẩm, xuất bản page/post và vận hành media/menu của CMS.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        $roleIds = DB::table('roles')
            ->whereIn('key', ['super-admin', 'cms.publisher'])
            ->pluck('id')
            ->all();

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
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
    }

    public function down(): void
    {
    }
};