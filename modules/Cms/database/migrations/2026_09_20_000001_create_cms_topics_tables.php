<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_topics', function (Blueprint $table) {
            $table->id();
            $table->string('website_key')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['website_key', 'slug']);
        });
        Schema::create('cms_post_topic', function (Blueprint $table) {
            $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('cms_topic_id')->constrained('cms_topics')->cascadeOnDelete();
            $table->primary(['cms_post_id', 'cms_topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_topic');
        Schema::dropIfExists('cms_topics');
    }
};
