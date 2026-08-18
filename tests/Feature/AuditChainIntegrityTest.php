<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\AuditChainVerifier;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditChainIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_chain_is_append_only_masked_and_verifiable(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('accounting.document.created', 'document:1', null, [
            'document_no' => 'NB-2026-000001',
            'credentials' => ['api_token' => 'must-not-leak'],
            'party' => ['tax_code' => '0100000000', 'name' => 'Customer'],
        ], moduleKey: 'accounting-tax');
        $logger->record('accounting.document.approved', 'document:1', ['status' => 'draft'], ['status' => 'approved'], moduleKey: 'accounting-tax');

        $first = AuditLog::query()->orderBy('sequence')->firstOrFail();
        $second = AuditLog::query()->orderBy('sequence')->skip(1)->firstOrFail();

        $this->assertSame(1, (int) $first->sequence);
        $this->assertSame($first->entry_hash, $second->previous_hash);
        $this->assertArrayNotHasKey('credentials', $first->after);
        $this->assertArrayNotHasKey('tax_code', $first->after['party']);
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);

        try {
            $first->forceFill(['action' => 'tampered'])->save();
            $this->fail('Audit log model mutation should be rejected.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        DB::table('audit_logs')->where('id', $first->id)->update(['action' => 'tampered']);
        $this->assertFalse(app(AuditChainVerifier::class)->verify()['valid']);
    }
}
