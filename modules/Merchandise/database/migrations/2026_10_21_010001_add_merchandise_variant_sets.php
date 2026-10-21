<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merch_variant_sets')) {
            Schema::create('merch_variant_sets', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->foreignId('catalog_id')->constrained('merch_catalogs')->restrictOnDelete();
                $t->string('code', 80);
                $t->string('name');
                $t->json('axes');
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->unique(['catalog_id', 'code']);
            });
        }
        if (! Schema::hasTable('merch_sku_variants')) {
            Schema::create('merch_sku_variants', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('variant_set_id')->constrained('merch_variant_sets')->restrictOnDelete();
                $t->foreignId('sku_id')->unique()->constrained('merch_skus')->restrictOnDelete();
                $t->json('attributes');
                $t->string('combination_hash', 64);
                $t->timestamps();
                $t->unique(['variant_set_id', 'combination_hash'], 'merch_variant_combination_unique');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Variant identities are retained; use a forward migration.');
    }
};
