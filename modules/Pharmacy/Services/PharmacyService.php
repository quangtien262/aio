<?php

namespace Modules\Pharmacy\Services;

use App\Models\Admin;
use Modules\Pharmacy\Services\Administration\PharmacyAdministrationService;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\Documents\PharmacyDocumentService;
use Modules\Pharmacy\Services\Integrations\PharmacyAccountingAdapter;
use Modules\Pharmacy\Services\Integrations\PharmacyCustomerAdapter;
use Modules\Pharmacy\Services\Integrations\PharmacyIntegrationService;
use Modules\Pharmacy\Services\Integrations\PharmacyRecoveryService;
use Modules\Pharmacy\Services\Operations\PharmacyOperationsService;
use Modules\Pharmacy\Services\Prescriptions\PatientService;
use Modules\Pharmacy\Services\Prescriptions\PrescriptionService;
use Modules\Pharmacy\Services\Purchasing\PharmacyPurchasingService;
use Modules\Pharmacy\Services\Quality\PharmacyRecallService;
use Modules\Pharmacy\Services\Sales\PharmacySaleService;
use Modules\Pharmacy\Services\Setup\PharmacySetupService;
use Modules\Pharmacy\Services\Workflow\PharmacyWorkflowService;

final class PharmacyService
{
    public function command(Admin $actor, string $action, array $input, string $key): array
    {
        return match ($action) {
            'setup', 'pharmacy.create', 'product.save', 'unit.save', 'price.save', 'membership.save', 'credential.save', 'counter.save', 'settings.save' => app(PharmacySetupService::class)->dispatch($actor, $action, $input, $key),
            'patient.save' => app(PatientService::class)->save($actor, $input, $key),
            'patient.lookup' => app(PatientService::class)->lookup($actor, $input, $key),
            'prescription.save' => app(PrescriptionService::class)->save($actor, $input, $key),
            'prescription.verify' => app(PrescriptionService::class)->verify($actor, $input, $key),
            'prescription.transfer' => app(PrescriptionService::class)->transfer($actor, $input, $key),
            'prescription.transfer-revoke' => app(PrescriptionService::class)->revokeTransfer($actor, $input, $key),
            'dispensing.create' => app(DispensingService::class)->create($actor, $input, $key),
            'dispensing.update', 'dispensing.lines' => app(DispensingService::class)->replaceLines($actor, $input, $key),
            'dispensing.prepare' => app(DispensingService::class)->prepare($actor, $input, $key),
            'dispensing.review' => app(DispensingService::class)->review($actor, $input, $key),
            'dispensing.cancel' => app(DispensingService::class)->cancel($actor, $input, $key),
            'dispensing.handover' => app(DispensingService::class)->handover($actor, $input, $key),
            'dispensing.finalize' => app(PharmacySaleService::class)->finalize($actor, $input, $key),
            'dispensing.return' => app(PharmacySaleService::class)->returnSale($actor, $input, $key),
            'dispensing.refund' => app(PharmacySaleService::class)->refund($actor, $input, $key),
            'shift.open' => app(PharmacySaleService::class)->openShift($actor, $input, $key),
            'shift.close' => app(PharmacySaleService::class)->closeShift($actor, $input, $key),
            'receipt.create', 'receipt.inspect', 'quality.hold', 'quality.release', 'quality.disposition', 'quality.destroy', 'quality.supplier-return', 'recall.create', 'recall.confirm', 'temperature.record', 'transfer.create', 'transfer.approve', 'transfer.dispatch', 'transfer.receive', 'stocktake.create', 'stocktake.post' => app(PharmacyOperationsService::class)->dispatch($actor, $action, $input, $key),
            'supplier.save', 'purchasing.create', 'purchasing.approve', 'purchasing.receive', 'purchasing.return' => app(PharmacyPurchasingService::class)->dispatch($actor, $action, $input, $key),
            'connection.save', 'integration.queue', 'integration.reconcile' => app(PharmacyIntegrationService::class)->dispatch($actor, $action, $input, $key),
            'print.create', 'export.create' => app(PharmacyDocumentService::class)->command($actor, $action, $input, $key),
            'import.preview', 'import.commit', 'price.publish', 'filter.save' => app(PharmacyAdministrationService::class)->dispatch($actor, $action, $input, $key),
            'dispensing.assign', 'dispensing.parties', 'dispensing.counsel', 'dispensing.handover-failed', 'shift.cash-movement', 'replenishment.propose' => app(PharmacyWorkflowService::class)->dispatch($actor, $action, $input, $key),
            'accounting.mapping.save', 'accounting.draft' => app(PharmacyAccountingAdapter::class)->dispatch($actor, $action, $input, $key),
            'recovery.resolve' => app(PharmacyRecoveryService::class)->dispatch($actor, $action, $input, $key),
            'customer.mapping.save', 'customer.save', 'customer.lookup', 'customer.link' => app(PharmacyCustomerAdapter::class)->dispatch($actor, $action, $input, $key),
            'recall.reconcile' => app(PharmacyRecallService::class)->reconcile($actor, $input, $key),
            'recall.close' => app(PharmacyRecallService::class)->close($actor, $input, $key),
            default => abort(422, 'Thao tác Nhà thuốc chưa được hỗ trợ.'),
        };
    }
}
