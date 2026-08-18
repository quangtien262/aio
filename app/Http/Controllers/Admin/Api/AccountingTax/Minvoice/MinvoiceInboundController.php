<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax\Minvoice;

use App\Http\Controllers\Controller;
use App\Jobs\AccountingTax\SyncMsmiInboundInvoices;
use App\Models\AcctDocument;
use App\Models\AcctEinvoiceInbound;
use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\AccountingArtifactStore;
use App\Support\AccountingTax\Providers\InboundInvoiceReviewService;
use App\Support\AccountingTax\Providers\MsmiInboundArtifactService;
use App\Support\AccountingTax\Providers\ProviderConnectionPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MinvoiceInboundController extends Controller
{
    public function __construct(
        private readonly MsmiInboundArtifactService $artifacts,
        private readonly InboundInvoiceReviewService $review,
        private readonly ProviderConnectionPolicy $connectionPolicy,
        private readonly ProviderApiSerializer $serializer,
        private readonly AccountingArtifactStore $artifactStore,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:acct_provider_connections,id'],
            'reconciliation_status' => ['nullable', Rule::in(['unmatched', 'matched', 'mismatch', 'ignored'])],
            'warning' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $connection = AcctProviderConnection::query()->findOrFail($validated['connection_id']);
        abort_unless($connection->channel === 'inbound', 404);
        $query = AcctEinvoiceInbound::query()
            ->where('connection_id', $connection->id)
            ->latest('issued_at')
            ->latest('id');

        if (isset($validated['reconciliation_status'])) {
            $query->where('reconciliation_status', $validated['reconciliation_status']);
        }

        if (($validated['warning'] ?? false) === true) {
            $query->whereNotNull('warnings')->where('warnings', '!=', '[]');
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 30));

        return response()->json([
            'data' => collect($page->items())->map(fn ($invoice): array => $this->serializer->inbound($invoice))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(AcctEinvoiceInbound $invoice): JsonResponse
    {
        return response()->json(['data' => $this->serializer->inbound($invoice->load(['lines', 'vatBreakdowns']))]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:acct_provider_connections,id'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'size' => ['nullable', 'integer', 'min:1', 'max:200'],
            'seller_tax_code' => ['nullable', 'string', 'max:32'],
            'seller_name' => ['nullable', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:80'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
        ]);
        $connection = AcctProviderConnection::query()->findOrFail($validated['connection_id']);
        abort_unless($connection->channel === 'inbound', 404);
        $this->connectionPolicy->assertNetworkCallAllowed($connection);

        SyncMsmiInboundInvoices::dispatch($connection->id, $validated)->afterCommit();

        return response()->json([
            'data' => [
                'status' => 'queued',
                'connection_id' => $connection->id,
                'filters' => collect($validated)->except('connection_id')->all(),
            ],
        ], 202);
    }

    public function downloadXml(AcctEinvoiceInbound $invoice): JsonResponse
    {
        return response()->json(['data' => $this->serializer->inbound($this->artifacts->download($invoice, 'xml'))]);
    }

    public function downloadHtml(AcctEinvoiceInbound $invoice): JsonResponse
    {
        return response()->json(['data' => $this->serializer->inbound($this->artifacts->download($invoice, 'html'))]);
    }

    public function checkWarning(AcctEinvoiceInbound $invoice): JsonResponse
    {
        return response()->json(['data' => $this->serializer->inbound($this->artifacts->checkWarning($invoice))]);
    }

    public function createInternalDraft(Request $request, AcctEinvoiceInbound $invoice): JsonResponse
    {
        $document = $this->review->createInternalDraft($invoice, $request->user('admin')?->id);

        return response()->json([
            'data' => [
                'invoice' => $this->serializer->inbound($invoice->fresh(['lines'])),
                'document_id' => $document->id,
                'workflow_status' => $document->workflow_status,
                'tax_eligibility' => $document->tax_eligibility,
            ],
        ], 201);
    }

    public function match(Request $request, AcctEinvoiceInbound $invoice): JsonResponse
    {
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'exists:acct_documents,id'],
        ]);
        $document = AcctDocument::query()->findOrFail($validated['document_id']);
        $invoice = $this->review->match($invoice, $document);

        return response()->json([
            'data' => [
                'invoice' => $this->serializer->inbound($invoice),
                'comparison' => $this->review->compare($invoice, $document),
            ],
        ]);
    }

    public function unmatch(AcctEinvoiceInbound $invoice): JsonResponse
    {
        return response()->json(['data' => $this->serializer->inbound($this->review->unmatch($invoice))]);
    }

    public function artifact(AcctEinvoiceInbound $invoice, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['xml', 'html'], true), 404);
        $path = $format === 'xml' ? $invoice->xml_path : $invoice->html_path;
        $checksum = $format === 'xml' ? $invoice->xml_checksum : $invoice->html_checksum;
        abort_if($path === null || ! $this->artifactStore->existsWithChecksum($path, $checksum), 404);

        return $this->artifactStore->disk()->download(
            $path,
            sprintf('inbound-invoice-%d.%s', $invoice->id, $format),
        );
    }
}
