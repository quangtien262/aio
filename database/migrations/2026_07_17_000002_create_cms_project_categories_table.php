<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_projects')) {
            return;
        }

        if (! Schema::hasTable('cms_project_categories')) {
            Schema::create('cms_project_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('cms_project_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('cms_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_projects', 'cms_project_category_id')) {
                $table->foreignId('cms_project_category_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('cms_project_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_projects')) {
            Schema::table('cms_projects', function (Blueprint $table): void {
                if (! Schema::hasColumn('cms_projects', 'cms_project_category_id')) {
                    return;
                }

                $table->dropConstrainedForeignId('cms_project_category_id');
            });
        }

        Schema::dropIfExists('cms_project_categories');
    }
};
