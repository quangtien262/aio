<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Services\FnbUnitService;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_units')) {
            Schema::create('fnb_units', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->string('code', 30);
                $table->string('name', 100);
                $table->string('dimension', 20);
                $table->decimal('factor', 18, 6);
                $table->boolean('is_system')->default(false);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'code']);
            });
        }
        $keys = DB::table('fnb_site_settings')->pluck('website_key')
            ->merge(DB::table('fnb_outlets')->distinct()->pluck('website_key'))
            ->merge(DB::table('fnb_ingredients')->distinct()->pluck('website_key'))->unique();
        foreach ($keys as $websiteKey) {
            FnbUnitService::seedDefaults($websiteKey);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_units');
    }
};
