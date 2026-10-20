<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_catalogs', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('code', 60)->unique();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('merch_skus', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('catalog_id')->constrained('merch_catalogs')->restrictOnDelete();
            $t->string('sku', 80);
            $t->string('name');
            $t->string('base_unit', 24)->default('pcs');
            $t->unsignedTinyInteger('quantity_scale')->default(0);
            $t->string('tracking_mode', 20)->default('none');
            $t->boolean('requires_expiry')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['catalog_id', 'sku']);
            $t->index(['catalog_id', 'is_active', 'name']);
        });
        Schema::create('merch_packagings', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('sku_id')->constrained('merch_skus')->restrictOnDelete();
            $t->string('code', 40);
            $t->string('name', 100);
            $t->decimal('quantity_multiplier', 24, 6);
            $t->unsignedInteger('revision')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['sku_id', 'code', 'revision']);
        });
        Schema::create('merch_barcodes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('catalog_id')->constrained('merch_catalogs')->restrictOnDelete();
            $t->foreignId('sku_id')->constrained('merch_skus')->restrictOnDelete();
            $t->foreignId('packaging_id')->nullable()->constrained('merch_packagings')->restrictOnDelete();
            $t->string('barcode', 128);
            $t->string('format', 20)->default('internal');
            $t->timestamps();
            $t->unique(['catalog_id', 'barcode']);
            $t->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_barcodes');
        Schema::dropIfExists('merch_packagings');
        Schema::dropIfExists('merch_skus');
        Schema::dropIfExists('merch_catalogs');
    }
};
