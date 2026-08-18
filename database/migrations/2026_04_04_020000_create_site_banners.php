<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_banners')) {
            Schema::create('site_banners', function (Blueprint $table) {
                $table->id();
                $table->string('theme_key')->nullable()->index();
                $table->string('placement')->index();
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->string('image_url');
                $table->string('link_url')->nullable();
                $table->string('badge')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('website_key')->nullable()->index();
                $table->string('owner_key')->nullable()->index();
                $table->string('tenant_key')->nullable()->index();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('site_banners');
    }
};
