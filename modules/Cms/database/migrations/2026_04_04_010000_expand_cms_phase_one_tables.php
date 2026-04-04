<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->text('excerpt')->nullable()->after('status');
            $table->string('meta_title')->nullable()->after('body');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('template')->nullable()->after('meta_description');
            $table->unsignedBigInteger('featured_media_id')->nullable()->after('template');
            $table->timestamp('publish_at')->nullable()->after('featured_media_id');
        });

        Schema::create('cms_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cms_media', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_url');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt_text')->nullable();
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cms_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->timestamp('publish_at')->nullable();
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cms_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('location')->index();
            $table->json('items');
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_categories');

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn(['excerpt', 'meta_title', 'meta_description', 'template', 'featured_media_id', 'publish_at']);
        });
    }
};
