<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_team_members')) {
            Schema::create('cms_team_members', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('role')->nullable();
                $table->string('department')->nullable();
                $table->text('summary')->nullable();
                $table->longText('bio')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('link_url')->nullable();
                $table->string('status', 40)->default('draft')->index();
                $table->timestamp('publish_at')->nullable()->index();
                $table->boolean('is_featured')->default(false)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->string('website_key')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cms_team_member_images')) {
            Schema::create('cms_team_member_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_team_member_id')->constrained('cms_team_members')->cascadeOnDelete();
                $table->foreignId('cms_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
                $table->string('image_url', 2048);
                $table->string('alt_text')->nullable();
                $table->string('caption')->nullable();
                $table->boolean('is_featured')->default(false)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_team_member_images');
        Schema::dropIfExists('cms_team_members');
    }
};
