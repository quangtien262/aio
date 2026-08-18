<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctDocumentNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AccountingDocumentNumberService
{
    public function next(int $organizationId, string $documentType, CarbonInterface $date): string
    {
        return DB::transaction(function () use ($organizationId, $documentType, $date): string {
            AcctDocumentNumberSequence::query()->insertOrIgnore([
                'organization_id' => $organizationId,
                'document_type' => $documentType,
                'year' => $date->year,
                'prefix' => $this->prefix($documentType),
                'next_number' => 1,
                'padding' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = AcctDocumentNumberSequence::query()
                ->where('organization_id', $organizationId)
                ->where('document_type', $documentType)
                ->where('year', $date->year)
                ->lockForUpdate()
                ->firstOrFail();

            do {
                $number = sprintf(
                    '%s-%d-%s',
                    $sequence->prefix,
                    $date->year,
                    str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT),
                );
                $sequence->increment('next_number');
                $sequence->refresh();
            } while (AcctDocument::query()
                ->where('organization_id', $organizationId)
                ->where('document_no', $number)
                ->exists());

            return $number;
        }, 3);
    }

    private function prefix(string $documentType): string
    {
        return match ($documentType) {
            'tax_invoice' => 'HD',
            'credit_note' => 'DCG',
            'debit_note' => 'DCT',
            'receipt' => 'PT',
            'expense' => 'PC',
            default => 'NB',
        };
    }
}
