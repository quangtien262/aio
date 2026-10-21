<?php

namespace Modules\Manufacturing\Http;

use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Manufacturing\Services\Documents\ManufacturingDocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManufacturingDocumentController
{
    public function upload(Request $request, ManufacturingDocumentService $documents): JsonResponse
    {
        $v = $request->validate(['factory_id' => 'required|uuid', 'resource_type' => 'required|in:style,order', 'resource_id' => 'required|uuid', 'file' => 'required|file']);

        return response()->json(['data' => $documents->upload($request->user('admin'), $v['factory_id'], $v['resource_type'], $v['resource_id'], $request->file('file'), (string) $request->header('Idempotency-Key'))]);
    }

    public function download(Request $request, string $document, ManufacturingDocumentService $documents): BinaryFileResponse
    {
        $row = $documents->authorized($request->user('admin'), $document);
        app(AuditLogger::class)->record('manufacturing.document.download', 'manufacturing-document', null, ['public_id' => $row->public_id, 'checksum' => $row->checksum], $request->user('admin'), 'manufacturing', '', 'manufacturing');

        return response()->download(Storage::disk('manufacturing_private')->path($row->path), $row->original_name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
