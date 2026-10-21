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
        if (! Schema::hasColumn('purchase_receipt_lines', 'public_id')) {
            Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
                // Legacy receipt writers may leave this nullable until upgraded.
                $table->uuid('public_id')->nullable();
            });
        }
        if (! Schema::hasIndex('purchase_receipt_lines', 'purchase_receipt_lines_public_id_unique')) {
            Schema::table('purchase_receipt_lines', fn (Blueprint $table) => $table->unique('public_id'));
        }
        DB::table('purchase_receipt_lines')->whereNull('public_id')->select('id')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('purchase_receipt_lines')->where('id', $row->id)->whereNull('public_id')->update(['public_id' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Receipt identities are permanent; use a forward migration.');
    }
};
