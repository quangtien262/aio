<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_stock_balances', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_key')->default(0)->after('location_id');
            $table->unsignedBigInteger('batch_key')->default(0)->after('batch_id');
        });

        DB::table('inv_stock_balances')->update([
            'location_key' => DB::raw('COALESCE(location_id, 0)'),
            'batch_key' => DB::raw('COALESCE(batch_id, 0)'),
        ]);

        Schema::table('inv_stock_balances', function (Blueprint $table): void {
            $table->dropUnique('inv_balance_unique_location_item_batch');
            $table->unique(['warehouse_id', 'location_key', 'item_id', 'batch_key'], 'inv_balance_unique_scope_item_batch');
        });

        Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
            $table->foreignId('source_location_id')->nullable()->after('item_id')->constrained('inv_locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->after('source_location_id')->constrained('inv_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inv_stock_document_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('destination_location_id');
            $table->dropConstrainedForeignId('source_location_id');
        });

        Schema::table('inv_stock_balances', function (Blueprint $table): void {
            $table->dropUnique('inv_balance_unique_scope_item_batch');
            $table->unique(['warehouse_id', 'location_id', 'item_id', 'batch_id'], 'inv_balance_unique_location_item_batch');
            $table->dropColumn(['location_key', 'batch_key']);
        });
    }
};
