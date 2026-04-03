<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('module_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admin_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['admin_id', 'role_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('module_installations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('version');
            $table->string('status')->default('installed')->index();
            $table->json('website_types')->nullable();
            $table->json('dependencies')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('theme_installations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('version');
            $table->string('website_type')->index();
            $table->string('status')->default('installed')->index();
            $table->boolean('is_active')->default(false);
            $table->json('blocks')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('AIO Website');
            $table->string('website_type')->nullable()->index();
            $table->string('active_theme_key')->nullable()->index();
            $table->boolean('is_setup_completed')->default(false);
            $table->json('completed_steps')->nullable();
            $table->json('branding')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_profiles');
        Schema::dropIfExists('theme_installations');
        Schema::dropIfExists('module_installations');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('admin_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
