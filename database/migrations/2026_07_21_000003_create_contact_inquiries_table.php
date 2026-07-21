<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('website_key')->index();
            $table->string('submitted_host')->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('source', 50)->default('contact')->index();
            $table->string('status', 40)->default('new')->index();
            $table->string('name', 120);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('subject', 150)->nullable();
            $table->string('route_summary')->nullable();
            $table->text('message');
            $table->string('page_url', 2048)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();

            $table->index(['website_key', 'status', 'submitted_at'], 'contact_inquiries_website_status_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
    }
};
