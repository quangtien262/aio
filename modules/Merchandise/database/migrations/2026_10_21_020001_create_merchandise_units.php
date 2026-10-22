<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Merchandise\Services\MerchandiseUnitService;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merch_units')) {
            Schema::create('merch_units', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->foreignId('catalog_id')->constrained('merch_catalogs')->restrictOnDelete();
                $t->string('code', 24);
                $t->string('name', 100);
                $t->unsignedTinyInteger('quantity_scale')->default(0);
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->unique(['catalog_id', 'code']);
            });
        }
        foreach (DB::table('merch_catalogs')->pluck('id') as $catalogId) {
            MerchandiseUnitService::seedCatalog((int) $catalogId);
        }
        foreach (DB::table('merch_skus')->select('catalog_id', 'base_unit')->selectRaw('MAX(quantity_scale) AS scale')->groupBy('catalog_id', 'base_unit')->get() as $unit) {
            MerchandiseUnitService::register((int) $unit->catalog_id, $unit->base_unit, $unit->base_unit, (int) $unit->scale);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Giữ danh mục đơn vị để bảo toàn lịch sử hàng hóa; dùng migration tiến tới.');
    }
};
