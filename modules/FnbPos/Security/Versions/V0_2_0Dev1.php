<?php

namespace Modules\FnbPos\Security\Versions;

use App\Core\Modules\Support\ModuleSecurityDefinition;

final class V0_2_0Dev1
{
    public static function definition(): ModuleSecurityDefinition
    {
        $base = V0_1_0::definition();
        $permissions = $base->permissions;
        foreach ([
            'fnb.chain.view' => ['Xem chuỗi cửa hàng', 'normal'],
            'fnb.chain.manage' => ['Quản lý thương hiệu, vùng và cửa hàng', 'critical'],
            'fnb.chain.staff.manage' => ['Phân quyền nhân viên trụ sở', 'critical'],
            'fnb.menu.master.manage' => ['Quản lý thực đơn toàn chuỗi', 'sensitive'],
            'fnb.recipe.master.manage' => ['Quản lý công thức toàn chuỗi', 'sensitive'],
        ] as $key => [$label, $risk]) {
            $permissions[$key] = ['name' => $label, 'description' => null, 'risk_level' => $risk];
        }
        $roles = $base->roles;
        foreach (['fnb-manager', 'fnb-stockkeeper'] as $roleKey) {
            $roles[$roleKey]['permissions'] = array_values(array_diff($roles[$roleKey]['permissions'], ['fnb.menu.manage', 'fnb.menu.publish', 'fnb.recipe.manage']));
        }
        $roles['fnb-owner']['permissions'] = array_keys($permissions);
        $roles['fnb-owner']['name'] = 'Chủ chuỗi F&B';
        $roles['fnb-hq-manager'] = [
            'name' => 'Quản lý trụ sở F&B', 'description' => 'Quản lý vận hành và dữ liệu toàn chuỗi; không cấp quyền trụ sở.',
            'permissions' => array_values(array_diff(array_keys($permissions), ['fnb.chain.staff.manage'])),
        ];
        $policies = $base->criticalPolicies + [
            'chain.menu.change' => ['executor' => ['fnb.menu.master.manage'], 'approver' => [], 'reauth' => true],
            'chain.change' => ['executor' => ['fnb.chain.manage'], 'approver' => [], 'reauth' => true],
            'chain.staff.change' => ['executor' => ['fnb.chain.staff.manage'], 'approver' => [], 'reauth' => true],
            'check.close_zero_charge' => ['executor' => ['fnb.payment.collect'], 'approver' => [], 'reauth' => true],
        ];

        return new ModuleSecurityDefinition('0.2.0-dev.1', $permissions, $roles, $policies,
            $base->features + ['chain_management' => ['enabled' => true, 'introduced_version' => '0.2.0-dev.1']],
            'deny', 'fnb_dedicated');
    }
}
