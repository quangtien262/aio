<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_calendar_days', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('company_id')->constrained('mfg_companies')->restrictOnDelete();
            $t->foreignId('factory_id')->constrained('mfg_factories')->restrictOnDelete();
            $t->foreignId('workshop_id')->nullable()->constrained('mfg_workshops')->restrictOnDelete();
            $t->foreignId('line_id')->nullable()->constrained('mfg_lines')->restrictOnDelete();
            $t->string('scope_key', 100);
            $t->date('work_date');
            $t->boolean('is_working')->default(true);
            $t->decimal('available_minutes', 20, 6)->default(0);
            $t->string('status', 30)->default('active');
            $t->unsignedBigInteger('version')->default(1);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['factory_id', 'scope_key', 'work_date'], 'mfg_calendar_scope_date_uq');
        });
        Schema::create('mfg_plan_changes', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('company_id')->constrained('mfg_companies')->restrictOnDelete();
            $t->foreignId('factory_id')->constrained('mfg_factories')->restrictOnDelete();
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->string('action', 40);
            $t->text('reason');
            $t->json('before_snapshot');
            $t->json('after_snapshot');
            $t->json('warnings');
            $t->unsignedBigInteger('recorded_by');
            $t->string('status', 30)->default('recorded');
            $t->unsignedBigInteger('version')->default(1);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_plan_changes');
        Schema::dropIfExists('mfg_calendar_days');
    }
};
