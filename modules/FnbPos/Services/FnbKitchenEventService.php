<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbContext;

final class FnbKitchenEventService
{
    /**
     * Allocate the cursor while holding the per-outlet row lock until the
     * caller's transaction commits. A semantic event key makes retry safe.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function append(
        FnbContext $context,
        string $eventKey,
        string $eventType,
        array $payload,
        ?int $stationId = null,
        ?int $ticketId = null,
        ?int $ticketLineId = null,
    ): array {
        $existing = DB::table('fnb_kitchen_events')
            ->where('outlet_id', $context->outletId)
            ->where('event_key', $eventKey)
            ->first();
        if ($existing !== null) {
            return (array) $existing;
        }

        $now = now();
        DB::table('fnb_kitchen_event_sequences')->insertOrIgnore([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'next_sequence' => 1,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $allocator = DB::table('fnb_kitchen_event_sequences')
            ->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)
            ->lockForUpdate()
            ->firstOrFail();
        $sequence = (int) $allocator->next_sequence;
        DB::table('fnb_kitchen_event_sequences')->where('id', $allocator->id)->update([
            'next_sequence' => $sequence + 1,
            'version' => (int) $allocator->version + 1,
            'updated_at' => now(),
        ]);

        DB::table('fnb_kitchen_events')->insert([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'sequence' => $sequence,
            'event_key' => $eventKey,
            'prep_station_id' => $stationId,
            'ticket_id' => $ticketId,
            'ticket_line_id' => $ticketLineId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'actor_id' => $context->actorId,
            'occurred_at' => now(),
        ]);

        return (array) DB::table('fnb_kitchen_events')
            ->where('outlet_id', $context->outletId)
            ->where('sequence', $sequence)
            ->firstOrFail();
    }
}
