<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_testimonials')) {
            Schema::create('cms_testimonials', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('role')->nullable();
                $table->string('company')->nullable();
                $table->longText('quote');
                $table->string('image_url', 2048)->nullable();
                $table->string('image_alt')->nullable();
                $table->string('link_url')->nullable();
                $table->string('status', 40)->default('draft')->index();
                $table->timestamp('publish_at')->nullable()->index();
                $table->boolean('is_featured')->default(false)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->string('website_key')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_testimonials');
    }
};
