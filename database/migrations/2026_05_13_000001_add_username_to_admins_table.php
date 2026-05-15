<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->string('username')->nullable()->after('name');
        });

        DB::table('admins')
            ->orderBy('id')
            ->get(['id', 'email', 'name'])
            ->each(function (object $admin): void {
                $base = Str::of((string) ($admin->email ?: $admin->name ?: 'admin'))
                    ->before('@')
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '.')
                    ->trim('.')
                    ->value();

                if ($base === '') {
                    $base = 'admin';
                }

                $candidate = $base;
                $suffix = 1;

                while (DB::table('admins')
                    ->where('username', $candidate)
                    ->where('id', '!=', $admin->id)
                    ->exists()) {
                    $suffix++;
                    $candidate = $base.'.'.$suffix;
                }

                DB::table('admins')
                    ->where('id', $admin->id)
                    ->update(['username' => $candidate]);
            });

        Schema::table('admins', function (Blueprint $table): void {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
