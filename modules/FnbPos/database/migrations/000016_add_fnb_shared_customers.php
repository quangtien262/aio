<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['fnb_service_sessions', 'fnb_checks'] as $name) {
            if (! Schema::hasColumn($name, 'shared_customer_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->uuid('shared_customer_id')->nullable());
            }
            $index = $name === 'fnb_checks' ? 'fnb_check_shared_customer_idx' : 'fnb_session_shared_customer_idx';
            if (! Schema::hasIndex($name, $index)) {
                Schema::table($name, fn (Blueprint $table) => $table->index(['website_key', 'shared_customer_id'], $index));
            }
        }
        if (! Schema::hasTable('fnb_shared_customer_extensions')) {
            Schema::create('fnb_shared_customer_extensions', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 100);
                $table->uuid('shared_customer_id');
                $table->text('notes')->nullable();
                $table->date('birthday')->nullable();
                $table->timestamp('privacy_consented_at')->nullable();
                $table->timestamp('marketing_consented_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'shared_customer_id'], 'fnb_shared_customer_extension_uq');
            });
        }
    }

    public function down(): void
    {
        if (DB::table('fnb_service_sessions')->whereNotNull('shared_customer_id')->exists()
            || DB::table('fnb_checks')->whereNotNull('shared_customer_id')->exists()
            || DB::table('fnb_shared_customer_extensions')->exists()) {
            throw new RuntimeException('Preserve shared buyer snapshots; roll forward instead of removing customer lineage.');
        }
        Schema::dropIfExists('fnb_shared_customer_extensions');
        foreach (['fnb_service_sessions', 'fnb_checks'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropIndex($name === 'fnb_checks' ? 'fnb_check_shared_customer_idx' : 'fnb_session_shared_customer_idx');
                $table->dropColumn('shared_customer_id');
            });
        }
    }
};
