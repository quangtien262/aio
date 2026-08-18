<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctDocument;
use Carbon\CarbonImmutable;

class InvoiceIssuancePolicy
{
    /** @return array<int, array{code:string,message:string,blocking:bool}> */
    public function warnings(AcctDocument $document): array
    {
        $document->loadMissing('lines');
        $kinds = $document->lines->pluck('item_kind')->filter()->unique();
        $requiresGoodsEvent = $kinds->contains(fn (string $kind): bool => in_array($kind, ['goods', 'asset', 'bundle'], true));
        $requiresServiceEvent = $kinds->contains(fn (string $kind): bool => in_array($kind, ['service', 'charge'], true));
        $events = [];
        $warnings = [];

        if ($requiresGoodsEvent) {
            $events['goods_transfer_at'] = data_get($document->metadata, 'goods_transfer_at');
        }

        if ($requiresServiceEvent) {
            $events['service_tax_point'] = data_get($document->metadata, 'service_completed_at')
                ?: data_get($document->metadata, 'payment_received_at');
        }

        if ($events === []) {
            $events['tax_point_at'] = data_get($document->metadata, 'tax_point_at');
        }

        foreach ($events as $event => $value) {
            if (! is_string($value) || trim($value) === '') {
                $warnings[] = [
                    'code' => 'issuance_event_missing',
                    'message' => "Thiếu mốc nghiệp vụ {$event} để xác định thời điểm lập hóa đơn.",
                    'blocking' => true,
                ];

                continue;
            }

            try {
                $eventDate = CarbonImmutable::parse($value)
                    ->setTimezone((string) config('accounting_einvoice.legal_timezone', 'Asia/Ho_Chi_Minh'))
                    ->toDateString();
            } catch (\Throwable) {
                $warnings[] = [
                    'code' => 'issuance_event_invalid',
                    'message' => "Mốc nghiệp vụ {$event} không có định dạng ngày hợp lệ.",
                    'blocking' => true,
                ];

                continue;
            }

            if ($document->document_date?->toDateString() !== $eventDate) {
                $warnings[] = [
                    'code' => 'issuance_date_mismatch',
                    'message' => "Ngày lập hóa đơn không khớp mốc nghiệp vụ {$event} ({$eventDate}).",
                    'blocking' => true,
                ];
            }
        }

        $eventDates = collect($events)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(function (string $value): ?string {
                try {
                    return CarbonImmutable::parse($value)
                        ->setTimezone((string) config('accounting_einvoice.legal_timezone', 'Asia/Ho_Chi_Minh'))
                        ->toDateString();
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->unique();

        if ($eventDates->count() > 1) {
            $warnings[] = [
                'code' => 'mixed_tax_points',
                'message' => 'Dòng hàng hóa và dịch vụ có thời điểm lập hóa đơn khác nhau; cần tách hoặc review nghiệp vụ.',
                'blocking' => true,
            ];
        }

        return $warnings;
    }
}
