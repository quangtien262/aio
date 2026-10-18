<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Services\Security\FnbChainScopeBackfill;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['fnb_brands', 'fnb_regions'] as $name) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $table) use ($name): void {
                    $table->id();
                    $table->string('website_key', 120);
                    $table->string('code', 40);
                    $table->string('name');
                    $table->string('status', 30)->default('active');
                    $table->unsignedInteger('version')->default(1);
                    $table->timestamps();
                    $table->unique(['website_key', 'code'], $name.'_scope_code_uq');
                    $table->unique(['website_key', 'id'], $name.'_scope_id_uq');
                });
            }
        }
        if (! Schema::hasColumn('fnb_outlets', 'brand_id')) {
            Schema::table('fnb_outlets', function (Blueprint $table): void {
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('region_id')->nullable();
                $table->foreign(['website_key', 'brand_id'], 'fnb_outlet_brand_scope_fk')->references(['website_key', 'id'])->on('fnb_brands')->restrictOnDelete();
                $table->foreign(['website_key', 'region_id'], 'fnb_outlet_region_scope_fk')->references(['website_key', 'id'])->on('fnb_regions')->restrictOnDelete();
                $table->index(['website_key', 'region_id', 'status'], 'fnb_outlet_region_status_idx');
            });
        }
        if (! Schema::hasTable('fnb_staff_role_scopes')) {
            Schema::create('fnb_staff_role_scopes', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
                $table->string('scope_type', 20);
                $table->unsignedBigInteger('scope_key');
                $table->foreignId('preset_role_id')->constrained('roles')->restrictOnDelete();
                $table->foreignId('core_assignment_id')->nullable()->constrained('admin_role_assignments')->nullOnDelete();
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('assigned_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'admin_id', 'scope_type', 'scope_key'], 'fnb_staff_role_scoped_uq');
                $table->index(['website_key', 'scope_type', 'scope_key', 'status'], 'fnb_staff_role_scope_status_idx');
            });
        }
        if (! Schema::hasColumn('fnb_menu_outlets', 'menu_publication_id')) {
            Schema::table('fnb_menu_outlets', function (Blueprint $table): void {
                $table->unsignedBigInteger('menu_publication_id')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->foreign('menu_publication_id', 'fnb_outlet_menu_publication_fk')->references('id')->on('fnb_menu_publications')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('fnb_menu_master_locks')) {
            Schema::create('fnb_menu_master_locks', function (Blueprint $table): void {
                $table->string('website_key', 120)->primary();
            });
        }
        app(FnbChainScopeBackfill::class)->run();
    }

    public function down(): void
    {
        // Operational role history is retained; package uninstall is unsupported.
    }
};
