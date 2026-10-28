<?php

namespace Modules\Pharmacy\Security\Versions;

use App\Core\Modules\Support\ModuleSecurityDefinition;

/** Immutable security snapshot for the initial Pharmacy package. */
final class V0_1_0Dev1
{
    public static function definition(): ModuleSecurityDefinition
    {
        $permissions = [
            'pharmacy.view' => ['name' => 'pharmacy.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.settings' => ['name' => 'pharmacy.settings', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.professional.manage' => ['name' => 'pharmacy.professional.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.product.manage' => ['name' => 'pharmacy.product.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.price.manage' => ['name' => 'pharmacy.price.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.patient.view' => ['name' => 'pharmacy.patient.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.patient.manage' => ['name' => 'pharmacy.patient.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.patient.sensitive' => ['name' => 'pharmacy.patient.sensitive', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.prescription.view' => ['name' => 'pharmacy.prescription.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.prescription.manage' => ['name' => 'pharmacy.prescription.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.prescription.transfer' => ['name' => 'pharmacy.prescription.transfer', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.dispensing.view' => ['name' => 'pharmacy.dispensing.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.dispensing.manage' => ['name' => 'pharmacy.dispensing.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.dispensing.review' => ['name' => 'pharmacy.dispensing.review', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.dispensing.handover' => ['name' => 'pharmacy.dispensing.handover', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.sale.create' => ['name' => 'pharmacy.sale.create', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.sale.view' => ['name' => 'pharmacy.sale.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.payment.collect' => ['name' => 'pharmacy.payment.collect', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.shift.manage' => ['name' => 'pharmacy.shift.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.return.manage' => ['name' => 'pharmacy.return.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.inventory.view' => ['name' => 'pharmacy.inventory.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.inventory.manage' => ['name' => 'pharmacy.inventory.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.receipt.manage' => ['name' => 'pharmacy.receipt.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.purchasing.manage' => ['name' => 'pharmacy.purchasing.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.quality.manage' => ['name' => 'pharmacy.quality.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.recall.manage' => ['name' => 'pharmacy.recall.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.transfer.manage' => ['name' => 'pharmacy.transfer.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.stocktake.manage' => ['name' => 'pharmacy.stocktake.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.report.view' => ['name' => 'pharmacy.report.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.cost.view' => ['name' => 'pharmacy.cost.view', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'low'],
            'pharmacy.integrations.manage' => ['name' => 'pharmacy.integrations.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.documents.manage' => ['name' => 'pharmacy.documents.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.export' => ['name' => 'pharmacy.export', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.controlled.manage' => ['name' => 'pharmacy.controlled.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.online.manage' => ['name' => 'pharmacy.online.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
            'pharmacy.knowledge.manage' => ['name' => 'pharmacy.knowledge.manage', 'description' => 'Quyền Core cần kết hợp phạm vi Nhà thuốc và điều kiện nghiệp vụ.', 'risk_level' => 'high'],
        ];
        $roles = [
            'pharmacy.manager' => ['name' => 'Nhà thuốc: manager', 'description' => 'Không thay thế membership và hồ sơ chuyên môn.', 'permissions' => ['pharmacy.view', 'pharmacy.settings', 'pharmacy.professional.manage', 'pharmacy.product.manage', 'pharmacy.price.manage', 'pharmacy.patient.view', 'pharmacy.patient.manage', 'pharmacy.patient.sensitive', 'pharmacy.prescription.view', 'pharmacy.prescription.manage', 'pharmacy.prescription.transfer', 'pharmacy.dispensing.view', 'pharmacy.dispensing.manage', 'pharmacy.dispensing.review', 'pharmacy.dispensing.handover', 'pharmacy.sale.create', 'pharmacy.sale.view', 'pharmacy.payment.collect', 'pharmacy.shift.manage', 'pharmacy.return.manage', 'pharmacy.inventory.view', 'pharmacy.inventory.manage', 'pharmacy.receipt.manage', 'pharmacy.purchasing.manage', 'pharmacy.quality.manage', 'pharmacy.recall.manage', 'pharmacy.transfer.manage', 'pharmacy.stocktake.manage', 'pharmacy.report.view', 'pharmacy.cost.view', 'pharmacy.integrations.manage', 'pharmacy.documents.manage', 'pharmacy.export', 'pharmacy.controlled.manage', 'pharmacy.online.manage', 'pharmacy.knowledge.manage']],
            'pharmacy.pharmacist' => ['name' => 'Nhà thuốc: pharmacist', 'description' => 'Không thay thế membership và hồ sơ chuyên môn.', 'permissions' => ['pharmacy.view', 'pharmacy.patient.view', 'pharmacy.patient.manage', 'pharmacy.patient.sensitive', 'pharmacy.prescription.view', 'pharmacy.prescription.manage', 'pharmacy.prescription.transfer', 'pharmacy.dispensing.view', 'pharmacy.dispensing.manage', 'pharmacy.dispensing.review', 'pharmacy.dispensing.handover', 'pharmacy.sale.create', 'pharmacy.sale.view', 'pharmacy.inventory.view', 'pharmacy.documents.manage']],
            'pharmacy.cashier' => ['name' => 'Nhà thuốc: cashier', 'description' => 'Không thay thế membership và hồ sơ chuyên môn.', 'permissions' => ['pharmacy.view', 'pharmacy.dispensing.view', 'pharmacy.sale.view', 'pharmacy.sale.create', 'pharmacy.payment.collect', 'pharmacy.shift.manage', 'pharmacy.documents.manage']],
            'pharmacy.stock' => ['name' => 'Nhà thuốc: stock', 'description' => 'Không thay thế membership và hồ sơ chuyên môn.', 'permissions' => ['pharmacy.view', 'pharmacy.inventory.view', 'pharmacy.inventory.manage', 'pharmacy.receipt.manage', 'pharmacy.transfer.manage', 'pharmacy.stocktake.manage']],
            'pharmacy.reader' => ['name' => 'Nhà thuốc: reader', 'description' => 'Không thay thế membership và hồ sơ chuyên môn.', 'permissions' => ['pharmacy.view', 'pharmacy.report.view']],
        ];

        return new ModuleSecurityDefinition('0.1.0-dev.1', $permissions, $roles,
            ['native_scope' => ['required' => true], 'professional_qualification' => ['owner_bypass' => false], 'managed_inventory' => ['fail_closed' => true]],
            ['pharmacy.workspace.v1' => ['enabled' => true]], customRolePolicy: 'allow', assignmentChannel: 'core');
    }
}
