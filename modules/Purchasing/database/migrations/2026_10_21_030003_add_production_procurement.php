<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requisitions')) {
            Schema::create('purchase_requisitions', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('order_ref', 191)->index();
                $t->string('source_revision', 80);
                $t->unsignedBigInteger('warehouse_id');
                $t->unsignedBigInteger('catalog_id');
                $t->json('lines');
                $t->string('status', 30)->default('open');
                $t->unsignedBigInteger('purchase_order_id')->nullable();
                $t->unsignedBigInteger('actor_id');
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->unique(['order_ref', 'source_revision', 'warehouse_id'], 'purchase_requisition_source_unique');
            });
        }
        if (! Schema::hasColumn('purchase_receipt_lines', 'trace_unit_id')) {
            Schema::table('purchase_receipt_lines', function (Blueprint $t): void {
                $t->unsignedBigInteger('trace_unit_id')->nullable()->index();
                $t->decimal('secondary_quantity', 24, 6)->nullable();
                $t->string('secondary_unit', 24)->nullable();
                $t->string('qc_status', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Procurement lineage requires a forward migration.');
    }
};
