<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->text('body')->nullable();
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
