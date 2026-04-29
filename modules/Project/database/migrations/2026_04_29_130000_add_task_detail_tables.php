<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro__task_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('pro__tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pro__task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('pro__tasks')->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pro__task_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('pro__tasks')->cascadeOnDelete();
            $table->foreignId('tracked_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('tracked_at');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro__task_time_entries');
        Schema::dropIfExists('pro__task_comments');
        Schema::dropIfExists('pro__task_checklists');
    }
};
