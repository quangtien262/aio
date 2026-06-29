<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_services')) {
            Schema::create('cms_services', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('status')->default('draft')->index();
                $table->text('summary')->nullable();
                $table->longText('content')->nullable();
                $table->string('icon')->nullable();
                $table->string('button_label')->nullable();
                $table->string('link_url')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamp('publish_at')->nullable();
                $table->boolean('is_featured')->default(false)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->string('website_key')->nullable()->index();
                $table->string('owner_key')->nullable()->index();
                $table->string('tenant_key')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cms_service_images')) {
            Schema::create('cms_service_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_service_id')->constrained('cms_services')->cascadeOnDelete();
                $table->foreignId('cms_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
                $table->string('image_url');
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
        Schema::dropIfExists('cms_service_images');
        Schema::dropIfExists('cms_services');
    }
};
