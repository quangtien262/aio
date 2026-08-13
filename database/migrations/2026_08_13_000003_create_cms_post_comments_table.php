<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_post_comments', function (Blueprint $table): void {
            $table->id();
            $table->string('website_key')->index();
            $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_post_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 30)->default('published')->index();
            $table->timestamps();

            $table->index(['website_key', 'cms_post_id', 'status'], 'post_comments_website_post_status_idx');
            $table->index(['cms_post_id', 'parent_id', 'created_at'], 'post_comments_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_comments');
    }
};
