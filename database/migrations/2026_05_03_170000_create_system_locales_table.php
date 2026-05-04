<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('system_locales')->insert([
            [
                'code' => 'vi',
                'name' => 'Tiếng Việt',
                'native_name' => 'Tiếng Việt',
                'is_default' => true,
                'is_active' => true,
                'is_published' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_default' => false,
                'is_active' => true,
                'is_published' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_locales');
    }
};
