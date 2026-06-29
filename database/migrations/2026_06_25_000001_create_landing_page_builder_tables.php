<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('website_key')->default('website-main')->index();
            $table->string('theme_key')->index();
            $table->string('page_type')->default('landing')->index();
            $table->string('slug')->index();
            $table->string('status')->default('draft')->index();
            $table->string('template')->default('landing')->index();
            $table->boolean('is_home')->default(false)->index();
            $table->json('settings')->nullable();
            $table->json('media')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['website_key', 'slug']);
            $table->index(['website_key', 'is_home']);
        });

        Schema::create('landing_page_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landing_page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->string('locale', 12)->index();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['landing_page_id', 'locale']);
        });

        Schema::create('landing_page_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landing_page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->string('theme_key')->index();
            $table->string('block_type')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->string('anchor_id')->nullable()->index();
            $table->json('settings')->nullable();
            $table->json('media')->nullable();
            $table->timestamps();

            $table->index(['landing_page_id', 'sort_order']);
        });

        Schema::create('landing_page_block_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landing_page_block_id')->constrained('landing_page_blocks')->cascadeOnDelete();
            $table->string('locale', 12)->index();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('button_label')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['landing_page_block_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_block_data');
        Schema::dropIfExists('landing_page_blocks');
        Schema::dropIfExists('landing_page_data');
        Schema::dropIfExists('landing_pages');
    }
};
