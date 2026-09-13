<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;

final class FnbOutboxService
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function emit(
        FnbContext $context,
        string $aggregateType,
        string|int $aggregateId,
        int $aggregateVersion,
        string $eventType,
        array $payload,
    ): array {
        $identity = [
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'aggregate_version' => $aggregateVersion,
            'event_type' => $eventType,
        ];
        $now = now();
        DB::table('fnb_outbox_events')->insertOrIgnore($identity + [
            'event_id' => (string) Str::uuid(),
            'schema_version' => 1,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (array) DB::table('fnb_outbox_events')->where($identity)->firstOrFail();
    }
}
