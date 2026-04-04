<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Http\Controllers\Admin\Api\Cms\Concerns\InteractsWithScopedCmsRecords;
use App\Models\CmsMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaManagementController
{
    use InteractsWithScopedCmsRecords;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureScopedPayloadAllowed($request, $validated);

        $file = $validated['file'];
        $storedPath = $file->store('cms', 'public');
        $media = CmsMedia::query()->create([
            'title' => $validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $storedPath,
            'file_url' => asset('storage/'.$storedPath),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'alt_text' => $validated['alt_text'] ?? null,
            'website_key' => $validated['website_key'] ?? null,
            'owner_key' => $validated['owner_key'] ?? null,
            'tenant_key' => $validated['tenant_key'] ?? null,
        ]);

        return response()->json(['message' => 'Đã upload media CMS.', 'data' => $this->serialize($media)], 201);
    }

    public function destroy(Request $request, AdminDataScope $adminDataScope, int $media): JsonResponse
    {
        /** @var CmsMedia $record */
        $record = $this->resolveScopedRecord($request, $adminDataScope, new CmsMedia(), $media);
        Storage::disk('public')->delete($record->file_path);
        $record->delete();

        return response()->json(['message' => 'Đã xóa media CMS.']);
    }

    private function serialize(CmsMedia $media): array
    {
        return [
            'id' => $media->id,
            'title' => $media->title,
            'file_path' => $media->file_path,
            'file_url' => $media->file_url,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
            'website_key' => $media->website_key,
            'owner_key' => $media->owner_key,
            'tenant_key' => $media->tenant_key,
        ];
    }
}
