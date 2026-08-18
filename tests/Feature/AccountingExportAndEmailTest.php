<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Jobs\GenerateAccountingExport;
use App\Jobs\SendAccountingDocumentEmail;
use App\Mail\AccountingDocumentMail;
use App\Models\AcctDocument;
use App\Models\AcctEmailDelivery;
use App\Models\AcctExport;
use App\Models\AcctOrganization;
use App\Models\AcctParty;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AccountingTax\AccountingArtifactStore;
use App\Support\AccountingTax\AccountingDocumentService;
use App\Support\AccountingTax\AccountingEmailService;
use App\Support\AccountingTax\AccountingExportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

class AccountingExportAndEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_exports_are_idempotent_versioned_and_private_in_all_supported_formats(): void
    {
        [$organization, $document] = $this->accountingFixture();
        Storage::fake('accounting_private');
        $exports = app(AccountingExportService::class);

        $csv = $exports->request(
            $organization,
            'document_register',
            'csv',
            ['from' => '2026-08-01', 'to' => '2026-08-31'],
            'Asia/Ho_Chi_Minh',
            Admin::SYSTEM_OWNER_ID,
            'register-august',
        );
        $sameCsv = $exports->request(
            $organization,
            'document_register',
            'csv',
            ['to' => '2026-08-31', 'from' => '2026-08-01'],
            'Asia/Ho_Chi_Minh',
            Admin::SYSTEM_OWNER_ID,
            'register-august',
        );

        $this->assertSame($csv->id, $sameCsv->id);
        $csv = $exports->generate($csv);
        Storage::disk('accounting_private')->assertExists($csv->artifact_path);
        $this->assertSame('document-register.v1', $csv->definition_version);
        $this->assertSame(1, $csv->row_count);
        $this->assertSame(hash('sha256', Storage::disk('accounting_private')->get($csv->artifact_path)), $csv->checksum);
        $this->assertStringContainsString("'=CMD", Storage::disk('accounting_private')->get($csv->artifact_path));

        $xlsx = $exports->request($organization, 'document_register', 'xlsx', [], 'Asia/Ho_Chi_Minh', 1, 'xlsx-register');
        $xlsx = $exports->generate($xlsx);
        $workbook = IOFactory::load(Storage::disk('accounting_private')->path($xlsx->artifact_path));
        $this->assertSame('document-register.v1', $workbook->getSheetByName('Metadata')->getCell('B2')->getValue());
        $this->assertSame("'=CMD", $workbook->getSheetByName('Data')->getCell('B2')->getValue());
        $workbook->disconnectWorksheets();

        $pdf = $exports->request($organization, 'vat_operational_estimate', 'pdf', ['currency' => 'VND'], 'Asia/Ho_Chi_Minh', 1, 'pdf-vat');
        $pdf = $exports->generate($pdf);
        $this->assertSame('application/pdf', $pdf->mime_type);
        $this->assertStringStartsWith('%PDF-', Storage::disk('accounting_private')->get($pdf->artifact_path));
        $this->assertSame($document->id, AcctDocument::query()->sole()->id);

        $job = new GenerateAccountingExport($csv->id);
        $this->assertTrue($job->afterCommit);
        $this->assertSame('accounting', $job->queue);
    }

    public function test_email_delivery_uses_immutable_attachments_and_tracks_attempts_without_sending_real_mail(): void
    {
        [$organization, $document] = $this->accountingFixture();
        Storage::fake('accounting_private');
        Mail::fake();

        $exports = app(AccountingExportService::class);
        $export = $exports->request($organization, 'document_register', 'csv', [], 'Asia/Ho_Chi_Minh', 1, 'mail-export');
        $export = $exports->generate($export);
        $document->organization()->update(['legal_name' => 'Tên pháp nhân đã thay đổi']);
        $document->party()->update(['name' => 'Tên đối tác đã thay đổi']);
        $document = $document->fresh(['organization', 'party', 'lines']);

        $emails = app(AccountingEmailService::class);
        $prepared = $emails->prepare(
            document: $document,
            recipientEmail: 'accounting@example.test',
            recipientName: 'Phòng kế toán',
            subject: 'Đối chiếu chứng từ tháng 8',
            templateKey: 'accounting_document_v1',
            exportIds: [$export->id],
            includeDocumentCsv: true,
            requestedBy: Admin::SYSTEM_OWNER_ID,
            clientIdempotencyKey: 'email-document-once',
        );
        $duplicate = $emails->prepare(
            document: $document,
            recipientEmail: 'accounting@example.test',
            recipientName: 'Phòng kế toán',
            subject: 'Đối chiếu chứng từ tháng 8',
            templateKey: 'accounting_document_v1',
            exportIds: [$export->id],
            includeDocumentCsv: true,
            requestedBy: Admin::SYSTEM_OWNER_ID,
            clientIdempotencyKey: 'email-document-once',
        );

        $this->assertTrue($prepared['created']);
        $this->assertFalse($duplicate['created']);
        $this->assertSame($prepared['delivery']->id, $duplicate['delivery']->id);
        $this->assertCount(2, $prepared['delivery']->attachments);
        $this->assertSame('Công ty TNHH AIO Tax', data_get($prepared, 'delivery.payload_snapshot.organization.legal_name'));
        $this->assertSame('Khách hàng kiểm thử', data_get($prepared, 'delivery.payload_snapshot.party.name'));

        foreach ($prepared['delivery']->attachments as $attachment) {
            Storage::disk('accounting_private')->assertExists($attachment['path']);
            $this->assertSame(hash('sha256', Storage::disk('accounting_private')->get($attachment['path'])), $attachment['checksum']);
            $this->assertStringStartsWith('email-deliveries/', $attachment['path']);
        }

        Storage::disk('accounting_private')->put($export->artifact_path, 'tampered source');
        $copiedExport = collect($prepared['delivery']->attachments)->firstWhere('source_type', 'accounting_export');
        $this->assertNotSame('tampered source', Storage::disk('accounting_private')->get($copiedExport['path']));

        $tampered = $emails->prepare(
            document: $document,
            recipientEmail: 'tampered@example.test',
            recipientName: null,
            subject: null,
            templateKey: 'accounting_document_v1',
            exportIds: [],
            includeDocumentCsv: true,
            requestedBy: Admin::SYSTEM_OWNER_ID,
            clientIdempotencyKey: 'email-tampered-attachment',
        )['delivery'];
        Storage::disk('accounting_private')->put($tampered->attachments[0]['path'], 'tampered attachment');

        try {
            (new SendAccountingDocumentEmail($tampered->id))->handle(app(AccountingArtifactStore::class));
            $this->fail('Expected immutable attachment checksum validation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
        }
        Mail::assertNotSent(AccountingDocumentMail::class, fn (AccountingDocumentMail $mail): bool => $mail->delivery->id === $tampered->id);
        $this->assertSame('retrying', $tampered->fresh()->status);

        $job = new SendAccountingDocumentEmail($prepared['delivery']->id);
        $this->assertTrue($job->afterCommit);
        $this->assertSame('mail', $job->queue);
        $job->handle(app(AccountingArtifactStore::class));

        Mail::assertSent(AccountingDocumentMail::class, fn (AccountingDocumentMail $mail): bool => $mail->delivery->id === $prepared['delivery']->id);
        $delivery = AcctEmailDelivery::query()->with('attempts')->findOrFail($prepared['delivery']->id);
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame('sent', $delivery->attempts->first()->status);
        $this->assertSame('sent', $document->fresh()->mail_status);
        $this->assertCount(2, (new AccountingDocumentMail($delivery))->attachments());
    }

    public function test_admin_api_queues_exports_and_email_without_exposing_private_paths(): void
    {
        [$organization, $document] = $this->accountingFixture();
        Storage::fake('accounting_private');
        Queue::fake();
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');

        $exportResponse = $this->withHeader('Idempotency-Key', 'api-export-once')
            ->postJson('/admin/api/accounting-tax/exports', [
                'organization_id' => $organization->id,
                'report_type' => 'document_register',
                'format' => 'xlsx',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'filters' => ['from' => '2026-08-01', 'to' => '2026-08-31'],
            ])
            ->assertAccepted()
            ->assertJsonMissingPath('data.artifact_path')
            ->assertJsonPath('data.definition_version', 'document-register.v1');

        Queue::assertPushed(GenerateAccountingExport::class, 1);
        $exportId = $exportResponse->json('data.id');
        $export = app(AccountingExportService::class)->generate(AcctExport::query()->findOrFail($exportId));

        $this->get("/admin/api/accounting-tax/exports/{$export->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $otherOrganization = AcctOrganization::query()->create([
            'name' => 'Other legal entity',
            'legal_name' => 'Other legal entity',
            'tax_code' => '0109999999',
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'name' => 'Other organization report viewer',
            'key' => 'other-organization-report-viewer',
            'status' => 'active',
            'is_assignable' => true,
        ]);
        $role->permissions()->sync([Permission::query()->where('key', 'accounting.report.view')->firstOrFail()->id]);
        $scopedAdmin = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        AdminRoleAssignment::query()->create([
            'admin_id' => $scopedAdmin->id,
            'role_id' => $role->id,
            'scope_type' => 'organization',
            'scope_value' => (string) $otherOrganization->id,
            'assigned_by' => $owner->id,
        ]);
        $this->flushSession();
        $this->actingAs($scopedAdmin, 'admin')
            ->getJson("/admin/api/accounting-tax/exports/{$export->id}/download")
            ->assertForbidden();

        $this->flushSession();
        $this->actingAs($owner, 'admin');

        $emailResponse = $this->withHeader('Idempotency-Key', 'api-email-once')
            ->postJson("/admin/api/accounting-tax/documents/{$document->id}/email", [
                'recipient_email' => 'recipient@example.test',
                'recipient_name' => 'Kế toán khách hàng',
                'include_document_csv' => true,
                'export_ids' => [$export->id],
            ])
            ->assertAccepted()
            ->assertJsonMissingPath('data.attachments.0.path')
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(SendAccountingDocumentEmail::class, 1);
        $this->assertNotNull($emailResponse->json('data.attachments.0.checksum'));
    }

    /** @return array{AcctOrganization, AcctDocument} */
    private function accountingFixture(): array
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');

        $organization = AcctOrganization::query()->create([
            'name' => 'AIO Tax',
            'legal_name' => 'Công ty TNHH AIO Tax',
            'tax_code' => '0101234567',
            'address' => 'Hà Nội',
            'default_currency' => 'VND',
            'is_default' => true,
            'status' => 'active',
        ]);
        $party = AcctParty::query()->create([
            'organization_id' => $organization->id,
            'type' => 'customer',
            'name' => 'Khách hàng kiểm thử',
            'tax_code' => '0107654321',
            'email' => 'customer@example.test',
            'address' => 'TP Hồ Chí Minh',
        ]);
        $document = app(AccountingDocumentService::class)->create([
            'organization_id' => $organization->id,
            'party_id' => $party->id,
            'direction' => 'outbound',
            'document_type' => 'internal_invoice',
            'document_no' => '=CMD',
            'document_date' => '2026-08-17',
            'currency' => 'VND',
            'idempotency_key' => 'export-fixture',
            'lines' => [[
                'line_type' => 'item',
                'item_kind' => 'service',
                'name' => '+Dịch vụ kiểm thử',
                'unit' => 'lần',
                'quantity' => 1,
                'unit_price' => 100000,
                'discount_amount' => 0,
                'tax_category' => 'standard',
                'tax_rate' => 10,
            ]],
        ], Admin::SYSTEM_OWNER_ID);
        $document->forceFill(['workflow_status' => 'posted', 'posted_at' => now(), 'posted_by' => Admin::SYSTEM_OWNER_ID])->save();

        return [$organization, $document->fresh(['organization', 'party', 'lines'])];
    }
}
