<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cust_exports', function (Blueprint $t): void {
            $t->uuid('lease_token')->nullable();
            $t->timestamp('lease_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cust_exports', fn (Blueprint $t) => $t->dropColumn(['lease_token', 'lease_expires_at']));
    }
};
