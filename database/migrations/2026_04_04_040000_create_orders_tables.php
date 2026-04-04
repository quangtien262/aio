<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_code')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status')->default('placed')->index();
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->string('delivery_address');
            $table->text('note')->nullable();
            $table->string('payment_method', 50);
            $table->string('payment_label', 120);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->string('website_key')->nullable()->index();
            $table->string('owner_key')->nullable()->index();
            $table->string('tenant_key')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->nullable()->constrained('catalog_products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_slug')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
