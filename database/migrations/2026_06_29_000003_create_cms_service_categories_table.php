<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_service_categories')) {
            Schema::create('cms_service_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('cms_service_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('cms_services') && ! Schema::hasColumn('cms_services', 'cms_service_category_id')) {
            Schema::table('cms_services', function (Blueprint $table): void {
                $table->foreignId('cms_service_category_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('cms_service_categories')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('cms_service_categories') && DB::table('cms_service_categories')->count() === 0) {
            $now = now();
            $categoryNames = Schema::hasTable('cms_services')
                ? collect(DB::table('cms_services')->orderBy('sort_order')->limit(6)->pluck('title'))
                    ->filter()
                    ->map(fn (string $title): string => trim(preg_replace('/\s+/', ' ', $title) ?? $title))
                    ->unique(fn (string $title): string => Str::slug($title))
                    ->take(3)
                    ->values()
                : collect();

            if ($categoryNames->isEmpty()) {
                $categoryNames = collect(['Thiết kế', 'Thi công', 'Tư vấn']);
            }

            $categoryNames->each(function (string $name, int $index) use ($now): void {
                DB::table('cms_service_categories')->insert([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => 'Nhóm dịch vụ '.$name,
                    'image_url' => null,
                    'sort_order' => $index,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

            $firstCategoryId = DB::table('cms_service_categories')->orderBy('sort_order')->value('id');

            if ($firstCategoryId && Schema::hasColumn('cms_services', 'cms_service_category_id')) {
                DB::table('cms_services')->whereNull('cms_service_category_id')->update(['cms_service_category_id' => $firstCategoryId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_services') && Schema::hasColumn('cms_services', 'cms_service_category_id')) {
            Schema::table('cms_services', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('cms_service_category_id');
            });
        }

        Schema::dropIfExists('cms_service_categories');
    }
};
