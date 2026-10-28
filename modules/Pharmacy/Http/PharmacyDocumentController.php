<?php

namespace Modules\Pharmacy\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pharmacy\Services\Documents\PharmacyDocumentService;
use Symfony\Component\HttpFoundation\Response;

final class PharmacyDocumentController
{
    public function upload(Request $request, PharmacyDocumentService $service): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:10240']);

        return response()->json(['data' => $service->upload($request->user('admin'), $request->except('file'), $request->file('file'), (string) $request->header('Idempotency-Key', ''))])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, string $id, PharmacyDocumentService $service): Response
    {
        return $service->download($request->user('admin'), $id);
    }

    public function printJob(Request $request, string $id, PharmacyDocumentService $service): Response
    {
        return $service->download($request->user('admin'), $id, true);
    }
}
