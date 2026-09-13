<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fnb_checks')) {
            Schema::table('fnb_checks', function (Blueprint $table): void {
                $table->index(['website_key', 'outlet_id', 'business_day_id', 'status', 'created_at'], 'fnb_check_day_hot_idx');
            });
        }

        if (Schema::hasTable('fnb_refunds')) {
            Schema::table('fnb_refunds', function (Blueprint $table): void {
                $table->index(['website_key', 'outlet_id', 'processing_business_day_id', 'status', 'requested_at'], 'fnb_refund_day_hot_idx');
            });
        }

        if (Schema::hasTable('fnb_kitchen_ticket_lines')) {
            Schema::table('fnb_kitchen_ticket_lines', function (Blueprint $table): void {
                $table->index(['website_key', 'outlet_id', 'status_rollup', 'waiting_at'], 'fnb_ticket_line_hot_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fnb_checks')) {
            Schema::table('fnb_checks', fn (Blueprint $table) => $table->dropIndex('fnb_check_day_hot_idx'));
        }
        if (Schema::hasTable('fnb_refunds')) {
            Schema::table('fnb_refunds', fn (Blueprint $table) => $table->dropIndex('fnb_refund_day_hot_idx'));
        }
        if (Schema::hasTable('fnb_kitchen_ticket_lines')) {
            Schema::table('fnb_kitchen_ticket_lines', fn (Blueprint $table) => $table->dropIndex('fnb_ticket_line_hot_idx'));
        }
    }
};
