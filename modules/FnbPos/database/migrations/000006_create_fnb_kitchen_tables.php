<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_kitchen_tickets')) {
            Schema::create('fnb_kitchen_tickets', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('prep_station_id');
                $table->unsignedBigInteger('sequence_no');
                $table->string('ticket_no', 80);
                $table->string('dispatch_key', 160)->unique();
                $table->string('status_rollup', 40)->default('in_progress');
                $table->integer('priority')->default(0);
                $table->timestamp('fired_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $this->actor($table, 'last_actor_id');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'business_day_id', 'sequence_no'], 'fnb_ticket_day_sequence_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'], 'fnb_ticket_order_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_ticket_scope_id_uq');
                $table->index(['website_key', 'outlet_id', 'prep_station_id', 'status_rollup', 'fired_at'], 'fnb_ticket_hot_idx');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id'], 'fnb_ticket_order_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'id'])->on('fnb_orders')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'prep_station_id'], 'fnb_ticket_station_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_prep_stations')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_kitchen_ticket_lines')) {
            Schema::create('fnb_kitchen_ticket_lines', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('business_day_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('order_line_id');
                $table->unsignedInteger('attempt_no')->default(1);
                $table->decimal('quantity', 18, 6);
                $table->decimal('served_quantity', 18, 6)->default(0);
                $table->decimal('voided_quantity', 18, 6)->default(0);
                $table->decimal('compensated_quantity', 18, 6)->default(0);
                $table->string('status_rollup', 40)->default('waiting');
                $table->text('note')->nullable();
                $table->timestamp('waiting_at');
                $table->timestamp('preparing_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['order_line_id', 'attempt_no'], 'fnb_ticket_line_attempt_uq');
                $table->unique(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'], 'fnb_ticket_line_order_chain_id_uq');
                $table->unique(['website_key', 'outlet_id', 'id'], 'fnb_ticket_line_scope_id_uq');
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'ticket_id'], 'fnb_ticket_line_ticket_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_kitchen_tickets')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'order_line_id'], 'fnb_ticket_line_order_line_fk')
                    ->references(['website_key', 'outlet_id', 'business_day_id', 'order_id', 'id'])->on('fnb_order_lines')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_kitchen_event_sequences')) {
            Schema::create('fnb_kitchen_event_sequences', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('next_sequence')->default(1);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique('outlet_id', 'fnb_kitchen_sequence_outlet_uq');
                $table->foreign(['website_key', 'outlet_id'], 'fnb_kitchen_sequence_outlet_fk')
                    ->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_kitchen_events')) {
            Schema::create('fnb_kitchen_events', function (Blueprint $table): void {
                $table->id();
                $this->scope($table);
                $table->unsignedBigInteger('sequence');
                $table->string('event_key', 160);
                $table->unsignedBigInteger('prep_station_id')->nullable();
                $table->unsignedBigInteger('ticket_id')->nullable();
                $table->unsignedBigInteger('ticket_line_id')->nullable();
                $table->string('event_type', 60);
                $table->json('payload');
                $this->actor($table, 'actor_id');
                $table->timestamp('occurred_at');
                $table->unique(['outlet_id', 'sequence'], 'fnb_kitchen_event_sequence_uq');
                $table->unique(['outlet_id', 'event_key'], 'fnb_kitchen_event_key_uq');
                $table->index(['website_key', 'outlet_id', 'prep_station_id', 'sequence'], 'fnb_kitchen_event_cursor_idx');
                $table->foreign(['website_key', 'outlet_id', 'prep_station_id'], 'fnb_kitchen_event_station_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_prep_stations')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'ticket_id'], 'fnb_kitchen_event_ticket_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_kitchen_tickets')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'ticket_line_id'], 'fnb_kitchen_event_line_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_kitchen_ticket_lines')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['fnb_kitchen_events', 'fnb_kitchen_event_sequences', 'fnb_kitchen_ticket_lines', 'fnb_kitchen_tickets'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function scope(Blueprint $table): void
    {
        $table->string('website_key', 120);
        $table->unsignedBigInteger('outlet_id');
    }

    private function actor(Blueprint $table, string $name): void
    {
        $table->foreignId($name)->nullable()->constrained('admins')->nullOnDelete();
    }
};
