<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_tags')) {
            Schema::create('cms_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key');
                $table->string('name', 80);
                $table->string('normalized_name', 80);
                $table->string('slug');
                $table->timestamps();
                $table->unique(['website_key', 'normalized_name']);
                $table->unique(['website_key', 'slug']);
            });
        }
        if (! Schema::hasTable('cms_post_tag')) {
            Schema::create('cms_post_tag', function (Blueprint $table): void {
                $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
                $table->foreignId('cms_tag_id')->constrained('cms_tags')->cascadeOnDelete();
                $table->primary(['cms_post_id', 'cms_tag_id']);
                $table->index(['cms_tag_id', 'cms_post_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_tags');
    }
};
