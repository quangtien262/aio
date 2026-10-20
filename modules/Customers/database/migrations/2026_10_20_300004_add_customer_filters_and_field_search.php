<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cust_field_values', function (Blueprint $t): void {
            $t->string('search_value')->nullable()->index();
        });
        Schema::create('cust_saved_filters', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('book_id')->constrained('cust_books')->restrictOnDelete();
            $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $t->string('name', 80);
            $t->text('filters');
            $t->timestamps();
            $t->unique(['book_id', 'admin_id', 'name'], 'cust_filter_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cust_saved_filters');
        Schema::table('cust_field_values', fn (Blueprint $t) => $t->dropColumn('search_value'));
    }
};
