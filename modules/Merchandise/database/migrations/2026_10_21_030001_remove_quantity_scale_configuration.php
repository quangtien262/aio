<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['merch_skus', 'merch_units'] as $table) {
            if (Schema::hasColumn($table, 'quantity_scale')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('quantity_scale'));
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Cấu hình số lẻ đã được bỏ; dùng migration tiến tới.');
    }
};
