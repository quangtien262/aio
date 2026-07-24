<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_property_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('real_estate_property_types')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('website_key')->nullable()->index();
            $table->timestamps();

            $table->unique(['website_key', 'slug'], 'real_estate_types_website_slug_unique');
        });

        Schema::create('real_estate_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_type_id')->nullable()->constrained('real_estate_property_types')->nullOnDelete();
            $table->foreignId('cms_project_id')->nullable()->constrained('cms_projects')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('code')->nullable();
            $table->string('publication_status', 30)->default('draft')->index();
            $table->string('availability_status', 30)->default('available')->index();
            $table->string('transaction_type', 20)->default('sale')->index();
            $table->decimal('price', 18, 2)->nullable()->index();
            $table->string('price_unit', 30)->default('total');
            $table->string('currency', 10)->default('VND');
            $table->string('province')->nullable()->index();
            $table->string('district')->nullable()->index();
            $table->string('ward')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->unsignedSmallInteger('floors')->nullable();
            $table->decimal('land_area', 12, 2)->nullable();
            $table->decimal('floor_area', 12, 2)->nullable();
            $table->string('direction', 30)->nullable();
            $table->string('legal_status')->nullable();
            $table->string('furnishing_status')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->string('virtual_tour_url', 2048)->nullable();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_hot')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('website_key')->nullable()->index();
            $table->timestamps();

            $table->unique(['website_key', 'slug'], 'real_estate_listings_website_slug_unique');
            $table->unique(['website_key', 'code'], 'real_estate_listings_website_code_unique');
            $table->index(
                ['website_key', 'publication_status', 'transaction_type', 'property_type_id'],
                'real_estate_listings_search_index'
            );
        });

        Schema::create('real_estate_listing_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('real_estate_listing_id')->constrained('real_estate_listings')->cascadeOnDelete();
            $table->foreignId('cms_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('media_type', 30)->default('image')->index();
            $table->string('media_url', 2048);
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_listing_media');
        Schema::dropIfExists('real_estate_listings');
        Schema::dropIfExists('real_estate_property_types');
    }
};
