<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('key', 'platform-owner')
            ->update([
                'name' => 'Administrator',
                'description' => 'Quyền quản trị cao nhất.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('key', 'platform-owner')
            ->update([
                'name' => 'Chủ quản trị website',
                'description' => 'Quyền quản trị cao nhất có thể bàn giao cho khách hàng, không phải role hệ thống.',
                'updated_at' => now(),
            ]);
    }
};
