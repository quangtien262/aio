<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_ingredients')) {
            Schema::create('fnb_ingredients', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->string('code', 60);
                $table->string('name');
                $table->string('base_unit', 30);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'code'], 'fnb_ingredient_scope_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_ingredient_scope_id_uq');
            });
        }

        if (! Schema::hasTable('fnb_unit_conversions')) {
            Schema::create('fnb_unit_conversions', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('ingredient_id');
                $table->string('from_unit', 30);
                $table->string('to_unit', 30);
                $table->decimal('multiplier', 18, 8);
                $table->timestamps();
                $table->unique(['ingredient_id', 'from_unit', 'to_unit'], 'fnb_unit_conversion_uq');
                $table->foreign(['website_key', 'ingredient_id'], 'fnb_conversion_ingredient_fk')
                    ->references(['website_key', 'id'])->on('fnb_ingredients')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_recipes')) {
            Schema::create('fnb_recipes', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->string('code', 60);
                $table->unsignedInteger('recipe_version')->default(1);
                $table->string('name');
                $table->decimal('yield_quantity', 18, 6)->default(1);
                $table->string('yield_unit', 30);
                $table->string('status', 30)->default('draft');
                $table->string('current_slot', 20)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $this->actor($table, 'published_by');
                $table->timestamps();
                $table->unique(['website_key', 'code', 'recipe_version'], 'fnb_recipe_scope_version_uq');
                $table->unique(['website_key', 'code', 'current_slot'], 'fnb_recipe_scope_current_uq');
                $table->unique(['website_key', 'id'], 'fnb_recipe_scope_id_uq');
            });
        }

        if (! Schema::hasTable('fnb_recipe_lines')) {
            Schema::create('fnb_recipe_lines', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity', 18, 6);
                $table->string('unit', 30);
                $table->decimal('base_quantity', 18, 6);
                $table->decimal('loss_rate', 7, 4)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['recipe_id', 'ingredient_id'], 'fnb_recipe_line_ingredient_uq');
                $table->foreign(['website_key', 'recipe_id'], 'fnb_recipe_line_recipe_fk')
                    ->references(['website_key', 'id'])->on('fnb_recipes')->restrictOnDelete();
                $table->foreign(['website_key', 'ingredient_id'], 'fnb_recipe_line_ingredient_fk')
                    ->references(['website_key', 'id'])->on('fnb_ingredients')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_variant_recipes')) {
            Schema::create('fnb_variant_recipes', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('variant_id');
                $table->unsignedBigInteger('recipe_id');
                $table->decimal('multiplier', 18, 6)->default(1);
                $table->string('current_slot', 20)->nullable()->default('current');
                $table->timestamps();
                $table->unique(['variant_id', 'current_slot'], 'fnb_variant_recipe_current_uq');
                $table->foreign(['website_key', 'variant_id'], 'fnb_variant_recipe_variant_fk')
                    ->references(['website_key', 'id'])->on('fnb_item_variants')->restrictOnDelete();
                $table->foreign(['website_key', 'recipe_id'], 'fnb_variant_recipe_recipe_fk')
                    ->references(['website_key', 'id'])->on('fnb_recipes')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_modifier_option_recipes')) {
            Schema::create('fnb_modifier_option_recipes', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('modifier_option_id');
                $table->unsignedBigInteger('recipe_id');
                $table->decimal('multiplier', 18, 6)->default(1);
                $table->string('current_slot', 20)->nullable()->default('current');
                $table->timestamps();
                $table->unique(['modifier_option_id', 'current_slot'], 'fnb_modifier_recipe_current_uq');
                $table->foreign(['website_key', 'modifier_option_id'], 'fnb_modifier_recipe_option_fk')
                    ->references(['website_key', 'id'])->on('fnb_modifier_options')->restrictOnDelete();
                $table->foreign(['website_key', 'recipe_id'], 'fnb_modifier_recipe_recipe_fk')
                    ->references(['website_key', 'id'])->on('fnb_recipes')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fnb_modifier_option_recipes', 'fnb_variant_recipes', 'fnb_recipe_lines',
            'fnb_recipes', 'fnb_unit_conversions', 'fnb_ingredients',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function website(Blueprint $table): void
    {
        $table->string('website_key', 120);
    }

    private function actor(Blueprint $table, string $name): void
    {
        $table->foreignId($name)->nullable()->constrained('admins')->nullOnDelete();
    }
};
