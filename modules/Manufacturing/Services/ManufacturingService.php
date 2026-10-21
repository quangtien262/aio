<?php

namespace Modules\Manufacturing\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Commands\ManufacturingCommandService;
use Modules\Manufacturing\Services\Costing\CostingService;
use Modules\Manufacturing\Services\Engineering\EngineeringService;
use Modules\Manufacturing\Services\Execution\ExecutionService;
use Modules\Manufacturing\Services\Execution\MaterialService;
use Modules\Manufacturing\Services\Execution\SubcontractService;
use Modules\Manufacturing\Services\Fulfillment\FulfillmentService;
use Modules\Manufacturing\Services\Planning\PlanningScheduleService;
use Modules\Manufacturing\Services\Planning\PlanningService;
use Modules\Manufacturing\Services\Quality\QualityService;
use Modules\Manufacturing\Services\Read\ManufacturingReadService;
use Modules\Manufacturing\Services\Setup\ManufacturingSetupService;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ManufacturingService
{
    public function bootstrap(Admin $actor, ?string $factory = null): array
    {
        $data = app(ManufacturingAccessService::class)->bootstrap($actor, $factory);
        if ($data['must_change_password']) {
            return $data;
        }
        $cap = app(ModuleCapabilityChecker::class);
        $data['readiness'] = [
            'can_open_app_store' => $actor->canAccess('store.module.view', 'global'),
            'inventory' => $cap->has('inventory', 'inventory.production.post.v1'),
            'merchandise' => $cap->has('merchandise', 'merchandise.variants.manage.v1'),
            'purchasing' => $cap->has('purchasing', 'purchasing.production.requisitions.v1') && $cap->has('purchasing', 'purchasing.receipt_lines.identity.v1'),
            'offline' => false, 'accounting' => false, 'payroll' => false,
        ];
        $data['readiness']['blockers'] = [];
        foreach (['inventory' => 'Cần cài hoặc nâng cấp Quản lý kho để hỗ trợ nghiệp vụ sản xuất.',
            'merchandise' => 'Cần cài hoặc nâng cấp Danh mục hàng hóa dùng chung để hỗ trợ biến thể hàng hóa.',
            'purchasing' => 'Cần cài và bật Mua hàng và nhà cung cấp để vận hành mua nguyên liệu FOB.'] as $module => $message) {
            if (! $data['readiness'][$module]) {
                $data['readiness']['blockers'][] = ['code' => $module, 'message' => $message];
            }
        }
        $data['contract_version'] = 'manufacturing.v1';

        return $data;
    }

    public function read(Admin $actor, string $kind, ?string $factory, array $query): array
    {
        if (in_array($kind, ['plan-capacity', 'plan-calendar', 'plan-history', 'engineering-impact'], true)) {
            $access = app(ManufacturingAccessService::class);
            $live = $access->actor($actor);
            $selected = $access->factory($live, (string) $factory, $kind === 'engineering-impact' ? 'manufacturing.engineering' : 'manufacturing.plan');

            return app(PlanningScheduleService::class)->read($live, $selected, $kind, $query);
        }

        return app(ManufacturingReadService::class)->read($actor, $kind, $factory, $query);
    }

    public function command(Admin $actor, string $operation, array $payload, string $key): array
    {
        $data = $payload['data'] ?? [];
        abort_unless(is_array($data) && count($data) <= 100, 422, 'Payload không hợp lệ.');
        $resource = $payload['resource'] ?? null;
        abort_unless($resource === null || (is_string($resource) && Str::isUuid($resource)), 422, 'Resource cần UUID hợp lệ.');
        $permission = $this->permission($operation);

        return app(ManufacturingCommandService::class)->run($actor, $operation, $payload, $key,
            function (Admin $live) use ($operation, $permission, $payload, $data): array {
                $access = app(ManufacturingAccessService::class);
                $access->permission($live, $permission);
                if ($operation === 'company.create') {
                    return ['company' => null, 'factory' => null, 'permission' => $permission];
                }
                if (in_array($operation, ['runtime.pause', 'runtime.resume'], true)) {
                    abort_unless($live->isSuperAdmin(), 403, 'Tạm dừng toàn app cần quyền System Owner hoặc quản trị toàn hệ thống.');

                    return ['company' => null, 'factory' => null, 'permission' => $permission];
                }
                if ($operation === 'factory.create') {
                    $company = $access->company($live, (string) ($data['company_id'] ?? ''), $permission, true);

                    return ['company' => $company, 'factory' => null, 'permission' => $permission];
                }
                $factory = $access->factory($live, (string) ($payload['factory_id'] ?? ''), $permission, true);
                $company = DB::table('mfg_companies')->where('id', $factory->company_id)->first();
                if (str_starts_with($operation, 'style.') || str_starts_with($operation, 'sample.')) {
                    $access->company($live, (int) $company->id, $permission, true);
                }
                if ($operation === 'subcontract.dispatch') {
                    $access->factory($live, (int) $factory->id, 'manufacturing.cost.manage');
                }

                return ['company' => $company, 'factory' => $factory, 'permission' => $permission];
            }, function (Admin $live, array $scope) use ($operation, $data, $resource, $key): array {
                // Shared providers have globally unique journals; the native journal is actor-scoped.
                $key = 'mfg:'.hash('sha256', $live->id.'|'.$key);
                $company = $scope['company'];
                $factory = $scope['factory'];
                $result = match ($operation) {
                    'company.create', 'factory.create', 'workshop.create', 'line.create', 'shift.create', 'membership.save', 'runtime.pause', 'runtime.resume', 'sku.save' => app(ManufacturingSetupService::class)->dispatch($live, $operation, $scope, $data, $resource),
                    'style.save' => app(EngineeringService::class)->save($company, $data, $resource),
                    'style.approve' => app(EngineeringService::class)->approve($company, (string) $resource, $data, $live),
                    'sample.save' => app(EngineeringService::class)->sample($company, $data, $resource, $live),
                    'sample.approve' => app(EngineeringService::class)->sample($company, $data, $resource, $live, true),
                    'demand.save' => app(PlanningService::class)->demand($factory, $data, $resource),
                    'order.save' => app(PlanningService::class)->order($factory, $data, $resource, $live),
                    'order.release' => app(PlanningService::class)->release($factory, (string) $resource, $data, $live),
                    'order.cancel' => app(PlanningService::class)->cancel($factory, (string) $resource, $data),
                    'plan.calendar.save', 'plan.commit', 'plan.reschedule' => app(PlanningScheduleService::class)->dispatch($live, $factory, $operation, $data, $resource),
                    'cut.create' => app(ExecutionService::class)->cut($factory, $data, $live, $key),
                    'bundle.split' => app(ExecutionService::class)->split($factory, (string) $resource, $data, $live),
                    'bundle.merge' => app(ExecutionService::class)->merge($factory, (string) $resource, $data, $live),
                    'operation.record' => app(ExecutionService::class)->operation($factory, (string) $resource, $data, $live),
                    'bundle.handover' => app(ExecutionService::class)->handover($factory, (string) $resource, $data, $live),
                    'handover.receive' => app(ExecutionService::class)->receiveHandover($factory, (string) $resource, $data, $live),
                    'quality.inspect' => app(QualityService::class)->inspect($factory, $data + ['bundle_id' => $resource], $live),
                    'quality.rework' => app(QualityService::class)->rework($factory, $data + ['bundle_id' => $resource], $live),
                    'quality.hold', 'quality.release' => app(QualityService::class)->hold($factory, (string) $resource, $data, $live, $operation === 'quality.release'),
                    'subcontract.dispatch' => app(SubcontractService::class)->dispatch($factory, $data + ['bundle_id' => $resource], $live),
                    'subcontract.receive' => app(SubcontractService::class)->receive($factory, (string) $resource, $data, $live),
                    'bundle.finish' => app(FulfillmentService::class)->finish($factory, (string) $resource, $data, $live, $key),
                    'shipment.pack' => app(FulfillmentService::class)->pack($factory, $data, $live),
                    'shipment.dispatch' => app(FulfillmentService::class)->dispatch($factory, (string) $resource, $data, $live, $key),
                    'shipment.cancel' => app(FulfillmentService::class)->cancel($factory, (string) $resource, $data, $live),
                    'costing.record' => app(CostingService::class)->record($factory, $data, $live),
                    'costing.close' => app(CostingService::class)->close($factory, (string) ($resource ?? $data['order_id'] ?? ''), $data, $live),
                    'costing.adjust' => app(CostingService::class)->adjust($factory, (string) ($resource ?? $data['order_id'] ?? ''), $data, $live, $key),
                    default => app(MaterialService::class)->run($factory, $operation, $resource, $data, $live, $key),
                };
                $result['id'] = $result['public_id'] ?? $result['id'] ?? null;
                app(ManufacturingCommandService::class)->emit($scope, 'manufacturing.'.$operation, explode('.', $operation)[0],
                    (string) ($result['public_id'] ?? $key), (int) ($result['version'] ?? 1), ['id' => $result['public_id'] ?? null, 'status' => $result['status'] ?? null]);

                // Mutations return the same confidentiality boundary as list/detail reads.
                $canCost = $live->canAccess('manufacturing.cost.view', 'global');
                if ($canCost && $factory) {
                    try {
                        app(ManufacturingAccessService::class)->factory($live, (int) $factory->id, 'manufacturing.cost.view');
                    } catch (HttpException $e) {
                        if ($e->getStatusCode() !== 403) {
                            throw $e;
                        }
                        $canCost = false;
                    }
                }

                return $canCost ? $result : ManufacturingData::withoutCosts($result);
            });
    }

    private function permission(string $operation): string
    {
        return match ($operation) {
            'company.create', 'factory.create', 'workshop.create', 'line.create', 'shift.create', 'membership.save', 'runtime.pause', 'runtime.resume', 'sku.save', 'material.receive', 'material.stocktake' => 'manufacturing.settings',
            'style.save', 'style.approve', 'sample.save', 'sample.approve' => 'manufacturing.engineering',
            'demand.save', 'order.save', 'order.release', 'order.cancel', 'plan.calendar.save', 'plan.commit', 'plan.reschedule', 'procurement.create', 'procurement.approve', 'procurement.receive', 'procurement.return' => 'manufacturing.plan',
            'material.inspect', 'quality.inspect', 'quality.rework', 'quality.hold', 'quality.release' => 'manufacturing.quality',
            'bundle.finish', 'shipment.pack', 'shipment.dispatch', 'shipment.cancel' => 'manufacturing.fulfill',
            'costing.record', 'costing.close', 'costing.adjust' => 'manufacturing.cost.manage',
            'material.reserve', 'material.issue', 'material.release', 'material.return', 'cut.create', 'bundle.split', 'bundle.merge', 'operation.record', 'bundle.handover', 'handover.receive', 'subcontract.dispatch', 'subcontract.receive' => 'manufacturing.execute',
            default => abort(404, 'Lệnh sản xuất không được hỗ trợ.'),
        };
    }
}
