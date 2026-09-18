<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Models\CmsMedia;
use App\Models\ContentApiResourceLink;
use App\Models\ContentApiToken;
use App\Support\AuditLogger;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ContentMediaController
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'payload_hash' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $websiteKey = app(SiteContext::class)->websiteKey();
        $identity = $this->identity($token, $websiteKey, $validated['external_id']);
        $link = ContentApiResourceLink::query()->where($identity)->first();

        if ($link !== null && filled($validated['payload_hash'] ?? null)
            && hash_equals((string) $link->payload_hash, $validated['payload_hash'])) {
            $existing = CmsMedia::query()->find($link->resource_id);
            if ($existing !== null) {
                return response()->json(['message' => 'Ảnh đã tồn tại.', 'data' => $this->serialize($existing)]);
            }
        }

        $file = $validated['file'];
        $payloadHash = $validated['payload_hash'] ?? hash_file('sha256', $file->getRealPath());
        $directory = 'cms/'.(Str::slug($websiteKey) ?: 'website-main');
        $storedPath = $file->store($directory, 'public');

        try {
            $media = DB::transaction(function () use ($file, $storedPath, $validated, $identity, $token, $payloadHash): CmsMedia {
                $media = CmsMedia::query()->create([
                    'title' => $validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'file_path' => $storedPath,
                    'file_url' => CmsMedia::buildPublicUrl($storedPath),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize() ?: 0,
                    'alt_text' => $validated['alt_text'] ?? null,
                    'folder_path' => null,
                ]);
                ContentApiResourceLink::query()->updateOrCreate($identity, [
                    'resource_id' => $media->id,
                    'payload_hash' => $payloadHash,
                    'last_token_id' => $token->id,
                ]);

                return $media;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPath);
            throw $exception;
        }

        $audit->record('cms.content_api.media.created', $media, null, [
            'external_id' => $validated['external_id'],
            'file_url' => $media->file_url,
            'source_key' => $token->source_key,
        ], websiteKey: $websiteKey);

        return response()->json(['message' => 'Đã upload ảnh đại diện.', 'data' => $this->serialize($media)], 201);
    }

    private function identity(ContentApiToken $token, string $websiteKey, string $externalId): array
    {
        return [
            'source_key' => $token->source_key,
            'website_key' => $websiteKey,
            'resource_type' => 'cms_media',
            'external_id' => $externalId,
        ];
    }

    private function serialize(CmsMedia $media): array
    {
        return [
            'id' => $media->id,
            'title' => $media->title,
            'file_url' => $media->file_url,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
        ];
    }
}
