<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fnb_bank_accounts', function (Blueprint $table): void {
            $table->string('bank_bin', 6)->default('970423');
            $table->string('default_slot', 10)->nullable();
            $table->index(['website_key', 'outlet_id'], 'fnb_bank_accounts_scope_idx');
        });
        Schema::table('fnb_bank_accounts', fn (Blueprint $table) => $table->dropUnique('fnb_bank_account_uq'));
        DB::table('fnb_bank_accounts')->where('enabled', true)->update(['default_slot' => 'default']);
        Schema::table('fnb_bank_accounts', fn (Blueprint $table) => $table->unique(['website_key', 'outlet_id', 'default_slot'], 'fnb_bank_default_uq'));
    }

    public function down(): void
    {
        // Multiple configured accounts cannot safely be collapsed into one on rollback.
        throw new RuntimeException('Preserve bank accounts; restore a database backup to roll back this migration.');
    }
};
