<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Jobs\SendAccountingDocumentEmail;
use App\Models\AcctDocument;
use App\Models\AcctEmailDelivery;
use App\Support\AccountingTax\AccountingEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailDeliveryController
{
    public function index(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'document_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['queued', 'sending', 'retrying', 'sent', 'failed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = AcctEmailDelivery::query()
            ->with('attempts')
            ->where('organization_id', $payload['organization_id'])
            ->when($payload['document_id'] ?? null, fn ($query, $documentId) => $query->where('document_id', $documentId))
            ->when($payload['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) ($payload['per_page'] ?? 30));
        $deliveries = collect($page->items())
            ->map(self::serialize(...))
            ->values()
            ->all();

        return response()->json(['data' => [
            'items' => $deliveries,
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
        ]]);
    }

    public function store(
        Request $request,
        AcctDocument $document,
        AccountingEmailService $emails,
    ): JsonResponse {
        $payload = $request->validate([
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'template_key' => ['sometimes', Rule::in(['accounting_document_v1'])],
            'export_ids' => ['sometimes', 'array', 'max:10'],
            'export_ids.*' => ['integer', 'distinct'],
            'include_document_csv' => ['sometimes', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
        $result = $emails->prepare(
            document: $document,
            recipientEmail: $payload['recipient_email'],
            recipientName: $payload['recipient_name'] ?? null,
            subject: $payload['subject'] ?? null,
            templateKey: $payload['template_key'] ?? 'accounting_document_v1',
            exportIds: $payload['export_ids'] ?? [],
            includeDocumentCsv: $payload['include_document_csv'] ?? true,
            requestedBy: $request->user('admin')?->id,
            clientIdempotencyKey: $request->header('Idempotency-Key') ?: ($payload['idempotency_key'] ?? null),
        );

        if ($result['created']) {
            SendAccountingDocumentEmail::dispatch($result['delivery']->id);
        }

        return response()->json(['data' => self::serialize($result['delivery']->load('attempts'))], 202);
    }

    public function retry(
        AcctEmailDelivery $delivery,
        AccountingEmailService $emails,
    ): JsonResponse {
        $delivery = $emails->retry($delivery);
        SendAccountingDocumentEmail::dispatch($delivery->id);

        return response()->json(['data' => self::serialize($delivery)], 202);
    }

    public static function serialize(AcctEmailDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,
            'organization_id' => $delivery->organization_id,
            'document_id' => $delivery->document_id,
            'recipient_email' => $delivery->recipient_email,
            'recipient_name' => $delivery->recipient_name,
            'template_key' => $delivery->template_key,
            'subject' => $delivery->subject,
            'status' => $delivery->status,
            'attachments' => collect($delivery->attachments ?? [])->map(fn (array $attachment): array => [
                'name' => $attachment['name'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'byte_size' => $attachment['byte_size'] ?? null,
                'checksum' => $attachment['checksum'] ?? null,
                'source_type' => $attachment['source_type'] ?? null,
                'source_id' => $attachment['source_id'] ?? null,
            ])->values()->all(),
            'attempt_count' => $delivery->attempt_count,
            'provider' => $delivery->provider,
            'provider_message_id' => $delivery->provider_message_id,
            'last_error' => $delivery->last_error,
            'queued_at' => $delivery->queued_at?->toIso8601String(),
            'started_at' => $delivery->started_at?->toIso8601String(),
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'completed_at' => $delivery->completed_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
            'attempts' => $delivery->relationLoaded('attempts')
                ? $delivery->attempts->map(fn ($attempt): array => [
                    'id' => $attempt->id,
                    'attempt_no' => $attempt->attempt_no,
                    'status' => $attempt->status,
                    'provider' => $attempt->provider,
                    'provider_message_id' => $attempt->provider_message_id,
                    'error_message' => $attempt->error_message,
                    'started_at' => $attempt->started_at?->toIso8601String(),
                    'completed_at' => $attempt->completed_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }
}
