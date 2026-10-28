<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ph_work_tasks', function (Blueprint $t): void {
            $this->base($t);
            $t->unsignedBigInteger('dispensing_id')->unique();
            $t->unsignedBigInteger('assigned_to');
            $t->unsignedBigInteger('assigned_by');
            $t->string('stage', 30);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamp('due_at')->nullable();
            $t->index(['pharmacy_id', 'assigned_to', 'stage']);
        });
        Schema::create('ph_counseling_evidence', function (Blueprint $t): void {
            $this->base($t);
            $t->unsignedBigInteger('dispensing_id')->index();
            $t->unsignedBigInteger('actor_id');
            $t->string('kind', 30);
            $t->longText('encrypted_evidence');
        });
        Schema::create('ph_cash_movements', function (Blueprint $t): void {
            $this->base($t);
            $t->unsignedBigInteger('shift_id')->index();
            $t->unsignedBigInteger('actor_id');
            $t->string('direction', 10);
            $t->bigInteger('amount_minor');
            $t->longText('encrypted_reason');
        });
        Schema::create('ph_replenishment_proposals', function (Blueprint $t): void {
            $this->base($t);
            $t->unsignedBigInteger('actor_id');
            $t->unsignedInteger('coverage_days');
            $t->json('lines');
            $t->timestamp('as_of');
        });
    }

    private function base(Blueprint $t): void
    {
        $t->id();
        $t->uuid('public_id')->unique();
        $t->unsignedBigInteger('chain_id')->index();
        $t->unsignedBigInteger('pharmacy_id')->index();
        $t->string('status', 30)->default('active');
        $t->unsignedBigInteger('version')->default(1);
        $t->timestamps();
    }

    public function down(): void
    {
        foreach (['ph_replenishment_proposals', 'ph_cash_movements', 'ph_counseling_evidence', 'ph_work_tasks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
