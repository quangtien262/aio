<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\AuditChainVerifier;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Services\Security\FnbAuditLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AuditChainPartitionTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_HASH = '15140c0a82c83e6a84a467317d3fcc23d163752b71c8a388d43fa4c4fa870a60';

    public function test_default_writer_preserves_the_pre_partition_golden_hash_contract(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 01:02:03', 'UTC'));
        $request = Request::create('/audit-fixture', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Fixture/1', 'HTTP_X_REQUEST_ID' => 'legacy-fixture']);
        app()->instance('request', $request);
        $log = app(AuditLogger::class)->record('fnb.sale.closed', 'check:legacy', after: ['status' => 'closed', 'amount_minor' => 29000], moduleKey: 'fnb-pos', websiteKey: 'website-legacy');
        $this->assertSame('default', $log->chain_key);
        $this->assertSame(self::LEGACY_HASH, $log->entry_hash);
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
    }

    public function test_additive_partition_migration_preserves_existing_default_rows_and_their_hashes(): void
    {
        $migration = require database_path('migrations/2026_10_18_000001_partition_audit_hash_chains.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('audit_logs', 'chain_key'));
        $id = DB::table('audit_logs')->insertGetId([
            'sequence' => 1, 'previous_hash' => null, 'entry_hash' => self::LEGACY_HASH, 'actor_admin_id' => null,
            'action' => 'fnb.sale.closed', 'module_key' => 'fnb-pos', 'website_key' => 'website-legacy',
            'target_type' => 'check:legacy', 'target_id' => null, 'before' => null,
            'after' => json_encode(['status' => 'closed', 'amount_minor' => 29000], JSON_THROW_ON_ERROR),
            'ip_address' => '127.0.0.1', 'user_agent' => 'Fixture/1', 'request_id' => 'legacy-fixture', 'created_at' => '2026-10-18 01:02:03',
        ]);
        DB::table('audit_log_chain_heads')->where('chain_key', 'default')->update(['last_sequence' => 1, 'last_hash' => self::LEGACY_HASH]);
        $migration->up();
        $migration->up();
        $legacy = AuditLog::findOrFail($id);
        $this->assertSame('default', $legacy->chain_key);
        $this->assertSame(self::LEGACY_HASH, $legacy->entry_hash);
        $this->assertSame(1, (int) $legacy->sequence);
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
        $new = app(AuditLogger::class)->record('legacy.continued');
        $this->assertSame(2, (int) $new->sequence);
        $this->assertSame(self::LEGACY_HASH, $new->previous_hash);
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
    }

    public function test_interleaved_chains_have_independent_sequences_and_heads_and_all_verify(): void
    {
        $logger = app(AuditLogger::class);
        $default = $logger->record('platform.started');
        $a1 = $logger->record('fnb.order.created', chainKey: 'fnb:outlet-a');
        $b1 = $logger->record('fnb.order.created', chainKey: 'fnb:outlet-b');
        $a2 = $logger->record('fnb.order.updated', chainKey: 'fnb:outlet-a');
        $b2 = $logger->record('fnb.order.updated', chainKey: 'fnb:outlet-b');
        $this->assertSame([1, 1, 1, 2, 2], array_map(fn ($log) => (int) $log->sequence, [$default, $a1, $b1, $a2, $b2]));
        $this->assertNull($a1->previous_hash);
        $this->assertNull($b1->previous_hash);
        $this->assertSame($a1->entry_hash, $a2->previous_hash);
        $this->assertSame($b1->entry_hash, $b2->previous_hash);
        $result = app(AuditChainVerifier::class)->verify();
        $this->assertTrue($result['valid']);
        $this->assertSame(5, $result['count']);
        $this->assertSame((int) $default->sequence, $result['last_sequence']);
        $this->assertSame(2, $result['chains']['fnb:outlet-a']['count']);
        $this->assertSame(2, $result['chains']['fnb:outlet-b']['count']);
        $this->assertSame($default->entry_hash, $result['last_hash']);
    }

    public function test_changing_both_row_and_head_chain_identity_is_detected_by_bound_hash(): void
    {
        $log = app(AuditLogger::class)->record('fnb.payment.collected', chainKey: 'fnb:source');
        DB::table('audit_logs')->where('id', $log->id)->update(['chain_key' => 'fnb:destination']);
        DB::table('audit_log_chain_heads')->where('chain_key', 'fnb:source')->update(['chain_key' => 'fnb:destination']);
        $result = app(AuditChainVerifier::class)->verify();
        $this->assertFalse($result['valid']);
        $this->assertSame('fnb:destination', $result['chain_key']);
        $this->assertStringContainsString('entry_hash', $result['error']);
    }

    #[DataProvider('tamperCases')]
    public function test_tampering_in_one_partition_invalidates_multi_chain_verification(string $kind): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('platform.healthy');
        $first = $logger->record('fnb.order.created', after: ['amount_minor' => 29000], chainKey: 'fnb:broken');
        $second = $logger->record('fnb.order.updated', chainKey: 'fnb:broken');
        $logger->record('fnb.order.other', chainKey: 'fnb:healthy');
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
        match ($kind) {
            'payload' => DB::table('audit_logs')->where('id', $first->id)->update(['after' => json_encode(['amount_minor' => 1])]),
            'previous_hash' => DB::table('audit_logs')->where('id', $second->id)->update(['previous_hash' => str_repeat('1', 64)]),
            'entry_hash' => DB::table('audit_logs')->where('id', $second->id)->update(['entry_hash' => str_repeat('2', 64)]),
            'head_hash' => DB::table('audit_log_chain_heads')->where('chain_key', 'fnb:broken')->update(['last_hash' => str_repeat('3', 64)]),
            'head_sequence' => DB::table('audit_log_chain_heads')->where('chain_key', 'fnb:broken')->update(['last_sequence' => 999]),
            'missing_head' => DB::table('audit_log_chain_heads')->where('chain_key', 'fnb:broken')->delete(),
        };
        $result = app(AuditChainVerifier::class)->verify();
        $this->assertFalse($result['valid']);
        $this->assertSame('fnb:broken', $result['chain_key']);
        $this->assertNotEmpty($result['error']);
    }

    public static function tamperCases(): array
    {
        return array_map(fn ($kind) => [$kind], ['payload', 'previous_hash', 'entry_hash', 'head_hash', 'head_sequence', 'missing_head']);
    }

    public function test_partition_migration_refuses_rollback_once_named_partition_has_events(): void
    {
        app(AuditLogger::class)->record('fnb.order.created', chainKey: 'fnb:outlet-a');
        $migration = require database_path('migrations/2026_10_18_000001_partition_audit_hash_chains.php');
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function test_fnb_outlet_and_website_audits_use_separate_verifiable_chains(): void
    {
        $logger = app(FnbAuditLogger::class);
        $a1 = $logger->record('fnb.order.created', 'website-a', null, outletIds: [1]);
        $a2 = $logger->record('fnb.order.updated', 'website-a', null, outletIds: [1]);
        $b = $logger->record('fnb.order.created', 'website-a', null, outletIds: [2]);
        $website = $logger->record('fnb.menu.updated', 'website-a', null);
        $otherWebsite = $logger->record('fnb.order.created', 'website-b', null, outletIds: [1]);
        $this->assertSame($a1->chain_key, $a2->chain_key);
        $this->assertCount(4, array_unique([$a1->chain_key, $b->chain_key, $website->chain_key, $otherWebsite->chain_key]));
        $this->assertSame($a1->entry_hash, $a2->previous_hash);
        $mixedA = $logger->record('fnb.menu.published', 'website-a', null, outletIds: [2, 1, 2]);
        $mixedB = $logger->record('fnb.menu.published', 'website-a', null, outletIds: [1, 2]);
        $this->assertSame($mixedA->chain_key, $mixedB->chain_key);
        $this->assertSame($mixedA->entry_hash, $mixedB->previous_hash);
        $this->assertSame([['type' => 'outlet', 'value' => '1'], ['type' => 'outlet', 'value' => '2']], $mixedB->after['audit_scope']);
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
    }
}
