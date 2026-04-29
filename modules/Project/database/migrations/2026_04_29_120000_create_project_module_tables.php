<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro__project_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('blue');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pro__project_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 32)->default('blue');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pro__priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('blue');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pro__task_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('blue');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_done')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pro__projects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('project_type_id')->nullable()->constrained('pro__project_types')->nullOnDelete();
            $table->foreignId('project_status_id')->constrained('pro__project_statuses')->cascadeOnDelete();
            $table->foreignId('priority_id')->constrained('pro__priorities')->cascadeOnDelete();
            $table->foreignId('manager_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('color', 32)->default('#1677ff');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pro__project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('role', 50)->default('member');
            $table->timestamps();
            $table->unique(['project_id', 'admin_id']);
        });

        Schema::create('pro__tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('task_status_id')->constrained('pro__task_statuses')->cascadeOnDelete();
            $table->foreignId('priority_id')->constrained('pro__priorities')->cascadeOnDelete();
            $table->foreignId('assignee_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();
        });

        Schema::create('pro__project_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pro__files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('pro__tasks')->nullOnDelete();
            $table->string('title');
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pro__reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->string('title');
            $table->date('report_date');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pro__activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pro__projects')->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action', 100);
            $table->text('description');
            $table->json('properties')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro__activities');
        Schema::dropIfExists('pro__reports');
        Schema::dropIfExists('pro__files');
        Schema::dropIfExists('pro__project_checklists');
        Schema::dropIfExists('pro__tasks');
        Schema::dropIfExists('pro__project_members');
        Schema::dropIfExists('pro__projects');
        Schema::dropIfExists('pro__task_statuses');
        Schema::dropIfExists('pro__priorities');
        Schema::dropIfExists('pro__project_types');
        Schema::dropIfExists('pro__project_statuses');
    }
};
