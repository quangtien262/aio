<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctEinvoiceInbound;
use App\Support\AccountingTax\AccountingArtifactStore;
use App\Support\AccountingTax\AccountingArtifactValidator;
use RuntimeException;

class MsmiInboundArtifactService
{
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly ProviderExecutionGuard $guard,
        private readonly ProviderResponseSanitizer $sanitizer,
        private readonly AccountingArtifactStore $artifactStore,
        private readonly AccountingArtifactValidator $artifactValidator,
    ) {}

    public function download(AcctEinvoiceInbound $invoice, string $format): AcctEinvoiceInbound
    {
        $this->guard->assertConnectorEnabled();
        $invoice->loadMissing('connection');
        $client = $this->factory->inbound($invoice->connection);

        if (! in_array($format, ['xml', 'html'], true)) {
            throw new RuntimeException('Định dạng artifact mSMI không hợp lệ.');
        }

        $contents = $format === 'xml'
            ? $client->downloadXml($invoice->provider_invoice_id)
            : $client->downloadHtml($invoice->provider_invoice_id);
        $this->artifactValidator->assertValid($format, $contents);
        $checksum = hash('sha256', $contents);
        $path = sprintf(
            'inbound/%d/%d/minvoice-%s-%s.%s',
            $invoice->organization_id,
            $invoice->id,
            preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice->provider_invoice_id),
            substr($checksum, 0, 16),
            $format,
        );

        if (! $this->artifactStore->existsWithChecksum($path, $checksum)) {
            $this->artifactStore->putAtomically($path, $contents);
        }

        $invoice->forceFill([
            $format.'_path' => $path,
            $format.'_checksum' => $checksum,
            'content_checksum' => $checksum,
        ])->save();

        return $invoice->fresh();
    }

    public function checkWarning(AcctEinvoiceInbound $invoice): AcctEinvoiceInbound
    {
        $this->guard->assertConnectorEnabled();
        $invoice->loadMissing('connection');
        $payload = $this->sanitizer->sanitize(
            $this->factory->inbound($invoice->connection)->warning($invoice->provider_invoice_id),
        );
        $warnings = array_values(array_unique(array_filter([
            ...($invoice->warnings ?? []),
            ((string) ($invoice->illegal_status ?? '0')) === '1' ? 'illegal_invoice' : null,
            ((string) ($payload['xmlnven'] ?? '0')) === '1' ? 'xml_integrity_failed' : null,
            (($payload['has_xml'] ?? true) === false) ? 'xml_unavailable' : null,
            (($payload['status'] ?? null) && ! in_array((string) $payload['status'], ['0', 'valid', 'success'], true))
                ? 'provider_warning'
                : null,
        ])));

        $invoice->forceFill([
            'warnings' => $warnings,
            'warning_payload' => $payload,
            'synced_at' => now(),
        ])->save();

        return $invoice->fresh();
    }
}
