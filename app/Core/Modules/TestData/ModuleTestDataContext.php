<?php

namespace App\Core\Modules\TestData;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ModuleTestDataContext
{
    private array $columns = [];

    private int $codeSequence = 0;

    public function __construct(
        public readonly string $batchId,
        public readonly string $moduleKey,
        public readonly string $websiteKey,
        public readonly Admin $actor,
    ) {}

    public function code(string $prefix): string
    {
        return 'TEST-'.substr(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $prefix)), 0, 10).'-'.substr(str_replace('-', '', $this->batchId), 0, 12).'-'.(++$this->codeSequence);
    }

    public function insert(string $table, array $values, ?string $primaryKey = 'id'): int|string|array
    {
        $this->assertTable($table);
        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);
        if (in_array('website_key', $columns, true)) {
            if (isset($values['website_key']) && $values['website_key'] !== $this->websiteKey) {
                throw new RuntimeException('Test fixtures cannot cross the selected website.');
            }
            $values['website_key'] = $this->websiteKey;
        }
        foreach (['created_at', 'updated_at'] as $column) {
            if (in_array($column, $columns, true) && ! array_key_exists($column, $values)) {
                $values[$column] = now();
            }
        }
        if ($primaryKey === null || array_key_exists($primaryKey, $values)) {
            DB::table($table)->insert($values);
            $key = $primaryKey === null ? $values : [$primaryKey => $values[$primaryKey]];
        } else {
            $id = DB::table($table)->insertGetId($values, $primaryKey);
            $key = [$primaryKey => $id];
        }
        $this->track($table, $key);

        return $primaryKey === null ? $key : $key[$primaryKey];
    }

    public function track(string $table, int|string|array $key, string $primaryKey = 'id'): void
    {
        $this->assertTable($table);
        $key = is_array($key) ? $key : [$primaryKey => $key];
        if ($key === []) {
            throw new RuntimeException('A test record needs an exact identity.');
        }
        $key = $this->normalize($key);
        $rows = DB::table($table)->where($key)->limit(2)->get();
        if ($rows->count() !== 1) {
            throw new RuntimeException('A test record key must identify exactly one inserted row.');
        }
        $row = (array) $rows->first();
        if (isset($row['website_key']) && $row['website_key'] !== $this->websiteKey) {
            throw new RuntimeException('Test record scope does not match the batch.');
        }
        // Stable identity survives normal edits, but prevents deleting a different
        // record which later reused an identifier. Rows without stable identity
        // retain a full snapshot and must remain unchanged before cleanup.
        $identity = array_intersect_key($row, array_flip(['public_id', 'uuid', 'created_at', 'website_key']));
        if (! isset($identity['public_id']) && ! isset($identity['uuid']) && ! isset($identity['created_at'])) {
            $identity = $row;
        }
        DB::table('module_test_data_records')->insert([
            'batch_id' => $this->batchId,
            'table_name' => $table,
            'record_key' => json_encode($key, JSON_THROW_ON_ERROR),
            'identity' => json_encode($identity, JSON_THROW_ON_ERROR),
            'record_hash' => hash('sha256', json_encode($key, JSON_THROW_ON_ERROR)),
        ]);
    }

    private function normalize(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        ksort($values);

        return $values;
    }

    private function assertTable(string $table): void
    {
        if (DB::transactionLevel() < 1 || ! preg_match('/^[a-z][a-z0-9_]*$/', $table)
            || preg_match('/^(admin_|role_|permission_|module_|audit_)/', $table)
            || in_array($table, ['admins', 'roles', 'permissions', 'migrations', 'sites', 'site_profiles', 'site_settings', 'settings', 'system_settings'], true)) {
            throw new RuntimeException('This table cannot be owned by a test-data batch.');
        }
    }
}
