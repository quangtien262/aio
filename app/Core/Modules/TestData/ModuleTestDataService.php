<?php

namespace App\Core\Modules\TestData;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\Support\ModuleRuntimeBarrier;
use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ModuleTestDataService
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleCapabilityChecker $capabilities,
        private readonly ModuleLifecycleCoordinator $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function metadata(array $module, string $websiteKey): array
    {
        $provider = $this->provider($module);
        $ready = Schema::hasTable('module_test_data_batches');
        $batches = $ready ? DB::table('module_test_data_batches')->where('website_key', $websiteKey)->where('module_key', $module['key'])->where('status', 'active') : null;

        return [
            'supported' => $provider !== null,
            'storage_ready' => $ready,
            'preview' => $provider?->preview() ?? [],
            'batch_count' => $batches ? (clone $batches)->count() : 0,
            'record_count' => $batches ? (int) (clone $batches)->sum('record_count') : 0,
        ];
    }

    public function batches(string $websiteKey, ?string $moduleKey = null): array
    {
        $this->assertReady();
        $modules = $this->registry->all()->keyBy('key');

        return DB::table('module_test_data_batches')->where('website_key', $websiteKey)
            ->when($moduleKey, fn (Builder $query) => $query->where('module_key', $moduleKey))
            ->where('status', 'active')->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn ($batch) => [
                'id' => $batch->id, 'module_key' => $batch->module_key,
                'module_name' => $modules->get($batch->module_key)['name'] ?? $batch->module_key,
                'record_count' => (int) $batch->record_count,
                'created_at' => $batch->created_at,
                'preview' => json_decode($batch->preview, true, flags: JSON_THROW_ON_ERROR),
            ])->all();
    }

    public function create(string $moduleKey, string $websiteKey, Admin $actor, string $requestId): array
    {
        $this->assertReady();
        $module = $this->registry->find($moduleKey);
        abort_unless($module, 404, 'Không tìm thấy ứng dụng.');
        $provider = $this->provider($module);
        abort_unless($provider, 422, 'Ứng dụng chưa hỗ trợ tạo dữ liệu demo.');
        $keys = $this->dependencyKeys($moduleKey);

        return $this->withLocks($keys, fn () => DB::transaction(function () use ($moduleKey, $websiteKey, $actor, $requestId, $provider, $keys): array {
            $this->lockScope($websiteKey);
            foreach ($keys as $key) {
                abort_unless($this->capabilities->enabled($key), 409, 'Ứng dụng hoặc app phụ thuộc đang tắt/bảo trì. Vui lòng thử lại khi đang hoạt động.');
                $module = $this->registry->find($key);
                $runtime = $module['lifecycle_state_provider'] ?? null;
                if ($runtime && is_subclass_of($runtime, ModuleRuntimeBarrier::class)) {
                    app($runtime)->guard();
                }
            }
            $existing = DB::table('module_test_data_batches')->where([
                'website_key' => $websiteKey, 'module_key' => $moduleKey,
                'actor_admin_id' => $actor->id, 'request_id' => $requestId,
            ])->first();
            if ($existing) {
                return ['id' => $existing->id, 'record_count' => (int) $existing->record_count, 'status' => $existing->status];
            }
            $batchId = (string) Str::uuid();
            $references = method_exists($provider, 'references') ? $provider->references() : [];
            DB::table('module_test_data_batches')->insert([
                'id' => $batchId, 'website_key' => $websiteKey, 'module_key' => $moduleKey,
                'actor_admin_id' => $actor->id, 'request_id' => $requestId, 'status' => 'active',
                'record_count' => 0, 'preview' => json_encode($provider->preview(), JSON_THROW_ON_ERROR),
                'soft_references' => json_encode($references, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $provider->create(new ModuleTestDataContext($batchId, $moduleKey, $websiteKey, $actor));
            $count = DB::table('module_test_data_records')->where('batch_id', $batchId)->count();
            abort_unless($count > 0, 422, 'Ứng dụng không tạo được bản ghi test nào.');
            DB::table('module_test_data_batches')->where('id', $batchId)->update(['record_count' => $count]);
            $this->audit->record('module.test_data.created', $batchId, null, ['module_key' => $moduleKey, 'record_count' => $count], $actor, $moduleKey, $websiteKey);

            return ['id' => $batchId, 'record_count' => $count, 'status' => 'active'];
        }, 3));
    }

    /** Removes only ledger-owned rows. Every batch stays as an audit receipt. */
    public function remove(string $websiteKey, Admin $actor, ?string $moduleKey = null, ?string $batchId = null): array
    {
        $this->assertReady();
        // Lock every participating package before touching cross-module fixtures.
        // This also protects schemas from concurrent upgrade/uninstall operations.
        $keys = $this->registry->all()->pluck('key')->sort()->values()->all();

        return $this->withLocks($keys, fn () => DB::transaction(function () use ($websiteKey, $actor, $moduleKey, $batchId): array {
            $this->lockScope($websiteKey);
            // FNB commands share-lock installation/site settings; other native
            // writers share-lock their module runtime row. Take the matching
            // exclusive locks before checking soft references and deleting.
            DB::table('module_installations')->orderBy('key')->lockForUpdate()->get();
            foreach ($this->registry->all()->sortBy('key') as $module) {
                $runtime = $module['lifecycle_state_provider'] ?? null;
                if ($runtime && is_subclass_of($runtime, ModuleRuntimeBarrier::class)) {
                    app($runtime)->lockForTestDataCleanup();
                }
            }
            if (Schema::hasTable('fnb_site_settings')) {
                DB::table('fnb_site_settings')->where('website_key', $websiteKey)->lockForUpdate()->get();
            }
            $batches = DB::table('module_test_data_batches')->where('website_key', $websiteKey)->where('status', 'active')
                ->when($moduleKey, fn (Builder $query) => $query->where('module_key', $moduleKey))
                ->when($batchId, fn (Builder $query) => $query->where('id', $batchId))
                ->orderBy('id')->lockForUpdate()->get();
            if ($batches->isEmpty()) {
                return ['batch_count' => 0, 'record_count' => 0];
            }
            $records = DB::table('module_test_data_records')->whereIn('batch_id', $batches->pluck('id'))->orderBy('id')->get();
            $owned = [];
            foreach ($records as $record) {
                $key = json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR);
                abort_unless(Schema::hasTable($record->table_name), 409, 'Cấu trúc dữ liệu demo đã thay đổi. Cần khôi phục app trước khi dọn dữ liệu.');
                $row = DB::table($record->table_name)->where($key)->lockForUpdate()->first();
                if (! $row) {
                    continue;
                }
                foreach (json_decode($record->identity, true, flags: JSON_THROW_ON_ERROR) as $column => $value) {
                    abort_unless(array_key_exists($column, (array) $row) && (string) $row->$column === (string) $value, 409,
                        'Một bản ghi không còn khớp nhận diện của đợt demo. Không xóa để tránh ảnh hưởng dữ liệu khác.');
                }
                $owned[$record->table_name][] = ['key' => $key, 'row' => (array) $row];
            }
            $soft = $batches->flatMap(fn ($batch) => json_decode($batch->soft_references, true, flags: JSON_THROW_ON_ERROR))->all();
            $this->assertNoExternalReferences($owned, $soft);
            $removed = 0;
            foreach ($records->reverse() as $record) {
                $removed += DB::table($record->table_name)->where(json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR))->delete();
            }
            DB::table('module_test_data_records')->whereIn('batch_id', $batches->pluck('id'))->delete();
            DB::table('module_test_data_batches')->whereIn('id', $batches->pluck('id'))->update(['status' => 'removed', 'removed_at' => now(), 'updated_at' => now()]);
            $this->audit->record('module.test_data.removed', $moduleKey ?? 'all', null,
                ['batch_ids' => $batches->pluck('id')->all(), 'record_count' => $removed], $actor, $moduleKey ?? 'store', $websiteKey);

            return ['batch_count' => $batches->count(), 'record_count' => $removed];
        }, 3));
    }

    private function assertNoExternalReferences(array $owned, array $soft): void
    {
        $edges = $soft;
        foreach (Schema::getTableListing() as $qualifiedTable) {
            $table = Str::afterLast($qualifiedTable, '.');
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $foreignTable = Str::afterLast($foreignKey['foreign_table'], '.');
                if (isset($owned[$foreignTable])) {
                    $edges[] = ['table' => $table, 'columns' => $foreignKey['columns'], 'foreign_table' => $foreignTable, 'foreign_columns' => $foreignKey['foreign_columns']];
                }
            }
        }
        foreach ($edges as $edge) {
            $parent = $edge['foreign_table'];
            $child = $edge['table'];
            if (! isset($owned[$parent]) || ! Schema::hasTable($child)) {
                continue;
            }
            $columns = $edge['columns'] ?? [$edge['column']];
            $foreignColumns = $edge['foreign_columns'] ?? [$edge['foreign_column'] ?? 'id'];
            if (array_filter($columns, fn ($column) => str_contains($column, '->'))) {
                $this->assertJsonReferences($edge, $columns, $foreignColumns, $owned);

                continue;
            }
            $query = DB::table($child.' as test_child')->join($parent.' as test_parent', function ($join) use ($columns, $foreignColumns): void {
                foreach ($columns as $index => $column) {
                    $join->on('test_child.'.$column, '=', 'test_parent.'.$foreignColumns[$index]);
                }
            });
            foreach ($edge['where'] ?? [] as $column => $value) {
                $query->where('test_child.'.$column, $value);
            }
            $this->whereKeys($query, $owned[$parent], 'test_parent.');
            if (isset($owned[$child])) {
                $query->whereNot(fn (Builder $nested) => $this->whereKeys($nested, $owned[$child], 'test_child.'));
            }
            abort_if($query->select('test_child.*')->lockForUpdate()->first() !== null, 409,
                'Chưa thể xóa: dữ liệu bên ngoài đợt demo đang liên kết với '.$parent.'. Hãy gỡ liên kết trước; toàn bộ dữ liệu demo vẫn được giữ nguyên.');
        }
    }

    private function assertJsonReferences(array $edge, array $columns, array $foreignColumns, array $owned): void
    {
        $query = DB::table($edge['table']);
        foreach ($edge['where'] ?? [] as $column => $value) {
            $query->where($column, $value);
        }
        // A locking read sees references committed before the runtime locks
        // were acquired, even under MySQL's repeatable-read transaction mode.
        foreach ($query->lockForUpdate()->cursor() as $object) {
            $row = (array) $object;
            if ($this->isOwnedRow($row, $owned[$edge['table']] ?? [])) {
                continue;
            }
            $values = [];
            foreach ($columns as $column) {
                if (! str_contains($column, '->')) {
                    $values[] = [$row[$column] ?? null];

                    continue;
                }
                [$base, $path] = explode('->', $column, 2);
                $json = json_decode($row[$base] ?? 'null', true);
                $value = data_get($json, str_replace('->', '.', $path));
                $values[] = is_array($value) ? Arr::flatten($value) : [$value];
            }
            foreach ($owned[$edge['foreign_table']] as $parent) {
                $matches = true;
                foreach ($foreignColumns as $index => $column) {
                    $parentValue = $parent['row'][$column] ?? null;
                    $matches = $matches && $parentValue !== null && in_array((string) $parentValue, array_map('strval', $values[$index]), true);
                }
                abort_if($matches, 409, 'Chưa thể xóa: dữ liệu khác đang sử dụng dữ liệu demo trong '.$edge['foreign_table'].'. Toàn bộ đợt demo vẫn được giữ nguyên.');
            }
        }
    }

    private function isOwnedRow(array $row, array $records): bool
    {
        foreach ($records as $record) {
            $matches = true;
            foreach ($record['key'] as $column => $value) {
                $matches = $matches && array_key_exists($column, $row) && (string) $row[$column] === (string) $value;
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function whereKeys(Builder $query, array $records, string $prefix = ''): void
    {
        $query->where(function (Builder $keys) use ($records, $prefix): void {
            foreach ($records as $record) {
                $keys->orWhere(function (Builder $row) use ($record, $prefix): void {
                    foreach ($record['key'] as $column => $value) {
                        $row->where($prefix.$column, $value);
                    }
                });
            }
        });
    }

    private function provider(array $module): ?ModuleTestDataProvider
    {
        $class = $module['test_data_provider'] ?? null;

        return is_string($class) && is_subclass_of($class, ModuleTestDataProvider::class) ? app($class) : null;
    }

    private function dependencyKeys(string $key, array $visited = []): array
    {
        if (in_array($key, $visited, true)) {
            return $visited;
        }
        $visited[] = $key;
        foreach ($this->registry->find($key)['dependencies'] ?? [] as $dependency) {
            $visited = $this->dependencyKeys($dependency, $visited);
        }
        sort($visited);

        return $visited;
    }

    private function withLocks(array $keys, callable $callback): mixed
    {
        if ($keys === []) {
            return $callback();
        }
        $key = array_shift($keys);

        return $this->lifecycle->withModuleLock($key, function () use ($key, $keys, $callback) {
            abort_if(Schema::hasTable('module_lifecycle_operations') && DB::table('module_lifecycle_operations')->where('module_key', $key)->where('active_slot', 'active')->exists(), 409,
                'Ứng dụng đang thay đổi hoặc bảo trì; chưa thể xử lý dữ liệu demo.');

            return $this->withLocks($keys, $callback);
        });
    }

    private function lockScope(string $websiteKey): void
    {
        DB::table('module_test_data_scopes')->insertOrIgnore(['website_key' => $websiteKey, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('module_test_data_scopes')->where('website_key', $websiteKey)->lockForUpdate()->first();
    }

    private function assertReady(): void
    {
        abort_unless(Schema::hasTable('module_test_data_batches'), 503, 'Cần cập nhật cấu trúc dữ liệu trước khi dùng chức năng data demo.');
    }
}
