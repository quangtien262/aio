<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('locked_at')->nullable()->after('is_active');
            $table->string('locked_reason')->nullable()->after('locked_at');
            $table->timestamp('last_login_at')->nullable()->after('locked_reason');
        });

        Schema::create('admin_role_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type')->index();
            $table->string('scope_value')->index();
            $table->timestamps();

            $table->unique(['admin_id', 'role_id', 'scope_type', 'scope_value'], 'admin_role_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_scopes');

        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'locked_at', 'locked_reason', 'last_login_at']);
        });
    }
};
