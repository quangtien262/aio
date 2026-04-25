<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:5120', 'required_without:file_url'],
            'file_url' => ['nullable', 'url', 'required_without:file'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['file'])) {
            $file = $validated['file'];
            $storedPath = $file->store('cms', 'public');
            $media = CmsMedia::query()->create([
                'title' => $validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $storedPath,
                'file_url' => CmsMedia::buildPublicUrl($storedPath),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'alt_text' => $validated['alt_text'] ?? null,
            ]);
        } else {
            $remoteUrl = $validated['file_url'];
            $media = CmsMedia::query()->create([
                'title' => $validated['title'] ?? Str::of(parse_url($remoteUrl, PHP_URL_PATH) ?: 'remote-image')->afterLast('/')->beforeLast('.')->replace(['-', '_'], ' ')->title()->toString(),
                'file_path' => null,
                'file_url' => $remoteUrl,
                'mime_type' => 'image/external',
                'size' => 0,
                'alt_text' => $validated['alt_text'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Đã upload media CMS.', 'data' => $this->serialize($media)], 201);
    }

    public function destroy(Request $request, int $media): JsonResponse
    {
        /** @var CmsMedia $record */
        $record = CmsMedia::query()->findOrFail($media);

        if ($record->file_path) {
            Storage::disk('public')->delete($record->file_path);
        }

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
        ];
    }
}
