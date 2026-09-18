<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Models\CmsPost;
use App\Models\ContentApiResourceLink;
use App\Models\ContentApiToken;
use App\Support\AuditLogger;
use App\Support\Cms\CmsPostWriter;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentPostController
{
    public function link(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'post_id' => ['required', 'integer'],
            'expected_slug' => ['required', 'string', 'max:255'],
        ]);
        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $websiteKey = app(SiteContext::class)->websiteKey();
        $post = CmsPost::query()->findOrFail($validated['post_id']);

        if (! hash_equals($post->slug, $validated['expected_slug'])) {
            throw ValidationException::withMessages([
                'expected_slug' => 'Slug xác nhận không khớp với bài viết đích.',
            ]);
        }

        $identity = [
            'source_key' => $token->source_key,
            'website_key' => $websiteKey,
            'resource_type' => 'cms_post',
            'external_id' => $validated['external_id'],
        ];
        $created = false;

        $link = DB::transaction(function () use ($identity, $post, $token, &$created): ContentApiResourceLink {
            $link = ContentApiResourceLink::query()->where($identity)->lockForUpdate()->first();
            if ($link !== null && (int) $link->resource_id !== (int) $post->id) {
                throw ValidationException::withMessages([
                    'external_id' => 'External ID đã liên kết với một bài viết khác.',
                ]);
            }

            if ($link === null) {
                $created = true;
                $link = ContentApiResourceLink::query()->create([
                    ...$identity,
                    'resource_id' => $post->id,
                    'payload_hash' => hash('sha256', $post->getRawOriginal('updated_at').':'.$post->id),
                    'last_token_id' => $token->id,
                ]);
            }

            return $link;
        });

        $audit->record('cms.content_api.post.linked', $post, null, [
            'external_id' => $validated['external_id'],
            'source_key' => $token->source_key,
            'resource_link_id' => $link->id,
        ], websiteKey: $websiteKey);

        return response()->json([
            'message' => $created ? 'Đã liên kết bài viết hiện có.' : 'Liên kết bài viết đã tồn tại.',
            'data' => $this->serialize($post, $validated['external_id']),
        ], $created ? 201 : 200);
    }

    public function upsert(Request $request, CmsPostWriter $writer, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'payload_hash' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:80'],
            'featured_media_id' => ['nullable', 'integer'],
            'category_id' => ['required', 'integer'],
            'publish_at' => ['nullable', 'date'],
            'is_highlight' => ['nullable', 'boolean'],
        ]);
        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $status = $validated['status'] ?? 'draft';
        if ($status === 'published' && ! $token->hasAbility('posts.publish')) {
            throw ValidationException::withMessages([
                'status' => 'API token không có quyền xuất bản trực tiếp.',
            ]);
        }
        $validated['status'] = $status;
        $websiteKey = app(SiteContext::class)->websiteKey();
        $identity = [
            'source_key' => $token->source_key,
            'website_key' => $websiteKey,
            'resource_type' => 'cms_post',
            'external_id' => $validated['external_id'],
        ];
        $created = false;

        $post = DB::transaction(function () use ($identity, $validated, $writer, $token, &$created): CmsPost {
            $link = ContentApiResourceLink::query()->where($identity)->lockForUpdate()->first();
            $post = $link ? CmsPost::query()->find($link->resource_id) : null;
            $before = $post?->getAttributes();
            $post = $post
                ? $writer->update($post, $validated)
                : $writer->create($validated);
            $created = $before === null;
            ContentApiResourceLink::query()->updateOrCreate($identity, [
                'resource_id' => $post->id,
                'payload_hash' => $validated['payload_hash'] ?? hash('sha256', json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'last_token_id' => $token->id,
            ]);

            return $post;
        });

        $audit->record($created ? 'cms.content_api.post.created' : 'cms.content_api.post.updated', $post, null, [
            'external_id' => $validated['external_id'],
            'source_key' => $token->source_key,
            'status' => $post->status,
        ], websiteKey: $websiteKey);

        return response()->json([
            'message' => $created ? 'Đã tạo bài viết nháp.' : 'Đã cập nhật bài viết.',
            'data' => $this->serialize($post, $validated['external_id']),
        ], $created ? 201 : 200);
    }

    public function show(Request $request, string $externalId): JsonResponse
    {
        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $link = ContentApiResourceLink::query()->where([
            'source_key' => $token->source_key,
            'website_key' => app(SiteContext::class)->websiteKey(),
            'resource_type' => 'cms_post',
            'external_id' => $externalId,
        ])->firstOrFail();
        $post = CmsPost::query()->with(['tags', 'category', 'featuredMedia'])->findOrFail($link->resource_id);

        return response()->json(['data' => $this->serialize($post, $externalId)]);
    }

    private function serialize(CmsPost $post, string $externalId): array
    {
        return [
            'id' => $post->id,
            'external_id' => $externalId,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'meta_keywords' => $post->meta_keywords,
            'tags' => $post->relationLoaded('tags') ? $post->tags->pluck('name')->all() : [],
            'category_id' => $post->category_id,
            'featured_media_id' => $post->featured_media_id,
            'featured_media_url' => $post->featuredMedia?->file_url,
            'publish_at' => $post->publish_at?->toAtomString(),
            'is_highlight' => $post->is_highlight,
        ];
    }
}
