<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cust_account_link_requests', function (Blueprint $t): void {
            $t->foreignId('account_id')->nullable()->constrained('customers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cust_account_link_requests', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('account_id');
        });
    }
};
