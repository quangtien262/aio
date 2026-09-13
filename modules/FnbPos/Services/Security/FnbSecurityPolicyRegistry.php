<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\ModuleInstallation;
use Modules\FnbPos\Security\FnbVersionedSecurity;
use RuntimeException;

final class FnbSecurityPolicyRegistry
{
    /** @var array<string,string> */
    private const ACTION_ALIASES = [
        'onboard' => 'outlet.change',
        'outlet.save' => 'outlet.change',
        'terminal.save' => 'terminal.bind_or_revoke',
        'staff.assign' => 'staff.assign',
        'staff.revoke' => 'staff.assign',
        'line.void' => 'order.void',
        'check.void' => 'order.void',
        'check.reopen' => 'order.reopen',
        'order.void' => 'order.void',
        'payment.refund' => 'payment.refund',
        'refund.cancel' => 'payment.refund',
        'shift.reconcile' => 'shift.reconcile',
        'cash.record' => 'cash.adjust',
        'cash.adjust' => 'cash.adjust',
        'settings.save' => 'settings.change',
        'payment_method.save' => 'settings.change',
        'price.override' => 'price.override',
        'order.discount' => 'discount.override',
        'discount.override' => 'discount.override',
        'order.reopen' => 'order.reopen',
        'fulfillment.compensate' => 'fulfillment.compensate',
        'fulfillment.compensate.cancel' => 'fulfillment.compensate.cancel',
        'fulfillment.compensate.resolve' => 'fulfillment.compensate.resolve',
        'consumption.reverse' => 'consumption.reverse',
        'customer.merge' => 'customer.merge',
    ];

    public function __construct(private readonly FnbVersionedSecurity $security) {}

    /** @return array{key:string,version:int,module_version:string,executor:list<string>,approver:list<string>,reauth:bool,dual:bool,conditional_dual:bool,raw:array<string,mixed>} */
    public function forAction(string $action): array
    {
        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->first();
        if ($installation === null || $installation->status !== 'enabled') {
            throw new RuntimeException('F&B module is not enabled.');
        }

        $definition = $this->security->definition((string) $installation->version);
        $policyKey = self::ACTION_ALIASES[$action] ?? $action;
        $policy = $definition->criticalPolicies[$policyKey] ?? null;
        if (! is_array($policy)) {
            throw new RuntimeException("No F&B security policy exists for action [{$action}] at version [{$definition->version}].");
        }

        return [
            'key' => $policyKey,
            'version' => 1,
            'module_version' => $definition->version,
            'executor' => array_values($policy['executor'] ?? []),
            'approver' => array_values($policy['approver'] ?? []),
            'reauth' => (bool) ($policy['reauth'] ?? false),
            'dual' => (bool) ($policy['dual'] ?? false),
            'conditional_dual' => (bool) ($policy['conditional_dual'] ?? false),
            'raw' => $policy,
        ];
    }
}
