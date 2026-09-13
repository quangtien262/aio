<?php

namespace Tests\Unit;

use Modules\FnbPos\Http\FnbPublicProjection;
use PHPUnit\Framework\TestCase;

final class FnbPublicProjectionTest extends TestCase
{
    public function test_nested_operational_and_conflict_snapshots_never_expose_internal_evidence(): void
    {
        $input = ['current_versions' => ['order:1' => 4], 'current_snapshot' => (object) [
            'id' => 1, 'public_id' => '00000000-0000-4000-8000-000000000001', 'grand_total_minor' => 15000,
            'buyer_snapshot' => ['email' => 'private@example.test'],
            'lines' => [['id' => 7, 'recipe_snapshot' => ['cost' => 123], 'metadata' => ['secret' => 'hidden']]],
            'payments' => [['id' => 9, 'provider_reference' => 'private-reference', 'idempotency_key' => 'private-key']],
        ]];

        $output = FnbPublicProjection::operational($input);
        $this->assertSame(['order:1' => 4], $output['current_versions']);
        $this->assertSame($input['current_snapshot']->public_id, $output['current_snapshot']['public_id']);
        $this->assertSame(15000, $output['current_snapshot']['grand_total_minor']);
        $this->assertSame([['id' => 7]], $output['current_snapshot']['lines']);
        $this->assertSame([['id' => 9]], $output['current_snapshot']['payments']);
        $this->assertArrayNotHasKey('buyer_snapshot', $output['current_snapshot']);
    }
}
