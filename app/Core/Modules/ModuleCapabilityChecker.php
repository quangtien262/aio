<?php

namespace App\Core\Modules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Runtime capabilities are checked afresh so long-lived workers see disable/drain. */
class ModuleCapabilityChecker
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function module(string $key): ?array
    {
        return $this->registry->find($key);
    }

    public function enabled(string $key): bool
    {
        if (! Schema::hasTable('module_installations')
            || DB::table('module_installations')->where('key', $key)->value('status') !== 'enabled') {
            return false;
        }

        return ! Schema::hasTable('module_lifecycle_operations')
            || ! DB::table('module_lifecycle_operations')->where('module_key', $key)->where('active_slot', 'active')->exists();
    }

    public function has(string $key, string $capability): bool
    {
        if (! $this->enabled($key)) {
            return false;
        }

        $module = $this->module($key);
        $contract = $module['provides'][$capability] ?? null;
        if ($contract === null) {
            return false;
        }

        $requiredVersion = is_array($contract) ? ($contract['introduced_version'] ?? null) : null;
        $installedVersion = DB::table('module_installations')->where('key', $key)->value('version');

        return $requiredVersion === null || version_compare((string) $installedVersion, $requiredVersion, '>=');
    }
}
