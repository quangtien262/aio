<?php

namespace App\Console\Commands;

use App\Core\Modules\ModuleManager;
use App\Models\ModuleLifecycleOperation;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ModuleLifecycleRecoveryCommand extends Command
{
    protected $signature = 'modules:lifecycle {module? : Filter by module key} {--resume= : Resume this exact operation UUID} {--json : Print status as JSON}';

    protected $description = 'Inspect module lifecycle operations or resume one interrupted operation without rolling back schema.';

    public function handle(ModuleManager $manager, AuditLogger $audit): int
    {
        if (! Schema::hasTable('module_lifecycle_operations')) {
            $this->error('Core lifecycle migration has not been applied.');

            return self::FAILURE;
        }
        if ($operationId = $this->option('resume')) {
            validator(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
            $operation = ModuleLifecycleOperation::query()->where('operation_id', $operationId)->firstOrFail();
            if ($this->argument('module') && $operation->module_key !== $this->argument('module')) {
                $this->error('Operation does not belong to the selected module.');

                return self::FAILURE;
            }
            $manager->resume($operationId);
            $audit->record('module.lifecycle.resumed', 'module_lifecycle_operations', after: [
                'operation_id' => $operationId, 'module_key' => $operation->module_key, 'source' => 'console',
            ], moduleKey: $operation->module_key);
        }
        $rows = ModuleLifecycleOperation::query()->when($this->argument('module'), fn ($q, $module) => $q->where('module_key', $module))
            ->latest('id')->limit(50)->get(['operation_id', 'module_key', 'operation', 'status', 'from_version', 'target_version', 'heartbeat_at']);
        if ($this->option('json')) {
            $this->line($rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Operation ID', 'Module', 'Operation', 'Status', 'From', 'Target', 'Heartbeat'], $rows->toArray());
        }

        return self::SUCCESS;
    }
}
