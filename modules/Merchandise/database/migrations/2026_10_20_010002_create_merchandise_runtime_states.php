<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Merchandise\Services\MerchandiseRuntimeState;

return new class extends Migration
{
    public function up(): void
    {
        app(MerchandiseRuntimeState::class)->install();
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_runtime_states');
    }
};
