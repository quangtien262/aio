<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_side_promos', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('location')->index();
            $table->json('items');
            $table->string('website_key')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_side_promos');
    }
};
