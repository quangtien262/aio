<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_service_interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // CMS is optional and its tables are not part of a core migration.
            $table->unsignedBigInteger('cms_service_id')->nullable()->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('status', 40)->default('interested');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_service_interests');
    }
};
