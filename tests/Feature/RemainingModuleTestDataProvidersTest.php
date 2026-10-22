<?php

namespace Tests\Feature;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RemainingModuleTestDataProvidersTest extends TestCase
{
    use RefreshDatabase;

    public static function providers(): array
    {
        $data = [];
        foreach (['Cms' => 'cms', 'Customers' => 'customers', 'Project' => 'project', 'Catalog' => 'catalog', 'RealEstate' => 'real-estate', 'Hrm' => 'hrm', 'Payroll' => 'payroll', 'AccountingTax' => 'accounting-tax', 'MinvoiceConnector' => 'minvoice-connector'] as $namespace => $module) {
            $data[$module] = ['Modules\\'.$namespace.'\\Services\\'.$namespace.'TestDataProvider', $module];
        }

        return $data;
    }

    #[DataProvider('providers')]
    public function test_each_provider_creates_only_owned_rows_and_cleanup_restores_original_counts(string $class, string $module): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $provider = new $class;
        $before = [];
        foreach (Schema::getTableListing() as $qualified) {
            $table = str_contains($qualified, '.') ? substr($qualified, strrpos($qualified, '.') + 1) : $qualified;
            $before[$table] = DB::table($table)->count();
        }
        $assignments = DB::table('admin_role_assignments')->get()->toJson();
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($provider, $module, $actor, $batch) {
            DB::table('module_test_data_batches')->insert(['id' => $batch, 'website_key' => 'website-test-fixtures', 'module_key' => $module, 'actor_admin_id' => $actor->id, 'request_id' => (string) Str::uuid(), 'status' => 'active', 'preview' => json_encode($provider->preview()), 'soft_references' => json_encode(method_exists($provider, 'references') ? $provider->references() : []), 'created_at' => now(), 'updated_at' => now()]);
            $provider->create(new ModuleTestDataContext($batch, $module, 'website-test-fixtures', $actor));
        });
        $owned = DB::table('module_test_data_records')->where('batch_id', $batch)->get();
        $this->assertGreaterThan(0, $owned->count());
        foreach ($before as $table => $count) {
            if (in_array($table, ['module_test_data_batches', 'module_test_data_records'])) {
                continue;
            }
            $this->assertSame($owned->where('table_name', $table)->count(), DB::table($table)->count() - $count, 'Untracked writes in '.$table);
        }
        $this->assertSame($assignments, DB::table('admin_role_assignments')->get()->toJson());
        $result = app(ModuleTestDataService::class)->remove('website-test-fixtures', $actor, $module, $batch);
        $this->assertSame($owned->count(), $result['record_count']);
        foreach ($owned->pluck('table_name')->unique() as $table) {
            $this->assertSame($before[$table], DB::table($table)->count(), 'Cleanup count mismatch for '.$table);
        }
    }
}
