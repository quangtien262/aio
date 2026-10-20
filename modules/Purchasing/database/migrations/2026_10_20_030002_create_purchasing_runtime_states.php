<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Purchasing\Services\PurchasingRuntimeState;

return new class extends Migration
{
    public function up(): void
    {
        app(PurchasingRuntimeState::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('purch_runtime_states');
    }
};
