<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_bundle_materials', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('factory_id');
            $t->foreignId('bundle_id')->constrained('mfg_bundles')->restrictOnDelete();
            $t->unsignedBigInteger('order_id')->index();
            $t->uuid('issue_id')->index();
            $t->uuid('roll_id')->nullable();
            $t->decimal('quantity', 20, 6);
            $t->decimal('allocated_quantity', 20, 6)->default(0);
            $t->timestamps();
            $t->unique(['bundle_id', 'issue_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('mfg_bundle_materials')->exists()) {
            throw new RuntimeException('Giữ lịch sử phân bổ vật tư sản xuất; dùng migration tiến tới.');
        }
        Schema::dropIfExists('mfg_bundle_materials');
    }
};
