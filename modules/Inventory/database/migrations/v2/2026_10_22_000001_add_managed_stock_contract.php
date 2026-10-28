<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['managed_owner_module' => 80, 'managed_owner_scope' => 100] as $column => $length) {
            if (! Schema::hasColumn('inv_warehouses', $column)) {
                Schema::table('inv_warehouses', fn (Blueprint $t) => $t->string($column, $length)->nullable()->index());
            }
        }
        if (! Schema::hasTable('inv_stock_holds')) {
            Schema::create('inv_stock_holds', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('warehouse_id');
                $t->unsignedBigInteger('item_id')->nullable();
                $t->unsignedBigInteger('batch_id')->nullable();
                $t->string('reason_code', 80);
                $t->string('reason', 1000);
                $t->string('source_ref', 191)->nullable();
                $t->string('status', 20)->default('active');
                $t->unsignedInteger('version')->default(1);
                $t->unsignedBigInteger('actor_id');
                $t->unsignedBigInteger('released_by')->nullable();
                $t->string('release_reason', 1000)->nullable();
                $t->timestamp('released_at')->nullable();
                $t->timestamps();
                $t->index(['warehouse_id', 'status', 'item_id', 'batch_id'], 'inv_hold_eligibility_idx');
            });
        }
        if (! Schema::hasTable('inv_managed_commands')) {
            Schema::create('inv_managed_commands', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->string('command_key', 191)->unique();
                $t->string('operation', 80);
                $t->unsignedBigInteger('actor_id');
                $t->string('fingerprint', 64);
                $t->json('response')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_disposition_events')) {
            Schema::create('inv_disposition_events', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('warehouse_id');
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('batch_id');
                $t->string('disposition', 30);
                $t->decimal('quantity', 24, 6);
                $t->string('reason', 1000);
                $t->unsignedBigInteger('actor_id');
                $t->string('operation_id', 191);
                $t->timestamps();
                $t->index(['warehouse_id', 'batch_id', 'id']);
            });
        }
        if (! Schema::hasTable('inv_restricted_withdrawals')) {
            Schema::create('inv_restricted_withdrawals', function (Blueprint $t): void {
                $t->id();
                $t->uuid('public_id')->unique();
                $t->unsignedBigInteger('warehouse_id');
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('batch_id');
                $t->unsignedBigInteger('source_movement_id')->nullable()->index();
                $t->decimal('quantity', 24, 6);
                $t->string('purpose', 40);
                $t->string('reason', 1000);
                $t->unsignedBigInteger('actor_id');
                $t->string('operation_id', 191);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('inv_stock_source_links')) {
            Schema::create('inv_stock_source_links', function (Blueprint $t): void {
                $t->id();
                $t->string('owner_module', 80);
                $t->string('owner_scope', 100);
                $t->string('source_type', 80);
                $t->string('source_id', 191);
                $t->string('source_line_id', 191)->nullable();
                $t->unsignedBigInteger('movement_id')->unique();
                $t->unsignedBigInteger('warehouse_id')->index();
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('batch_id')->nullable()->index();
                $t->timestamps();
                $t->index(['owner_module', 'source_type', 'source_id'], 'inv_source_ref_idx');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Managed stock history is forward-only; use a forward repair migration.');
    }
};
