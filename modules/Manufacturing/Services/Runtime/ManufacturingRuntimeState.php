<?php

namespace Modules\Manufacturing\Services\Runtime;

use App\Core\Modules\Support\ModuleRuntimeBarrier;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class ManufacturingRuntimeState extends ModuleRuntimeBarrier
{
    protected const MODULE_KEY = 'manufacturing';

    protected const TABLE = 'mfg_runtime_states';

    public function guard(bool $lock = true): void
    {
        parent::guard($lock);
        if ($lock) {
            abort_if((bool) DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->value('maintenance_paused'), 423, 'Sản xuất đang tạm dừng ghi để bảo trì.');
        }
    }

    /** Pause/resume is a command too, but must be able to exit a paused state. */
    public function guardMaintenanceCommand(): void
    {
        parent::guard(true);
    }

    public function setPaused(Admin $actor, bool $paused, ?string $reason = null): array
    {
        abort_unless($actor->canAccess('manufacturing.settings', 'global'), 403);
        abort_unless($actor->isSuperAdmin(), 403, 'Tạm dừng toàn app cần tài khoản quản trị nền tảng.');

        return DB::transaction(function () use ($actor, $paused, $reason): array {
            parent::guard(true);
            $row = DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->lockForUpdate()->first();
            DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->update([
                'maintenance_paused' => $paused, 'paused_by' => $actor->id, 'pause_reason' => $reason,
                'version' => $row->version + 1, 'updated_at' => now(),
            ]);

            return ['status' => $paused ? 'paused' : 'active', 'version' => $row->version + 1];
        }, 3);
    }

    public function state(): array
    {
        $row = DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->first();

        return ['status' => $row?->operational_state ?? 'unavailable', 'paused' => (bool) ($row?->maintenance_paused ?? false), 'version' => (int) ($row?->version ?? 0), 'reason' => $row?->pause_reason];
    }
}
