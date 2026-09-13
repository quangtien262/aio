<?php

namespace Modules\FnbPos\Security\Versions;

use App\Core\Modules\Support\ModuleSecurityDefinition;
use App\Support\PermissionLabel;

final class V0_1_0
{
    public static function definition(string $version = '0.1.0'): ModuleSecurityDefinition
    {
        return new ModuleSecurityDefinition(
            version: $version,
            permissions: self::permissions(),
            roles: self::roles(),
            criticalPolicies: self::criticalPolicies(),
            features: [
                'session_merge' => ['enabled' => false, 'introduced_version' => '0.1.1'],
                'inventory_mapping' => ['enabled' => false, 'introduced_version' => '0.1.2'],
                'accounting_mapping' => ['enabled' => false, 'introduced_version' => '0.1.2'],
                'integration_connection' => ['enabled' => false, 'introduced_version' => '0.1.2'],
                'integration_retry' => ['enabled' => false, 'introduced_version' => '0.1.2'],
            ],
            customRolePolicy: 'deny',
            assignmentChannel: 'fnb_dedicated',
        );
    }

    /** @return array<string, array{name:string,description:?string,risk_level:string}> */
    private static function permissions(): array
    {
        $risks = [
            'fnb.dashboard.view' => 'normal',
            'fnb.outlet.view' => 'normal',
            'fnb.outlet.manage' => 'critical',
            'fnb.terminal.manage' => 'critical',
            'fnb.staff.view' => 'sensitive',
            'fnb.staff.assign' => 'critical',
            'fnb.floor.view' => 'normal',
            'fnb.floor.manage' => 'sensitive',
            'fnb.menu.view' => 'normal',
            'fnb.menu.manage' => 'sensitive',
            'fnb.menu.publish' => 'sensitive',
            'fnb.menu.availability.update' => 'sensitive',
            'fnb.price.override' => 'critical',
            'fnb.order.view' => 'normal',
            'fnb.order.create' => 'normal',
            'fnb.order.update' => 'normal',
            'fnb.order.submit' => 'normal',
            'fnb.order.transfer' => 'normal',
            'fnb.order.split' => 'sensitive',
            'fnb.order.void' => 'critical',
            'fnb.order.void.approve' => 'critical',
            'fnb.order.reopen' => 'critical',
            'fnb.kitchen.view' => 'normal',
            'fnb.kitchen.update' => 'normal',
            'fnb.kitchen.priority' => 'sensitive',
            'fnb.payment.collect' => 'sensitive',
            'fnb.payment.refund' => 'critical',
            'fnb.payment.refund.approve' => 'critical',
            'fnb.discount.apply' => 'sensitive',
            'fnb.discount.override' => 'critical',
            'fnb.shift.view' => 'normal',
            'fnb.shift.open' => 'normal',
            'fnb.shift.close' => 'sensitive',
            'fnb.shift.reconcile' => 'critical',
            'fnb.cash.adjust' => 'critical',
            'fnb.recipe.view' => 'normal',
            'fnb.recipe.manage' => 'sensitive',
            'fnb.stock.view' => 'normal',
            'fnb.waste.record' => 'sensitive',
            'fnb.consumption.reverse' => 'critical',
            'fnb.customer.lookup' => 'sensitive',
            'fnb.customer.create' => 'sensitive',
            'fnb.customer.attach' => 'normal',
            'fnb.customer.view' => 'sensitive',
            'fnb.customer.update' => 'sensitive',
            'fnb.customer.merge' => 'critical',
            'fnb.report.operations.view' => 'normal',
            'fnb.report.financial.view' => 'sensitive',
            'fnb.report.export' => 'sensitive',
            'fnb.integration.view' => 'normal',
            'fnb.audit.view' => 'sensitive',
            'fnb.settings.view' => 'normal',
            'fnb.settings.manage' => 'critical',
        ];

        return collect($risks)->mapWithKeys(fn (string $risk, string $key): array => [
            $key => [
                'name' => PermissionLabel::make($key),
                'description' => null,
                'risk_level' => $risk,
            ],
        ])->all();
    }

    /** @return array<string, array{name:string,description:?string,permissions:list<string>}> */
    private static function roles(): array
    {
        return [
            'fnb-owner' => self::role('Chủ quán F&B', [
                'fnb.dashboard.view', 'fnb.outlet.view', 'fnb.outlet.manage', 'fnb.terminal.manage',
                'fnb.staff.view', 'fnb.staff.assign', 'fnb.floor.view', 'fnb.floor.manage',
                'fnb.menu.view', 'fnb.menu.manage', 'fnb.menu.publish', 'fnb.menu.availability.update',
                'fnb.price.override', 'fnb.order.view', 'fnb.order.create', 'fnb.order.update',
                'fnb.order.submit', 'fnb.order.transfer', 'fnb.order.split', 'fnb.order.void',
                'fnb.order.void.approve', 'fnb.order.reopen', 'fnb.kitchen.view', 'fnb.kitchen.update',
                'fnb.kitchen.priority', 'fnb.payment.collect', 'fnb.payment.refund',
                'fnb.payment.refund.approve', 'fnb.discount.apply', 'fnb.discount.override',
                'fnb.shift.view', 'fnb.shift.open', 'fnb.shift.close', 'fnb.shift.reconcile',
                'fnb.cash.adjust', 'fnb.recipe.view', 'fnb.recipe.manage', 'fnb.stock.view',
                'fnb.waste.record', 'fnb.consumption.reverse', 'fnb.customer.lookup',
                'fnb.customer.create', 'fnb.customer.attach', 'fnb.customer.view',
                'fnb.customer.update', 'fnb.customer.merge', 'fnb.report.operations.view',
                'fnb.report.financial.view', 'fnb.report.export', 'fnb.integration.view',
                'fnb.audit.view', 'fnb.settings.view', 'fnb.settings.manage',
            ]),
            'fnb-manager' => self::role('Quản lý quán F&B', [
                'fnb.dashboard.view', 'fnb.outlet.view', 'fnb.staff.view', 'fnb.staff.assign',
                'fnb.floor.view', 'fnb.floor.manage', 'fnb.menu.view', 'fnb.menu.manage',
                'fnb.menu.publish', 'fnb.menu.availability.update', 'fnb.price.override',
                'fnb.order.view', 'fnb.order.create', 'fnb.order.update', 'fnb.order.submit',
                'fnb.order.transfer', 'fnb.order.split', 'fnb.order.void', 'fnb.order.void.approve',
                'fnb.order.reopen', 'fnb.kitchen.view', 'fnb.kitchen.update', 'fnb.kitchen.priority',
                'fnb.payment.collect', 'fnb.payment.refund', 'fnb.payment.refund.approve',
                'fnb.discount.apply', 'fnb.discount.override', 'fnb.shift.view', 'fnb.shift.open',
                'fnb.shift.close', 'fnb.shift.reconcile', 'fnb.cash.adjust', 'fnb.recipe.view',
                'fnb.recipe.manage', 'fnb.stock.view', 'fnb.waste.record', 'fnb.consumption.reverse',
                'fnb.customer.lookup', 'fnb.customer.create', 'fnb.customer.attach',
                'fnb.customer.view', 'fnb.customer.update', 'fnb.customer.merge',
                'fnb.report.operations.view', 'fnb.report.financial.view', 'fnb.report.export',
                'fnb.integration.view', 'fnb.audit.view', 'fnb.settings.view',
            ]),
            'fnb-cashier' => self::role('Thu ngân F&B', [
                'fnb.dashboard.view', 'fnb.outlet.view', 'fnb.floor.view', 'fnb.menu.view',
                'fnb.order.view', 'fnb.order.create', 'fnb.order.update', 'fnb.order.submit',
                'fnb.order.transfer', 'fnb.order.split', 'fnb.kitchen.view', 'fnb.payment.collect',
                'fnb.discount.apply', 'fnb.shift.view', 'fnb.shift.open', 'fnb.shift.close',
                'fnb.customer.lookup', 'fnb.customer.create', 'fnb.customer.attach',
            ]),
            'fnb-waiter' => self::role('Phục vụ F&B', [
                'fnb.outlet.view', 'fnb.floor.view', 'fnb.menu.view', 'fnb.order.view',
                'fnb.order.create', 'fnb.order.update', 'fnb.order.submit',
                'fnb.order.transfer', 'fnb.kitchen.view',
            ]),
            'fnb-kitchen' => self::role('Bếp/Bar F&B', [
                'fnb.outlet.view', 'fnb.menu.view', 'fnb.menu.availability.update',
                'fnb.kitchen.view', 'fnb.kitchen.update', 'fnb.recipe.view',
            ]),
            'fnb-stockkeeper' => self::role('Thủ kho F&B', [
                'fnb.outlet.view', 'fnb.menu.view', 'fnb.menu.availability.update',
                'fnb.recipe.view', 'fnb.recipe.manage', 'fnb.stock.view',
                'fnb.waste.record', 'fnb.report.operations.view', 'fnb.integration.view',
            ]),
            'fnb-accountant' => self::role('Kế toán F&B', [
                'fnb.dashboard.view', 'fnb.outlet.view', 'fnb.menu.view', 'fnb.order.view',
                'fnb.shift.view', 'fnb.report.financial.view', 'fnb.report.export',
                'fnb.audit.view', 'fnb.integration.view',
            ]),
            'fnb-viewer' => self::role('Người xem F&B', [
                'fnb.dashboard.view', 'fnb.outlet.view', 'fnb.menu.view',
                'fnb.report.financial.view',
            ]),
        ];
    }

    /** @param list<string> $permissions
     * @return array{name:string,description:?string,permissions:list<string>}
     */
    private static function role(string $name, array $permissions): array
    {
        return ['name' => $name, 'description' => null, 'permissions' => $permissions];
    }

    /** @return array<string, array<string, mixed>> */
    private static function criticalPolicies(): array
    {
        return [
            'outlet.change' => ['executor' => ['fnb.outlet.manage'], 'approver' => [], 'reauth' => true],
            'terminal.bind_or_revoke' => ['executor' => ['fnb.terminal.manage'], 'approver' => [], 'reauth' => true],
            'staff.assign' => ['executor' => ['fnb.staff.assign'], 'approver' => [], 'reauth' => true],
            'price.override' => ['executor' => ['fnb.price.override'], 'approver' => ['fnb.price.override'], 'reauth' => true, 'conditional_dual' => true],
            'order.void' => ['executor' => ['fnb.order.void'], 'approver' => ['fnb.order.void.approve'], 'reauth' => true, 'conditional_dual' => true],
            'order.void.approve' => ['executor' => [], 'approver' => ['fnb.order.void.approve'], 'reauth' => true, 'approver_only' => true],
            'fulfillment.compensate' => ['executor' => ['fnb.order.void', 'fnb.payment.refund'], 'approver' => ['fnb.order.void.approve', 'fnb.payment.refund.approve'], 'reauth' => true, 'conditional_dual' => true],
            'fulfillment.compensate.cancel' => ['executor' => ['fnb.order.void', 'fnb.payment.refund'], 'approver' => [], 'reauth' => true],
            'fulfillment.compensate.resolve' => ['executor' => ['fnb.order.void', 'fnb.payment.refund'], 'approver' => ['fnb.payment.refund.approve'], 'reauth' => true, 'conditional_dual' => true],
            'order.reopen' => ['executor' => ['fnb.order.reopen'], 'approver' => [], 'reauth' => true],
            'payment.refund' => ['executor' => ['fnb.payment.refund'], 'approver' => ['fnb.payment.refund.approve'], 'reauth' => true, 'conditional_dual' => true],
            'payment.refund.approve' => ['executor' => [], 'approver' => ['fnb.payment.refund.approve'], 'reauth' => true, 'approver_only' => true],
            'discount.override' => ['executor' => ['fnb.discount.apply'], 'approver' => ['fnb.discount.override'], 'reauth' => true, 'conditional_dual' => true],
            'shift.reconcile' => ['executor' => ['fnb.shift.close'], 'approver' => ['fnb.shift.reconcile'], 'reauth' => true, 'conditional_dual' => true],
            'cash.adjust' => ['executor' => ['fnb.cash.adjust'], 'approver' => ['fnb.cash.adjust'], 'reauth' => true, 'conditional_dual' => true],
            'consumption.reverse' => ['executor' => ['fnb.consumption.reverse'], 'approver' => ['fnb.consumption.reverse'], 'reauth' => true, 'dual' => true],
            'customer.merge' => ['executor' => ['fnb.customer.merge'], 'approver' => [], 'reauth' => true],
            'settings.change' => ['executor' => ['fnb.settings.manage'], 'approver' => [], 'reauth' => true],
        ];
    }
}
