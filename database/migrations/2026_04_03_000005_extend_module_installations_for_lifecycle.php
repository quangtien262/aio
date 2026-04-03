<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_installations', function (Blueprint $table) {
            $table->timestamp('last_upgraded_at')->nullable()->after('enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('module_installations', function (Blueprint $table) {
            $table->dropColumn('last_upgraded_at');
        });
    }
};
