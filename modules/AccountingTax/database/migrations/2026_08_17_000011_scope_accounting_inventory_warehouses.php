<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct_inventory_warehouse_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('acct_organizations')->restrictOnDelete();
            $table->unsignedBigInteger('inventory_warehouse_id');
            $table->boolean('is_default')->default(false)->index();
            $table->string('default_slot', 20)->nullable();
            $table->json('warehouse_snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique('inventory_warehouse_id', 'acct_inventory_warehouse_global_unique');
            $table->unique(
                ['organization_id', 'inventory_warehouse_id'],
                'acct_inventory_warehouse_org_unique',
            );
            $table->unique(
                ['organization_id', 'default_slot'],
                'acct_inventory_warehouse_default_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acct_inventory_warehouse_mappings');
    }
};
