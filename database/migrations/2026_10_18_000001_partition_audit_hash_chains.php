<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_logs', 'chain_key')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->string('chain_key', 50)->default('default'));
        }
        if (! Schema::hasIndex('audit_logs', 'audit_chain_sequence_unique')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->unique(['chain_key', 'sequence'], 'audit_chain_sequence_unique'));
        }
        if (Schema::hasIndex('audit_logs', 'audit_logs_sequence_unique')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->dropUnique('audit_logs_sequence_unique'));
        }
    }

    public function down(): void
    {
        if (DB::table('audit_logs')->where('chain_key', '!=', 'default')->exists()) {
            throw new RuntimeException('Audit chain partitioning cannot roll back after partitioned events exist.');
        }
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique('audit_chain_sequence_unique');
            $table->unique('sequence');
            $table->dropColumn('chain_key');
        });
    }
};
