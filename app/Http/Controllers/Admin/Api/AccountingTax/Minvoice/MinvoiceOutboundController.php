<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax\Minvoice;

use App\Http\Controllers\Controller;
use App\Models\AcctDocument;
use App\Models\AcctEinvoiceTransmission;
use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\AccountingArtifactStore;
use App\Support\AccountingTax\Providers\EinvoiceTransmissionService;
use App\Support\AccountingTax\Providers\ProviderSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MinvoiceOutboundController extends Controller
{
    public function __construct(
        private readonly EinvoiceTransmissionService $transmissions,
        private readonly ProviderSeriesService $seriesService,
        private readonly ProviderApiSerializer $serializer,
        private readonly AccountingArtifactStore $artifacts,
    ) {}

    public function preview(Request $request, AcctDocument $document): JsonResponse
    {
        $validated = $this->connectionPayload($request);
        $connection = AcctProviderConnection::query()->findOrFail($validated['connection_id']);

        return response()->json([
            'data' => $this->transmissions->preview($document, $connection, $validated['series'] ?? null),
        ]);
    }

    public function series(AcctProviderConnection $connection): JsonResponse
    {
        abort_unless($connection->channel === 'outbound', 404);

        return response()->json([
            'data' => $connection->series()
                ->orderByDesc('is_default')
                ->orderBy('series')
                ->get()
                ->map(fn ($series): array => $this->serializer->series($series))
                ->all(),
        ]);
    }

    public function syncSeries(Request $request, AcctProviderConnection $connection): JsonResponse
    {
        $validated = $request->validate(['invoice_type' => ['nullable', 'integer', 'min:1', 'max:20']]);
        $series = $this->seriesService->sync($connection, (int) ($validated['invoice_type'] ?? 1));

        return response()->json([
            'data' => [
                'series' => collect($series)->map(fn ($row): array => $this->serializer->series($row))->all(),
            ],
        ]);
    }

    public function createDraft(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->enqueue($request, $document, 'create_draft');
    }

    public function createAndSign(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->enqueue($request, $document, 'create_and_sign');
    }

    public function signSend(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->enqueue($request, $document, 'sign_send');
    }

    public function syncStatus(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->enqueue($request, $document, 'sync_status');
    }

    public function downloadPdf(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->enqueue($request, $document, 'download_pdf');
    }

    public function downloadXml(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->enqueue($request, $document, 'download_xml');
    }

    public function transmissions(AcctDocument $document): JsonResponse
    {
        return response()->json([
            'data' => AcctEinvoiceTransmission::query()
                ->where('document_id', $document->id)
                ->latest('id')
                ->get()
                ->map(fn ($row): array => $this->serializer->transmission($row))
                ->all(),
        ]);
    }

    public function legalPreview(Request $request, AcctDocument $document): JsonResponse
    {
        $validated = $this->legalPayload($request, true);
        $connection = AcctProviderConnection::query()->findOrFail($validated['connection_id']);

        return response()->json([
            'data' => $this->transmissions->legalPreview(
                $document,
                $connection,
                $validated['operation'],
                $validated,
            ),
        ]);
    }

    public function adjust(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->blockedLegalOperation($request, $document, 'adjust');
    }

    public function replace(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->blockedLegalOperation($request, $document, 'replace');
    }

    public function cancel(Request $request, AcctDocument $document): JsonResponse
    {
        return $this->blockedLegalOperation($request, $document, 'cancel');
    }

    public function artifact(AcctEinvoiceTransmission $transmission, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['pdf', 'xml'], true), 404);
        $path = $format === 'pdf' ? $transmission->pdf_path : $transmission->xml_path;
        $checksum = $format === 'pdf' ? $transmission->pdf_checksum : $transmission->xml_checksum;
        abort_if($path === null || ! $this->artifacts->existsWithChecksum($path, $checksum), 404);

        return $this->artifacts->disk()->download(
            $path,
            sprintf('invoice-%d.%s', $transmission->document_id, $format),
        );
    }

    private function enqueue(Request $request, AcctDocument $document, string $operation): JsonResponse
    {
        $validated = $this->connectionPayload($request, signing: $operation === 'sign_send');
        $connection = AcctProviderConnection::query()->findOrFail($validated['connection_id']);
        $options = array_filter([
            'series' => $validated['series'] ?? null,
            'signing' => $validated['signing'] ?? null,
        ], fn ($value): bool => $value !== null);
        $transmission = $this->transmissions->enqueue($document, $connection, $operation, $options);

        return response()->json(['data' => $this->serializer->transmission($transmission)], 202);
    }

    private function connectionPayload(Request $request, bool $signing = false): array
    {
        return $request->validate([
            'connection_id' => ['required', 'integer', 'exists:acct_provider_connections,id'],
            'series' => ['nullable', 'string', 'max:80'],
            'signing' => [$signing ? 'sometimes' : 'nullable', 'array'],
            'signing.mode' => ['nullable', Rule::in(['FILE', 'EASY', 'ICA', 'INTRUST'])],
        ]);
    }

    private function legalPayload(Request $request, bool $operationRequired = false): array
    {
        return $request->validate([
            'connection_id' => ['required', 'integer', 'exists:acct_provider_connections,id'],
            'operation' => [$operationRequired ? 'required' : 'sometimes', Rule::in(['adjust', 'replace', 'cancel'])],
            'series' => ['nullable', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:2000'],
            'legal_reference' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function blockedLegalOperation(
        Request $request,
        AcctDocument $document,
        string $operation,
    ): JsonResponse {
        $validated = $this->legalPayload($request);
        $connection = AcctProviderConnection::query()->findOrFail($validated['connection_id']);
        $transmission = $this->transmissions->enqueueBlockedLegalOperation(
            $document,
            $connection,
            $operation,
            $validated,
        );

        return response()->json([
            'message' => 'Nghiệp vụ đã được lưu để audit nhưng bị khóa an toàn; không có request provider được gửi.',
            'data' => $this->serializer->transmission($transmission),
        ], 409);
    }
}
