<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core migration also upgrades already-installed Catalog databases.
        // Fresh Catalog installs use the same precision in their create migration.
        foreach ([
            'catalog_products' => ['price' => false, 'original_price' => true],
            'orders' => ['subtotal' => false],
            'order_items' => ['unit_price' => false, 'original_price' => true, 'line_total' => false],
        ] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($columns as $column => $nullable) {
                if (! Schema::hasColumn($tableName, $column)) {
                    continue;
                }
                Schema::table($tableName, function (Blueprint $table) use ($column, $nullable): void {
                    $definition = $table->decimal($column, 18, 2);
                    if ($nullable) {
                        $definition->nullable();
                    } else {
                        $definition->default(0);
                    }
                    $definition->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Keep the widened precision: shrinking could destroy existing high-value prices.
    }
};
