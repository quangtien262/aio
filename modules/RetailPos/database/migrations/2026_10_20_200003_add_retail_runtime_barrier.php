<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\RetailPos\Services\RetailRuntimeState;

return new class extends Migration
{
    public function up(): void
    {
        app(RetailRuntimeState::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_runtime_states');
    }
};
