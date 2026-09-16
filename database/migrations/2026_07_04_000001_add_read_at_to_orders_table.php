<?php

use App\Support\Database\MigrationRollback;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('placed_at')->index();
        });
    }

    public function down(): void
    {
        MigrationRollback::dropColumns('orders', ['read_at']);
    }
};
