<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Services\InventoryRuntimeState;

return new class extends Migration
{
    public function up(): void
    {
        app(InventoryRuntimeState::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_runtime_states');
    }
};
