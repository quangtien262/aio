<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_bundles', function (Blueprint $table): void {
            $table->decimal('finished_quantity', 20, 6)->default(0);
            $table->decimal('scrap_quantity', 20, 6)->default(0);
            $table->decimal('wip_quantity', 20, 6)->default(0);
            $table->index(['factory_id', 'wip_quantity'], 'mfg_bundles_factory_wip_idx');
        });

        // Metadata remains the write-model history. Backfill in bounded batches
        // with decimal arithmetic; JSON/SQLite floating point must not round it.
        DB::table('mfg_bundles')->select('id', 'quantity', 'status', 'metadata')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $metadata = is_string($row->metadata) ? (json_decode($row->metadata, true) ?: []) : (array) $row->metadata;
                $finished = BigDecimal::of((string) ($metadata['finished_quantity'] ?? '0'))->toScale(6, RoundingMode::HalfUp);
                $scrap = BigDecimal::of((string) ($metadata['scrap_quantity'] ?? '0'))->toScale(6, RoundingMode::HalfUp);
                $wip = BigDecimal::max(BigDecimal::of((string) $row->quantity)->minus($finished)->minus($scrap), BigDecimal::zero());
                if (in_array($row->status, ['merged', 'cancelled', 'split', 'shipped', 'completed', 'scrapped'], true)) {
                    $wip = BigDecimal::zero();
                }
                DB::table('mfg_bundles')->where('id', $row->id)->update([
                    'finished_quantity' => (string) $finished,
                    'scrap_quantity' => (string) $scrap,
                    'wip_quantity' => (string) $wip->toScale(6),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('mfg_bundles', function (Blueprint $table): void {
            $table->dropIndex('mfg_bundles_factory_wip_idx');
            $table->dropColumn(['finished_quantity', 'scrap_quantity', 'wip_quantity']);
        });
    }
};
