<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbContext;

final class FnbSequenceService
{
    /** @return array{sequence_no:int,document_no:string} */
    public function next(FnbContext $context, string $businessDate, string $type, string $prefix): array
    {
        $now = now();
        DB::table('fnb_document_sequences')->insertOrIgnore([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'business_date' => $businessDate,
            'document_type' => $type,
            'prefix' => strtoupper($prefix),
            'next_number' => 1,
            'padding' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('fnb_document_sequences')
            ->where('outlet_id', $context->outletId)
            ->where('business_date', $businessDate)
            ->where('document_type', $type)
            ->lockForUpdate()
            ->firstOrFail();

        $number = (int) $sequence->next_number;
        DB::table('fnb_document_sequences')->where('id', $sequence->id)->update([
            'next_number' => $number + 1,
            'updated_at' => now(),
        ]);

        return [
            'sequence_no' => $number,
            'document_no' => sprintf('%s-%s-%0'.$sequence->padding.'d', $sequence->prefix, str_replace('-', '', $businessDate), $number),
        ];
    }
}
