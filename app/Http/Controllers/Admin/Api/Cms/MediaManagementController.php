<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsMediaFolder;
use App\Support\SiteContext;
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
            'folder_path' => ['nullable', 'string', 'max:255'],
        ]);
        $folderPath = $this->normalizeFolderPath($validated['folder_path'] ?? null);

        if (! empty($validated['file'])) {
            $file = $validated['file'];
            $storedPath = $file->store($this->storageDirectory(), 'public');
            $media = CmsMedia::query()->create([
                'title' => $validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $storedPath,
                'file_url' => CmsMedia::buildPublicUrl($storedPath),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'alt_text' => $validated['alt_text'] ?? null,
                'folder_path' => $folderPath,
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
                'folder_path' => $folderPath,
            ]);
        }

        return response()->json(['message' => 'Đã upload media CMS.', 'data' => $this->serialize($media)], 201);
    }

    public function update(Request $request, int $media): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'folder_path' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var CmsMedia $record */
        $record = CmsMedia::query()->findOrFail($media);
        $record->update([
            'title' => $validated['title'],
            'alt_text' => $validated['alt_text'] ?? null,
            'folder_path' => $this->normalizeFolderPath($validated['folder_path'] ?? null),
        ]);

        return response()->json(['message' => 'Đã cập nhật tên hiển thị media.', 'data' => $this->serialize($record->fresh())]);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $name = trim((string) $validated['name']);
        $path = Str::slug($name) ?: 'folder';
        $basePath = $path;
        $suffix = 2;

        while (CmsMediaFolder::query()->where('path', $path)->exists()) {
            $path = $basePath.'-'.$suffix;
            $suffix++;
        }

        $folder = CmsMediaFolder::query()->create([
            'name' => $name,
            'path' => $path,
            'sort_order' => (int) CmsMediaFolder::query()->max('sort_order') + 1,
        ]);

        return response()->json(['message' => 'Da tao thu muc media.', 'data' => [
            'id' => $folder->id,
            'name' => $folder->name,
            'path' => $folder->path,
            'sort_order' => $folder->sort_order,
            'count' => 0,
        ]], 201);
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
            'folder_path' => $media->folder_path,
            'website_key' => $media->website_key,
            'is_current_website' => $media->website_key === app(SiteContext::class)->websiteKey(),
            'usage_count' => 0,
            'is_unused' => true,
        ];
    }

    private function normalizeFolderPath(?string $folderPath): ?string
    {
        $folderPath = trim((string) $folderPath);

        if ($folderPath === '') {
            return null;
        }

        /** @var CmsMediaFolder|null $folder */
        $folder = CmsMediaFolder::query()->where('path', $folderPath)->first();

        return $folder?->path;
    }

    private function storageDirectory(): string
    {
        $websiteKey = app(SiteContext::class)->websiteKey();
        $websiteKey = Str::slug($websiteKey) ?: 'website-main';

        return 'cms/'.$websiteKey;
    }
}
